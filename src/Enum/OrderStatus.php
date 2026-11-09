<?php

declare(strict_types=1);

namespace Checkslip\Enum;

/**
 * State of a credit order.
 */
enum OrderStatus: string
{
    case Pending = 'PENDING';
    case Paid = 'PAID';
    case Cancelled = 'CANCELLED';
    case Flagged = 'FLAGGED';
}
