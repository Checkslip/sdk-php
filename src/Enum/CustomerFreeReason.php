<?php

declare(strict_types=1);

namespace Checkslip\Enum;

/**
 * A reason a customer can filter by.
 *
 * These are the nine reasons the API accepts in a free_reason filter, which
 * are the ones a customer can receive. FreeReason, the type the API sends
 * back, stays wider: it has to carry a value this version does not know.
 *
 * The free_reason filter on Slips::counts() is not this type and is not
 * narrowed, because counts ignore it: the API answers which filters it
 * applied in SlipCounts::$filtersApplied and lists the rest in
 * $filtersIgnored, where free_reason appears. Leave it alone rather than
 * making the two look alike.
 */
enum CustomerFreeReason: string
{
    case NotFound = 'NOT_FOUND';
    case Duplicate = 'DUPLICATE';
    case Unavailable = 'UNAVAILABLE';
    case InvalidQr = 'INVALID_QR';
    case InvalidFile = 'INVALID_FILE';
    case QrTampered = 'QR_TAMPERED';
    case TooOld = 'TOO_OLD';
    case SystemError = 'SYSTEM_ERROR';
    case Other = 'OTHER';
}
