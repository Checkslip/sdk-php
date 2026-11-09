<?php

declare(strict_types=1);

namespace Checkslip\Model;

use Checkslip\Internal\Arr;

/**
 * One CSV file of an export. $url is signed and short-lived; download it with
 * {@see \Checkslip\Resource\Slips::downloadExportPart()}.
 */
final class SlipExportPart
{
    public function __construct(
        public readonly int $part,
        public readonly ?int $rows,
        public readonly string $url,
        public readonly \DateTimeImmutable $urlExpiresAt,
    ) {}

    /**
     * @param array<string, mixed> $d
     */
    public static function fromArray(array $d): self
    {
        return new self(Arr::int($d, 'part'), Arr::intOrNull($d, 'rows'), Arr::str($d, 'url'), Arr::date($d, 'url_expires_at'));
    }
}
