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
        public readonly int $freeSystemError,
        /**
         * Lookups that were not charged with no reason given, including any
         * reason this version does not know. Reads as 0 against a server that
         * does not send it.
         *
         * The API used to also return free_no_credit and free_hold_expired.
         * Both had been returning 0 on every account, and those lookups count
         * here.
         */
        public readonly int $freeOther = 0,
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
            Arr::int($d, 'free_system_error'),
            // free_other may be absent, so it falls back to 0.
            Arr::intOrNull($d, 'free_other') ?? 0,
        );
    }
}
