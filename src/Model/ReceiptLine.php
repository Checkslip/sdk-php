<?php

declare(strict_types=1);

namespace Checkslip\Model;

use Checkslip\Internal\Arr;

final class ReceiptLine
{
    public function __construct(
        public readonly string $description,
        public readonly int $quantity,
        public readonly Money $amount,
    ) {}

    /**
     * @param array<string, mixed> $d
     */
    public static function fromArray(array $d): self
    {
        return new self(Arr::str($d, 'description'), Arr::int($d, 'quantity'), Money::fromArray(Arr::obj($d, 'amount')));
    }
}
