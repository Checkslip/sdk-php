<?php

declare(strict_types=1);

namespace Checkslip\Resource;

use Checkslip\Enum\CountBucket;
use Checkslip\Enum\ExportColumn;
use Checkslip\Enum\FreeReason;
use Checkslip\Enum\Outcome;
use Checkslip\Internal\Multipart;
use Checkslip\Internal\Query;
use Checkslip\Internal\Retry;
use Checkslip\Internal\Transport;
use Checkslip\Model\SlipCheck;
use Checkslip\Model\SlipCheckRecord;
use Checkslip\Model\SlipCounts;
use Checkslip\Model\SlipExport;
use Checkslip\Model\SlipExportPart;
use Checkslip\Page;
use Checkslip\Upload;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Transfer lookups and their history.
 */
final class Slips extends AbstractResource
{
    /**
     * Looks a transfer up with the sending bank, from the QR text on the slip.
     *
     * Every lookup carries an Idempotency-Key. Pass your own (for example your
     * order id plus the slip's QR) so a retry after a crash or a timeout returns
     * the first answer and never uses a second credit. Without one, the SDK
     * makes a random key and reuses it for its own retries of this call.
     *
     * A failed lookup (INVALID_QR, QR_TAMPERED, TOO_OLD, ...) throws
     * {@see \Checkslip\Exception\UnprocessableEntityException} and uses no credit.
     *
     * @param string $qr The text read from the slip's QR code.
     * @param string|null $clientReference Your own id for this lookup, such as an order number (printable ASCII, at most 128).
     */
    public function lookup(string $qr, ?string $clientReference = null, ?string $idempotencyKey = null): SlipCheck
    {
        $body = ['qr' => $qr];
        if ($clientReference !== null) {
            $body['client_reference'] = $clientReference;
        }
        [$data, $response] = $this->transport->json('POST', '/api/slips/lookup', [], $body, self::idempotency($idempotencyKey));

        return SlipCheck::fromArray($data, self::pendingRetryAfter($response));
    }

    /**
     * Looks a transfer up from a photo or screenshot of the slip. Checkslip reads
     * the QR in the image and does not keep the image.
     *
     * JPEG, PNG or WebP, up to 10 MB and 50 megapixels. Other files get
     * INVALID_FILE and use no credit.
     *
     * ```php
     * $client->slips->lookupImage(Upload::fromPath('/tmp/slip.jpg'));
     * ```
     */
    public function lookupImage(Upload $image, ?string $clientReference = null, ?string $idempotencyKey = null): SlipCheck
    {
        $form = Multipart::build($image, 'image', ['client_reference' => $clientReference]);
        $headers = self::idempotency($idempotencyKey) + ['Content-Type' => $form['contentType']];
        $response = $this->transport->send('POST', '/api/slips/lookup', [], $form['body'], $headers);

        return SlipCheck::fromArray(Transport::decode($response), self::pendingRetryAfter($response));
    }

    /**
     * Past lookups of the account, newest first.
     *
     * @param string|null $q Free text search (reference, name, amount)
     * @param list<Outcome|string>|null $outcome Only these outcomes
     * @param list<Outcome|string>|null $outcomeNot Leave these outcomes out
     * @param string|null $senderBank Three-digit Bank of Thailand code, such as "004"
     * @param string|null $apiKey The 12-character id of an API key
     * @param string|null $after Only lookups after this request_id
     * @param list<bool|string>|null $charged true, false and/or "unsettled"
     * @param list<FreeReason|string>|null $freeReason
     * @param int|null $limit 1 to 100, default 20
     *
     * @return Page<SlipCheckRecord>
     */
    public function list(
        ?string $q = null,
        ?string $transRef = null,
        ?array $outcome = null,
        ?array $outcomeNot = null,
        ?string $senderBank = null,
        ?string $apiKey = null,
        ?string $after = null,
        ?array $charged = null,
        ?array $freeReason = null,
        \DateTimeInterface|string|null $from = null,
        \DateTimeInterface|string|null $to = null,
        ?int $limit = null,
        ?string $cursor = null,
    ): Page {
        return $this->page('/api/slips', [
            'q' => $q,
            'trans_ref' => $transRef,
            'outcome' => Query::csv($outcome),
            'outcome_not' => Query::csv($outcomeNot),
            'sender_bank' => $senderBank,
            'api_key' => $apiKey,
            'after' => $after,
            'charged' => $charged === null ? null : Query::list(array_map(static fn (bool|string $c): string => \is_bool($c) ? ($c ? 'true' : 'false') : $c, $charged)),
            'free_reason' => Query::list($freeReason),
            'from' => Query::dateTime($from),
            'to' => Query::dateTime($to),
            'limit' => $limit,
            'cursor' => $cursor,
        ], SlipCheckRecord::fromArray(...));
    }

    /**
     * One past lookup by its request_id. Another account's id answers 404.
     */
    public function get(string $requestId): SlipCheckRecord
    {
        return SlipCheckRecord::fromArray($this->fetch(self::path('/api/slips/%s', $requestId)));
    }

    /**
     * Lookup counts per time bucket, for charts.
     *
     * @param list<Outcome|string>|null $outcome
     * @param list<Outcome|string>|null $outcomeNot
     */
    public function counts(
        \DateTimeInterface|string $from,
        \DateTimeInterface|string $to,
        CountBucket|string $bucket,
        ?array $outcome = null,
        ?array $outcomeNot = null,
    ): SlipCounts {
        [$data] = $this->transport->json('GET', '/api/slips/counts', [
            'from' => Query::dateTime($from),
            'to' => Query::dateTime($to),
            'bucket' => $bucket instanceof CountBucket ? $bucket->value : $bucket,
            'outcome' => Query::csv($outcome),
            'outcome_not' => Query::csv($outcomeNot),
        ]);

        return SlipCounts::fromArray($data);
    }

    /**
     * Starts a CSV export of the history with the same filters as {@see list()}.
     * Up to 1,000,000 rows come back READY at once; more run as a job you poll
     * with {@see getExport()}. The file holds payer and payee names: keep it safe.
     *
     * @param list<ExportColumn|string> $columns
     * @param list<Outcome|string>|null $outcome
     * @param list<Outcome|string>|null $outcomeNot
     * @param list<bool|string>|null $charged
     * @param list<FreeReason|string>|null $freeReason
     * @param string|null $cursor The continue_cursor of an earlier, truncated export
     */
    public function createExport(
        array $columns,
        ?string $q = null,
        ?string $transRef = null,
        ?array $outcome = null,
        ?array $outcomeNot = null,
        ?string $senderBank = null,
        ?string $apiKey = null,
        ?array $charged = null,
        ?array $freeReason = null,
        \DateTimeInterface|string|null $from = null,
        \DateTimeInterface|string|null $to = null,
        ?string $cursor = null,
    ): SlipExport {
        if ($columns === []) {
            throw new \InvalidArgumentException('Pick at least one column.');
        }
        $body = array_filter([
            'columns' => Query::list($columns),
            'q' => $q,
            'trans_ref' => $transRef,
            'outcome' => Query::list($outcome),
            'outcome_not' => Query::list($outcomeNot),
            'sender_bank' => $senderBank,
            'api_key' => $apiKey,
            'charged' => $charged === null ? null : array_map(static fn (bool|string $c): string => \is_bool($c) ? ($c ? 'true' : 'false') : $c, $charged),
            'free_reason' => Query::list($freeReason),
            'from' => Query::dateTime($from),
            'to' => Query::dateTime($to),
            'cursor' => $cursor,
        ], static fn (mixed $v): bool => $v !== null);
        [$data] = $this->transport->json('POST', '/api/slips/exports', [], $body);

        return SlipExport::fromArray($data);
    }

    public function getExport(string $exportId): SlipExport
    {
        return SlipExport::fromArray($this->fetch(self::path('/api/slips/exports/%s', $exportId)));
    }

    /**
     * Downloads one CSV part of a READY export. The body is a stream, so a large
     * part does not have to fit in memory: copy it to a file in chunks.
     *
     * The part link is signed and short-lived; fetch a fresh one with
     * {@see getExport()} once it has expired.
     */
    public function downloadExportPart(SlipExportPart|string $part): StreamInterface
    {
        $url = $part instanceof SlipExportPart ? $part->url : $part;
        if (!str_starts_with($url, '/') && !str_starts_with($url, $this->transport->baseUrl() . '/')) {
            throw new \InvalidArgumentException('The part URL does not belong to the configured base URL; refusing to send the API key there.');
        }

        return $this->transport->send('GET', $url)->getBody();
    }

    private static function pendingRetryAfter(ResponseInterface $response): ?int
    {
        return $response->getStatusCode() === 202 ? Retry::afterSeconds($response) : null;
    }
}
