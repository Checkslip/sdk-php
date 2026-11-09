<?php

declare(strict_types=1);

namespace Checkslip\Resource;

use Checkslip\Model\BillingInfo;

/**
 * The buyer details printed on receipts.
 */
final class BillingDetails extends AbstractResource
{
    public function get(): BillingInfo
    {
        return BillingInfo::fromArray($this->fetch('/api/billing/billing-info'));
    }

    /**
     * Saves the buyer details for future receipts. Receipts already issued keep theirs.
     */
    public function update(BillingInfo $info): BillingInfo
    {
        return BillingInfo::fromArray($this->transport->json('PUT', '/api/billing/billing-info', [], $info->toArray())[0]);
    }
}
