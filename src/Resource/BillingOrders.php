<?php

declare(strict_types=1);

namespace Checkslip\Resource;

use Checkslip\Enum\OrderStatus;
use Checkslip\Model\Order;
use Checkslip\Page;

/**
 * Orders for credit packages.
 */
final class BillingOrders extends AbstractResource
{
    /**
     * @return Page<Order>
     */
    public function list(OrderStatus|string|null $status = null, ?int $limit = null, ?string $cursor = null): Page
    {
        return $this->page('/api/billing/orders', [
            'status' => $status instanceof OrderStatus ? $status->value : $status,
            'limit' => $limit,
            'cursor' => $cursor,
        ], Order::fromArray(...));
    }

    /**
     * Every order across all pages.
     *
     * @return \Generator<int, Order>
     */
    public function listAll(OrderStatus|string|null $status = null, ?int $limit = null, ?string $cursor = null): \Generator
    {
        return $this->list($status, $limit, $cursor)->autoPaging();
    }

    public function get(string $orderId): Order
    {
        return Order::fromArray($this->fetch(self::path('/api/billing/orders/%s', $orderId)));
    }

    /**
     * Asks the payment provider for the order's result now instead of waiting for its notice.
     */
    public function sync(string $orderId): Order
    {
        return Order::fromArray($this->transport->json('POST', self::path('/api/billing/orders/%s/sync', $orderId))[0]);
    }
}
