<?php

declare(strict_types=1);

namespace Checkslip\Resource;

use Checkslip\Enum\CreditReason;
use Checkslip\Enum\OrderStatus;
use Checkslip\Internal\Query;
use Checkslip\Model\Balance;
use Checkslip\Model\BillingInfo;
use Checkslip\Model\CreditEntry;
use Checkslip\Model\Order;
use Checkslip\Model\PackageList;
use Checkslip\Model\Receipt;
use Checkslip\Page;

/**
 * Credits, orders and receipts.
 */
final class Billing extends AbstractResource
{
    public function balance(): Balance
    {
        return Balance::fromArray($this->fetch('/api/billing/balance'));
    }

    public function packages(): PackageList
    {
        return PackageList::fromArray($this->fetch('/api/billing/packages'));
    }

    /**
     * Every change to the credit balance, newest first.
     *
     * @param list<CreditReason|string>|null $reason
     *
     * @return Page<CreditEntry>
     */
    public function ledger(
        ?array $reason = null,
        \DateTimeInterface|string|null $from = null,
        \DateTimeInterface|string|null $to = null,
        ?int $limit = null,
        ?string $cursor = null,
    ): Page {
        return $this->page('/api/billing/ledger', [
            'reason' => Query::csv($reason),
            'from' => Query::dateTime($from),
            'to' => Query::dateTime($to),
            'limit' => $limit,
            'cursor' => $cursor,
        ], CreditEntry::fromArray(...));
    }

    /**
     * @return Page<Order>
     */
    public function orders(OrderStatus|string|null $status = null, ?int $limit = null, ?string $cursor = null): Page
    {
        return $this->page('/api/billing/orders', [
            'status' => $status instanceof OrderStatus ? $status->value : $status,
            'limit' => $limit,
            'cursor' => $cursor,
        ], Order::fromArray(...));
    }

    public function order(string $orderId): Order
    {
        return Order::fromArray($this->fetch(self::path('/api/billing/orders/%s', $orderId)));
    }

    /**
     * Asks the payment provider for the order's result now instead of waiting for its notice.
     */
    public function syncOrder(string $orderId): Order
    {
        return Order::fromArray($this->transport->json('POST', self::path('/api/billing/orders/%s/sync', $orderId))[0]);
    }

    /**
     * @return Page<Receipt>
     */
    public function receipts(?int $limit = null, ?string $cursor = null): Page
    {
        return $this->page('/api/billing/receipts', ['limit' => $limit, 'cursor' => $cursor], Receipt::fromArray(...));
    }

    public function receipt(string $receiptId): Receipt
    {
        return Receipt::fromArray($this->fetch(self::path('/api/billing/receipts/%s', $receiptId)));
    }

    public function billingInfo(): BillingInfo
    {
        return BillingInfo::fromArray($this->fetch('/api/billing/billing-info'));
    }

    /**
     * Saves the buyer details for future receipts. Receipts already issued keep theirs.
     */
    public function updateBillingInfo(BillingInfo $info): BillingInfo
    {
        return BillingInfo::fromArray($this->transport->json('PUT', '/api/billing/billing-info', [], $info->toArray())[0]);
    }
}
