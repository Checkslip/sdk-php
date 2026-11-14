<?php

declare(strict_types=1);

namespace Checkslip\Model;

use Checkslip\Internal\Arr;

/**
 * A credit package you can buy.
 */
final class Package
{
    public function __construct(
        public readonly string $code,
        public readonly string $nameTh,
        public readonly string $nameEn,
        public readonly Money $price,
        /**
         * What a customer pays for this package now: $price plus VAT when VAT
         * is in effect and prices exclude it; otherwise it is the same as
         * $price. An order opened now asks for this amount, so show this one
         * as the price to pay.
         */
        public readonly Money $priceTotal,
        public readonly Money $pricePerCredit,
        /**
         * The same price per credit, in baht, as text with three decimals,
         * rounded half up at a tenth of a satang. Two packages that tie at a
         * whole satang differ here (0.175 against 0.167). Show it as sent
         * rather than working it out from $price and $credits. Like
         * $pricePerCredit it divides $price, not $priceTotal, so it excludes
         * VAT whenever $price does.
         */
        public readonly string $pricePerCreditBaht,
        public readonly int $credits,
        public readonly int $validityDays,
        public readonly bool $forSale,
        public readonly int $sort,
    ) {}

    /**
     * @param array<string, mixed> $d
     */
    public static function fromArray(array $d): self
    {
        return new self(
            Arr::str($d, 'code'),
            Arr::str($d, 'name_th'),
            Arr::str($d, 'name_en'),
            Money::fromArray(Arr::obj($d, 'price')),
            Money::fromArray(Arr::obj($d, 'price_total')),
            Money::fromArray(Arr::obj($d, 'price_per_credit')),
            Arr::str($d, 'price_per_credit_baht'),
            Arr::int($d, 'credits'),
            Arr::int($d, 'validity_days'),
            Arr::bool($d, 'for_sale'),
            Arr::int($d, 'sort'),
        );
    }
}
