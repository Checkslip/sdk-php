<?php

declare(strict_types=1);

namespace Checkslip\Resource;

use Checkslip\Model\Receipt;
use Checkslip\Page;

/**
 * Receipts for paid orders.
 */
final class BillingReceipts extends AbstractResource
{
    /**
     * @return Page<Receipt>
     */
    public function list(?int $limit = null, ?string $cursor = null): Page
    {
        return $this->page('/api/billing/receipts', ['limit' => $limit, 'cursor' => $cursor], Receipt::fromArray(...));
    }

    /**
     * Every receipt across all pages.
     *
     * @return \Generator<int, Receipt>
     */
    public function listAll(?int $limit = null, ?string $cursor = null): \Generator
    {
        return $this->list($limit, $cursor)->autoPaging();
    }

    public function get(string $receiptId): Receipt
    {
        return Receipt::fromArray($this->fetch(self::path('/api/billing/receipts/%s', $receiptId)));
    }
}
