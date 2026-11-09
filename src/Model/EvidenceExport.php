<?php

declare(strict_types=1);

namespace Checkslip\Model;

use Checkslip\Enum\ExportState;
use Checkslip\Enum\Stream;
use Checkslip\Internal\Arr;
use Checkslip\Internal\Enums;

/**
 * An evidence file of the audit log for a range of closed UTC days.
 * Poll it until READY or FAILED; the file is deleted after $expiresAt.
 */
final class EvidenceExport
{
    /**
     * @param list<Stream|string> $streams
     */
    public function __construct(
        public readonly string $exportId,
        public readonly ExportState|string $status,
        public readonly \DateTimeImmutable $fromDay,
        public readonly \DateTimeImmutable $toDay,
        public readonly array $streams,
        public readonly \DateTimeImmutable $createdAt,
        public readonly ?\DateTimeImmutable $readyAt,
        public readonly ?\DateTimeImmutable $expiresAt,
        /** SHA-256 of the file, hex. Set once the file is READY. */
        public readonly ?string $sha256,
        public readonly ?int $sizeBytes,
        /** File format, such as "checkslip-audit-export/2". */
        public readonly ?string $format,
    ) {}

    /**
     * @param array<string, mixed> $d
     */
    public static function fromArray(array $d): self
    {
        $manifest = Arr::objOrNull($d, 'manifest') ?? [];

        return new self(
            Arr::str($d, 'export_id'),
            Enums::of(ExportState::class, Arr::str($d, 'status')),
            Arr::date($d, 'from_day'),
            Arr::date($d, 'to_day'),
            Enums::list(Stream::class, Arr::strings($d, 'streams')),
            Arr::date($d, 'created_at'),
            Arr::dateOrNull($d, 'ready_at'),
            Arr::dateOrNull($d, 'expires_at'),
            Arr::strOrNull($manifest, 'sha256'),
            Arr::intOrNull($manifest, 'size_bytes'),
            Arr::strOrNull($manifest, 'format'),
        );
    }

    public function isReady(): bool
    {
        return $this->status === ExportState::Ready;
    }
}
