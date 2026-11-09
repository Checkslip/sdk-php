<?php

declare(strict_types=1);

namespace Checkslip\Model;

use Checkslip\Enum\OrderStatus;
use Checkslip\Enum\PaymentMethod;
use Checkslip\Internal\Arr;
use Checkslip\Internal\Enums;

/**
 * A credit purchase.
 */
final class Order
{
    public function __construct(
        public readonly string $id,
        public readonly OrderStatus|string $status,
        public readonly string $packageCode,
        public readonly string $packageNameTh,
        public readonly int $credits,
        public readonly Money $amount,
        public readonly PaymentMethod|string|null $paymentMethod,
        public readonly ?string $receiptId,
        public readonly \DateTimeImmutable $createdAt,
        public readonly ?\DateTimeImmutable $paidAt,
        public readonly ?\DateTimeImmutable $cancelledAt,
        /** True when the payment page closed but the money has not arrived yet. */
        public readonly bool $confirming,
        public readonly ?string $reviewReason,
    ) {}

    /**
     * @param array<string, mixed> $d
     */
    public static function fromArray(array $d): self
    {
        return new self(
            Arr::str($d, 'id'),
            Enums::of(OrderStatus::class, Arr::str($d, 'status')),
            Arr::str($d, 'package_code'),
            Arr::str($d, 'package_name_th'),
            Arr::int($d, 'credits'),
            Money::fromArray(Arr::obj($d, 'amount')),
            Enums::ofNullable(PaymentMethod::class, Arr::strOrNull($d, 'payment_method')),
            Arr::strOrNull($d, 'receipt_id'),
            Arr::date($d, 'created_at'),
            Arr::dateOrNull($d, 'paid_at'),
            Arr::dateOrNull($d, 'cancelled_at'),
            Arr::boolOrNull($d, 'confirming') ?? false,
            Arr::strOrNull($d, 'review_reason'),
        );
    }
}
