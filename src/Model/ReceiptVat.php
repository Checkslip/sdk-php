<?php

declare(strict_types=1);

namespace Checkslip\Model;

use Checkslip\Internal\Arr;

final class ReceiptVat
{
    public function __construct(
        /** Rate in basis points: 700 means 7%. */
        public readonly int $rateBp,
        public readonly Money $base,
        public readonly Money $amount,
    ) {}

    /**
     * @param array<string, mixed> $d
     */
    public static function fromArray(array $d): self
    {
        return new self(Arr::int($d, 'rate_bp'), Money::fromArray(Arr::obj($d, 'base')), Money::fromArray(Arr::obj($d, 'amount')));
    }
}
