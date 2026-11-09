<?php

declare(strict_types=1);

namespace Checkslip\Model;

use Checkslip\Enum\ExportState;
use Checkslip\Internal\Arr;
use Checkslip\Internal\Enums;

/**
 * A CSV export of the lookup history. Small exports come back READY at once
 * (mode DIRECT); larger ones run as a job, so poll {@see \Checkslip\Resource\Slips::getExport()}.
 * Parts are deleted 24 hours after they are ready.
 */
final class SlipExport
{
    /**
     * @param array<string, mixed> $request The filters and columns the export was made with.
     * @param list<SlipExportPart> $parts
     */
    public function __construct(
        public readonly string $exportId,
        public readonly ExportState|string $state,
        public readonly ?string $mode,
        public readonly \DateTimeImmutable $createdAt,
        public readonly array $request,
        public readonly ?int $rows,
        public readonly bool $truncated,
        /** Pass this as the cursor of the next export to carry on where this one stopped. */
        public readonly ?string $continueCursor,
        public readonly ?\DateTimeImmutable $expiresAt,
        public readonly array $parts,
    ) {}

    /**
     * @param array<string, mixed> $d
     */
    public static function fromArray(array $d): self
    {
        return new self(
            Arr::str($d, 'export_id'),
            Enums::of(ExportState::class, Arr::str($d, 'state')),
            Arr::strOrNull($d, 'mode'),
            Arr::date($d, 'created_at'),
            Arr::objOrNull($d, 'request') ?? [],
            Arr::intOrNull($d, 'rows'),
            Arr::boolOrNull($d, 'truncated') ?? false,
            Arr::strOrNull($d, 'continue_cursor'),
            Arr::dateOrNull($d, 'expires_at'),
            array_map(SlipExportPart::fromArray(...), Arr::objects($d, 'parts')),
        );
    }

    public function isReady(): bool
    {
        return $this->state === ExportState::Ready;
    }
}
