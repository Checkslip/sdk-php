<?php

declare(strict_types=1);

namespace Checkslip\Resource;

use Checkslip\Enum\ExportColumn;
use Checkslip\Enum\FreeReason;
use Checkslip\Enum\Outcome;
use Checkslip\Internal\Query;
use Checkslip\Model\SlipExport;
use Checkslip\Model\SlipExportPart;
use Psr\Http\Message\StreamInterface;

/**
 * CSV exports of past lookups.
 */
final class SlipExports extends AbstractResource
{
    /**
     * Starts a CSV export of the history with the same filters as {@see list()}.
     * Up to 1,000,000 rows come back READY at once; more run as a job you poll
     * with {@see get()}. The file holds payer and payee names: keep it safe.
     *
     * @param list<ExportColumn|string> $columns
     * @param list<Outcome|string>|null $outcome
     * @param list<Outcome|string>|null $outcomeNot
     * @param list<bool|string>|null $charged
     * @param list<FreeReason|string>|null $freeReason
     * @param string|null $cursor The continue_cursor of an earlier, truncated export
     */
    public function create(
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
            'charged' => self::charged($charged),
            'free_reason' => Query::list($freeReason),
            'from' => Query::dateTime($from),
            'to' => Query::dateTime($to),
            'cursor' => $cursor,
        ], static fn(mixed $v): bool => $v !== null);
        [$data] = $this->transport->json('POST', '/api/slips/exports', [], $body);

        return SlipExport::fromArray($data);
    }

    public function get(string $exportId): SlipExport
    {
        return SlipExport::fromArray($this->fetch(self::path('/api/slips/exports/%s', $exportId)));
    }

    /**
     * Downloads one CSV part of a READY export. The body is a stream, so a large
     * part does not have to fit in memory: copy it to a file in chunks.
     *
     * The part link is signed and short-lived; fetch a fresh one with
     * {@see get()} once it has expired.
     */
    public function downloadPart(SlipExportPart|string $part): StreamInterface
    {
        $url = $part instanceof SlipExportPart ? $part->url : $part;
        if (!str_starts_with($url, '/') && !str_starts_with($url, $this->transport->baseUrl() . '/')) {
            throw new \InvalidArgumentException('The part URL does not belong to the configured base URL; refusing to send the API key there.');
        }

        return $this->transport->send('GET', $url)->getBody();
    }
}
