<?php

declare(strict_types=1);

namespace Checkslip\Model;

use Checkslip\Internal\Arr;

/**
 * An amount in satang (1 baht = 100 satang), so no float rounding ever touches money.
 */
final class Money
{
    public function __construct(
        public readonly int $satang,
        public readonly string $currency = 'THB',
    ) {}

    /**
     * @param array<string, mixed> $d
     */
    public static function fromArray(array $d): self
    {
        return new self(Arr::int($d, 'satang'), Arr::str($d, 'currency'));
    }

    /**
     * The amount in baht as a decimal string, for display: 5000 satang gives "50.00".
     */
    public function toBaht(): string
    {
        $abs = abs($this->satang);

        return \sprintf('%s%d.%02d', $this->satang < 0 ? '-' : '', intdiv($abs, 100), $abs % 100);
    }

    public function equals(self $other): bool
    {
        return $this->satang === $other->satang && $this->currency === $other->currency;
    }
}
