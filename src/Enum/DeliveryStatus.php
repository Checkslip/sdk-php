<?php

declare(strict_types=1);

namespace Checkslip\Enum;

/**
 * State of one webhook delivery.
 */
enum DeliveryStatus: string
{
    case Pending = 'PENDING';
    case Retrying = 'RETRYING';
    case Succeeded = 'SUCCEEDED';
    case Failed = 'FAILED';
    case Cancelled = 'CANCELLED';
}
