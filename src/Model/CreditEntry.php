<?php

declare(strict_types=1);

namespace Checkslip\Model;

use Checkslip\Enum\CreditReason;
use Checkslip\Enum\FreeReason;
use Checkslip\Internal\Arr;
use Checkslip\Internal\Enums;

/**
 * One change to the credit balance.
 */
final class CreditEntry
{
    public function __construct(
        public readonly string $id,
        /** Credits added (positive) or used (negative). */
        public readonly int $delta,
        public readonly int $balanceAfter,
        public readonly ?\DateTimeImmutable $expiresAtAfter,
        public readonly CreditReason|string $reason,
        public readonly FreeReason|string|null $freeReason,
        /** What caused the entry: ORDER, CHECK, STAFF or SYSTEM. */
        public readonly ?string $refType,
        public readonly ?string $refId,
        public readonly \DateTimeImmutable $createdAt,
    ) {}

    /**
     * @param array<string, mixed> $d
     */
    public static function fromArray(array $d): self
    {
        return new self(
            Arr::str($d, 'id'),
            Arr::int($d, 'delta'),
            Arr::int($d, 'balance_after'),
            Arr::dateOrNull($d, 'expires_at_after'),
            Enums::of(CreditReason::class, Arr::str($d, 'reason')),
            Enums::ofNullable(FreeReason::class, Arr::strOrNull($d, 'free_reason')),
            Arr::strOrNull($d, 'ref_type'),
            Arr::strOrNull($d, 'ref_id'),
            Arr::date($d, 'created_at'),
        );
    }
}
