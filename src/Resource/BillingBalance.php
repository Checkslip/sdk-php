<?php

declare(strict_types=1);

namespace Checkslip\Resource;

use Checkslip\Model\Balance;

/**
 * The credit left on this account.
 */
final class BillingBalance extends AbstractResource
{
    public function get(): Balance
    {
        return Balance::fromArray($this->fetch('/api/billing/balance'));
    }
}
