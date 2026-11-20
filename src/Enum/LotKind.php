<?php

declare(strict_types=1);

namespace Checkslip\Enum;

/**
 * The kind of a credit lot. New kinds may be added: show an unknown kind as
 * credits with their expiry.
 */
enum LotKind: string
{
    /**
     * Every credit that did not come from a campaign (bought, trial, adjusted
     * by our team) as one lot per account; a purchase sets its expiry to at
     * least one year from the payment and never shortens it.
     */
    case Paid = 'PAID';
    /**
     * Credits a campaign gave, which expire on that campaign's own date and
     * are not moved by a purchase.
     */
    case Campaign = 'CAMPAIGN';
}
