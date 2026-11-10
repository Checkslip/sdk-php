<?php

declare(strict_types=1);

namespace Checkslip\Resource;

use Checkslip\Enum\CountBucket;
use Checkslip\Enum\CustomerFreeReason;
use Checkslip\Enum\Outcome;
use Checkslip\Internal\Multipart;
use Checkslip\Internal\Query;
use Checkslip\Internal\Retry;
use Checkslip\Internal\Transport;
use Checkslip\Model\SlipCheck;
use Checkslip\Model\SlipCheckRecord;
use Checkslip\Model\SlipCounts;
use Checkslip\Page;
use Checkslip\Upload;
use Psr\Http\Message\ResponseInterface;

/**
 * Transfer lookups and their history.
 */
final class Slips extends AbstractResource
{
    /** CSV exports of past lookups. */
    public readonly SlipExports $exports;

    public function __construct(Transport $transport)
    {
        parent::__construct($transport);
        $this->exports = new SlipExports($transport);
    }

    /**
     * Looks a transfer up with the sending bank.
     *
     * Pass the text read from the slip's QR code, or an {@see Upload} of the
     * slip image. Checkslip reads the QR in an image and does not keep it.
     * JPEG, PNG or WebP, up to 10 MB and 50 megapixels; other files get
     * INVALID_FILE and use no credit.
     *
     * Every lookup carries an Idempotency-Key. Pass your own (for example your
     * order id plus the slip's QR) so a retry after a crash or a timeout returns
     * the first answer and never uses a second credit. Without one, the SDK
     * makes a random key and reuses it for its own retries of this call.
     *
     * A failed lookup (INVALID_QR, QR_TAMPERED, TOO_OLD, ...) throws
     * {@see \Checkslip\Exception\UnprocessableEntityException} and uses no credit.
     *
     * ```php
     * $client->slips->lookup($qrText);
     * $client->slips->lookup(Upload::fromPath('/tmp/slip.jpg'));
     * ```
     *
     * @param string|Upload $slip The QR text, or the slip image.
     * @param string|null $clientReference Your own id for this lookup, such as an order number (printable ASCII, at most 128).
     */
    public function lookup(string|Upload $slip, ?string $clientReference = null, ?string $idempotencyKey = null): SlipCheck
    {
        if ($slip instanceof Upload) {
            $form = Multipart::build($slip, 'image', ['client_reference' => $clientReference]);
            $headers = self::idempotency($idempotencyKey) + ['Content-Type' => $form['contentType']];
            $response = $this->transport->send('POST', '/api/slips/lookup', [], $form['body'], $headers);

            return SlipCheck::fromArray(Transport::decode($response), self::pendingRetryAfter($response));
        }

        if (trim($slip) === '') {
            throw new \InvalidArgumentException('Pass the QR text of the slip, or an Upload of its image.');
        }
        $body = ['qr' => $slip];
        if ($clientReference !== null) {
            $body['client_reference'] = $clientReference;
        }
        [$data, $response] = $this->transport->json('POST', '/api/slips/lookup', [], $body, self::idempotency($idempotencyKey));

        return SlipCheck::fromArray($data, self::pendingRetryAfter($response));
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
     * @param list<CustomerFreeReason|string>|null $freeReason The nine reasons a customer can receive
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
            'charged' => self::charged($charged),
            'free_reason' => Query::list($freeReason),
            'from' => Query::dateTime($from),
            'to' => Query::dateTime($to),
            'limit' => $limit,
            'cursor' => $cursor,
        ], SlipCheckRecord::fromArray(...));
    }

    /**
     * Every matching past lookup across all pages.
     *
     * @param list<Outcome|string>|null $outcome
     * @param list<Outcome|string>|null $outcomeNot
     * @param list<bool|string>|null $charged
     * @param list<CustomerFreeReason|string>|null $freeReason The nine reasons a customer can receive
     *
     * @return \Generator<int, SlipCheckRecord>
     */
    public function listAll(
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
    ): \Generator {
        return $this->list($q, $transRef, $outcome, $outcomeNot, $senderBank, $apiKey, $after, $charged, $freeReason, $from, $to, $limit, $cursor)->autoPaging();
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

    private static function pendingRetryAfter(ResponseInterface $response): ?int
    {
        return $response->getStatusCode() === 202 ? Retry::afterSeconds($response) : null;
    }
}
