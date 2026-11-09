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
        public readonly Money $pricePerCredit,
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
            Money::fromArray(Arr::obj($d, 'price_per_credit')),
            Arr::int($d, 'credits'),
            Arr::int($d, 'validity_days'),
            Arr::bool($d, 'for_sale'),
            Arr::int($d, 'sort'),
        );
    }
}
