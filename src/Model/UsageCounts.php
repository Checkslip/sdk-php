<?php

declare(strict_types=1);

namespace Checkslip\Model;

use Checkslip\Internal\Arr;

/**
 * Lookups in a period: how many used a credit, and how many were free and why.
 */
final class UsageCounts
{
    public function __construct(
        public readonly int $checks,
        public readonly int $charged,
        public readonly int $freeNotFound,
        public readonly int $freeDuplicate,
        public readonly int $freeUnavailable,
        public readonly int $freeInvalidQr,
        public readonly int $freeInvalidFile,
        public readonly int $freeQrTampered,
        public readonly int $freeTooOld,
        public readonly int $freeNoCredit,
        public readonly int $freeHoldExpired,
        public readonly int $freeSystemError,
    ) {}

    /**
     * @param array<string, mixed> $d
     */
    public static function fromArray(array $d): self
    {
        return new self(
            Arr::int($d, 'checks'),
            Arr::int($d, 'charged'),
            Arr::int($d, 'free_not_found'),
            Arr::int($d, 'free_duplicate'),
            Arr::int($d, 'free_unavailable'),
            Arr::int($d, 'free_invalid_qr'),
            Arr::int($d, 'free_invalid_file'),
            Arr::int($d, 'free_qr_tampered'),
            Arr::int($d, 'free_too_old'),
            Arr::int($d, 'free_no_credit'),
            Arr::int($d, 'free_hold_expired'),
            Arr::int($d, 'free_system_error'),
        );
    }
}
