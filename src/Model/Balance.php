<?php

declare(strict_types=1);

namespace Checkslip\Model;

use Checkslip\Internal\Arr;

/**
 * The account's credit balance. One credit is used per FOUND lookup.
 */
final class Balance
{
    public function __construct(
        public readonly string $accountId,
        public readonly int $credits,
        /** True when $credits is at or below $threshold. */
        public readonly bool $low,
        public readonly int $threshold,
        /** How the threshold was worked out: AVG_7_DAYS, FALLBACK_10_PERCENT or NONE. */
        public readonly string $thresholdBasis,
        public readonly ?\DateTimeImmutable $expiresAt,
        public readonly \DateTimeImmutable $updatedAt,
    ) {}

    /**
     * @param array<string, mixed> $d
     */
    public static function fromArray(array $d): self
    {
        return new self(
            Arr::str($d, 'account_id'),
            Arr::int($d, 'credits'),
            Arr::bool($d, 'low'),
            Arr::int($d, 'threshold'),
            Arr::str($d, 'threshold_basis'),
            Arr::dateOrNull($d, 'expires_at'),
            Arr::date($d, 'updated_at'),
        );
    }
}
