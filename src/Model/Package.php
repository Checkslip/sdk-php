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
         * What a customer pays for this package now, including any VAT due; do
         * not add VAT again. It equals $price when PackageList::$vatRateBp is 0
         * or PackageList::$pricesIncludeVat is true. An order opened now asks for
         * this amount, so show this one as the price to pay.
         */
        public readonly Money $priceTotal,
        /**
         * The price of one credit: $price divided by $credits, rounded half up to
         * a whole satang, so it excludes VAT when PackageList::$pricesIncludeVat
         * is false. Read the amount to pay from $priceTotal; do not multiply this
         * by $credits to get it.
         */
        public readonly Money $pricePerCredit,
        /**
         * The price of one credit in baht with 3 decimals, from $price divided by
         * $credits, so it excludes VAT when PackageList::$pricesIncludeVat is
         * false. Two packages that tie at a whole satang differ here (0.175
         * against 0.167). It is display text: show it as sent instead of
         * computing it.
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
