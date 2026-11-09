<?php

declare(strict_types=1);

namespace Checkslip\Model;

use Checkslip\Enum\BuyerType;
use Checkslip\Internal\Arr;
use Checkslip\Internal\Enums;

/**
 * Buyer details printed on receipts. Also what you send to update them.
 */
final class BillingInfo
{
    public function __construct(
        public readonly BuyerType|string|null $buyerType = null,
        public readonly ?string $name = null,
        /** 13 digits. */
        public readonly ?string $taxId = null,
        public readonly ?string $branch = null,
        public readonly ?string $address = null,
        public readonly ?string $email = null,
    ) {}

    /**
     * @param array<string, mixed> $d
     */
    public static function fromArray(array $d): self
    {
        return new self(
            Enums::ofNullable(BuyerType::class, Arr::strOrNull($d, 'buyer_type')),
            Arr::strOrNull($d, 'name'),
            Arr::strOrNull($d, 'tax_id'),
            Arr::strOrNull($d, 'branch'),
            Arr::strOrNull($d, 'address'),
            Arr::strOrNull($d, 'email'),
        );
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return array_filter([
            'buyer_type' => $this->buyerType !== null ? Enums::value($this->buyerType) : null,
            'name' => $this->name,
            'tax_id' => $this->taxId,
            'branch' => $this->branch,
            'address' => $this->address,
            'email' => $this->email,
        ], static fn (?string $v): bool => $v !== null);
    }
}
