<?php

declare(strict_types=1);

namespace Checkslip\Resource;

use Checkslip\Model\PackageList;

/**
 * The credit packages on sale.
 */
final class BillingPackages extends AbstractResource
{
    /**
     * The answer is kept whole rather than unwrapped to its items, because it
     * also says whether prices include VAT and whether checkout is open.
     */
    public function list(): PackageList
    {
        return PackageList::fromArray($this->fetch('/api/billing/packages'));
    }
}
