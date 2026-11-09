<?php

declare(strict_types=1);

namespace Checkslip\Resource;

use Checkslip\Enum\Stream;
use Checkslip\Internal\Query;
use Checkslip\Model\EvidenceExport;
use Checkslip\Page;
use Psr\Http\Message\StreamInterface;

/**
 * Evidence files for an auditor, a bank or a court.
 */
final class AuditExports extends AbstractResource
{
    /**
     * @return Page<EvidenceExport>
     */
    public function list(?int $limit = null, ?string $cursor = null): Page
    {
        return $this->page('/api/audit/exports', ['limit' => $limit, 'cursor' => $cursor], EvidenceExport::fromArray(...));
    }

    /**
     * Every evidence file across all pages.
     *
     * @return \Generator<int, EvidenceExport>
     */
    public function listAll(?int $limit = null, ?string $cursor = null): \Generator
    {
        return $this->list($limit, $cursor)->autoPaging();
    }

    /**
     * Requests an evidence file for closed UTC days (not today; yesterday from
     * 00:10 UTC), at most 31 days. Comes back QUEUED; poll {@see get()}.
     * The same Idempotency-Key returns the same file instead of building another.
     *
     * @param list<Stream|string>|null $streams AUDIT_EVENTS, which is the only stream exports carry. All of them when null.
     */
    public function create(
        \DateTimeInterface|string $fromDay,
        \DateTimeInterface|string $toDay,
        ?array $streams = null,
        ?string $idempotencyKey = null,
    ): EvidenceExport {
        $body = ['from_day' => Query::date($fromDay), 'to_day' => Query::date($toDay)];
        if ($streams !== null) {
            $body['streams'] = Query::list($streams) ?? [];
        }

        return EvidenceExport::fromArray($this->transport->json('POST', '/api/audit/exports', [], $body, self::idempotency($idempotencyKey))[0]);
    }

    public function get(string $exportId): EvidenceExport
    {
        return EvidenceExport::fromArray($this->fetch(self::path('/api/audit/exports/%s', $exportId)));
    }

    /**
     * Downloads a READY evidence file (newline-delimited JSON) as a stream.
     * Compare its SHA-256 with {@see EvidenceExport::$sha256}.
     */
    public function download(string $exportId): StreamInterface
    {
        return $this->transport->send('GET', self::path('/api/audit/exports/%s/file', $exportId))->getBody();
    }
}
