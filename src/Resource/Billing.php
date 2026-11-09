<?php

declare(strict_types=1);

namespace Checkslip\Resource;

use Checkslip\Internal\Transport;

/**
 * Credits, orders, receipts and the buyer details printed on receipts.
 */
final class Billing extends AbstractResource
{
    /** The credit left on this account. */
    public readonly BillingBalance $balance;

    /** The buyer details printed on receipts. */
    public readonly BillingDetails $info;

    /** The credit packages on sale. */
    public readonly BillingPackages $packages;

    /** Every top-up, charge and expiry. */
    public readonly BillingLedger $ledger;

    /** Orders for credit packages. */
    public readonly BillingOrders $orders;

    /** Receipts for paid orders. */
    public readonly BillingReceipts $receipts;

    public function __construct(Transport $transport)
    {
        parent::__construct($transport);
        $this->balance = new BillingBalance($transport);
        $this->info = new BillingDetails($transport);
        $this->packages = new BillingPackages($transport);
        $this->ledger = new BillingLedger($transport);
        $this->orders = new BillingOrders($transport);
        $this->receipts = new BillingReceipts($transport);
    }
}
