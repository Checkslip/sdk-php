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
        /** The account this balance belongs to. */
        public readonly string $accountId,
        /**
         * All the credits the account has now, the sum of $creditsLeft over
         * every lot in $lots. A slip lookup (looking up the transfer's data
         * with the bank) uses 1 credit only when a matching transfer is found.
         */
        public readonly int $credits,
        /**
         * True when $credits is below $threshold, meaning credits are running
         * low. It is the same rule as the low-credit email and the web app, so
         * read this value rather than working it out.
         */
        public readonly bool $low,
        /**
         * The credit level below which this account counts as running low
         * ($low, and the low-credit email). It is set from this account's own
         * use. 0 means no warning yet ($low is false).
         */
        public readonly int $threshold,
        /**
         * How the threshold was worked out. Always null: the API stopped
         * sending this to customers in November 2026, and the published API
         * reference does not list this field. There is no replacement field.
         * It stays here so code that reads it keeps working.
         */
        public readonly ?string $thresholdBasis,
        /**
         * The latest expiry among lots that still have credits (after every lot
         * has run out, it still gives the date of the last one). null means
         * credits were never bought or given. For when the first lot runs out,
         * read $nextExpiresAt.
         */
        public readonly ?\DateTimeImmutable $expiresAt,
        /** When this balance last changed. */
        public readonly \DateTimeImmutable $updatedAt,
        /**
         * About how many credits this account would use in a year at its recent
         * pace. null means there has not been enough recent use to tell.
         */
        public readonly ?int $creditsPerYear = null,
        /**
         * The lots that still have credits, in the order they are charged, the
         * next one first. $credits is their sum. Lots with nothing left are not
         * listed.
         *
         * @var list<CreditLot>
         */
        public readonly array $lots = [],
        /**
         * The soonest expiry among lots that still have credits, the date some
         * of the credits start to expire. null means no credits are left.
         */
        public readonly ?\DateTimeImmutable $nextExpiresAt = null,
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
            Arr::strOrNull($d, 'threshold_basis'),
            Arr::dateOrNull($d, 'expires_at'),
            Arr::date($d, 'updated_at'),
            Arr::intOrNull($d, 'credits_per_year'),
            array_map(CreditLot::fromArray(...), Arr::objects($d, 'lots')),
            Arr::dateOrNull($d, 'next_expires_at'),
        );
    }
}
