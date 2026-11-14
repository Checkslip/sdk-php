<?php

declare(strict_types=1);

namespace Checkslip\Model;

use Checkslip\Internal\Arr;

final class PackageList
{
    /**
     * @param list<Package> $items
     */
    public function __construct(
        /** False while checkout is closed; packages still list but cannot be bought. */
        public readonly bool $checkoutOpen,
        public readonly bool $pricesIncludeVat,
        /**
         * The VAT rate in force now, in basis points: 700 is 7%, and 0 means
         * no VAT applies anywhere yet. Read it to find out whether VAT is in
         * effect.
         */
        public readonly int $vatRateBp,
        public readonly array $items,
    ) {}

    /**
     * @param array<string, mixed> $d
     */
    public static function fromArray(array $d): self
    {
        return new self(
            Arr::bool($d, 'checkout_open'),
            Arr::bool($d, 'prices_include_vat'),
            Arr::int($d, 'vat_rate_bp'),
            array_map(Package::fromArray(...), Arr::objects($d, 'items')),
        );
    }
}
