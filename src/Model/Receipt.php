<?php

declare(strict_types=1);

namespace Checkslip\Model;

use Checkslip\Enum\PaymentMethod;
use Checkslip\Internal\Arr;
use Checkslip\Internal\Enums;

final class Receipt
{
    /**
     * @param array{name: string, tax_id?: string, address?: string, branch?: string} $seller
     * @param list<ReceiptLine> $lines
     */
    public function __construct(
        public readonly string $id,
        public readonly string $receiptNo,
        public readonly string $orderId,
        public readonly \DateTimeImmutable $issuedAt,
        /** False while the seller's tax details are not complete yet. */
        public readonly bool $sellerReady,
        public readonly array $seller,
        public readonly BillingInfo $buyer,
        public readonly array $lines,
        public readonly ?ReceiptVat $vat,
        public readonly Money $total,
        public readonly PaymentMethod|string $paymentMethod,
        /**
         * The order code (ORD-XXXXX-XXXXX) of this receipt's order, the same as
         * Order::$code, for referring to the order. The receipt's legal number
         * is $receiptNo; never use this code in its place. Every receipt comes
         * from an order and every order has a code, so it should always be set;
         * null means the order's code could not be read at that moment.
         */
        public readonly ?string $orderCode = null,
    ) {}

    /**
     * @param array<string, mixed> $d
     */
    public static function fromArray(array $d): self
    {
        $s = Arr::obj($d, 'seller');
        $seller = ['name' => Arr::str($s, 'name')];
        foreach (['tax_id', 'address', 'branch'] as $k) {
            $v = Arr::strOrNull($s, $k);
            if ($v !== null) {
                $seller[$k] = $v;
            }
        }
        $vat = Arr::objOrNull($d, 'vat');

        return new self(
            Arr::str($d, 'id'),
            Arr::str($d, 'receipt_no'),
            Arr::str($d, 'order_id'),
            Arr::date($d, 'issued_at'),
            Arr::bool($d, 'seller_ready'),
            $seller,
            BillingInfo::fromArray(Arr::obj($d, 'buyer')),
            array_map(ReceiptLine::fromArray(...), Arr::objects($d, 'lines')),
            $vat !== null ? ReceiptVat::fromArray($vat) : null,
            Money::fromArray(Arr::obj($d, 'total')),
            Enums::of(PaymentMethod::class, Arr::str($d, 'payment_method')),
            Arr::strOrNull($d, 'order_code'),
        );
    }
}
