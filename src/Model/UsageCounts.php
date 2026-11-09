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
        /**
         * Always 0: the API no longer counts this reason, and the field is
         * going away. These lookups are counted in $freeOther. An absent
         * field reads as 0, so reading it stays safe either way.
         */
        public readonly int $freeNoCredit,
        /** Going away with $freeNoCredit, and counted in $freeOther the same way. */
        public readonly int $freeHoldExpired,
        public readonly int $freeSystemError,
        /**
         * Lookups that were not charged with no reason given, including the
         * two deprecated counts above and any reason this version does not
         * know. Reads as 0 against a server that does not send it.
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
            // These three may be absent, so they fall back to 0.
            Arr::intOrNull($d, 'free_no_credit') ?? 0,
            Arr::intOrNull($d, 'free_hold_expired') ?? 0,
            Arr::int($d, 'free_system_error'),
            Arr::intOrNull($d, 'free_other') ?? 0,
        );
    }
}
