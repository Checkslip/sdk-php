<?php

declare(strict_types=1);

namespace Checkslip\Model;

use Checkslip\Internal\Arr;

/**
 * The time-limited price of a package while an offer runs.
 */
final class PackageOffer
{
    public function __construct(
        /** The offer price of the package. */
        public readonly Money $price,
        /** The offer price of one credit. */
        public readonly Money $pricePerCredit,
        /** The offer price per credit in baht, as display text with three decimals. */
        public readonly string $pricePerCreditBaht,
        /** When the offer stops and the regular price applies again. */
        public readonly \DateTimeImmutable $endsAt,
    ) {}

    /**
     * @param array<string, mixed> $d
     */
    public static function fromArray(array $d): self
    {
        return new self(
            Money::fromArray(Arr::obj($d, 'price')),
            Money::fromArray(Arr::obj($d, 'price_per_credit')),
            Arr::str($d, 'price_per_credit_baht'),
            Arr::date($d, 'ends_at'),
        );
    }
}
