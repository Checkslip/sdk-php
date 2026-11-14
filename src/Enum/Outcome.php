<?php

declare(strict_types=1);

namespace Checkslip\Enum;

/**
 * What the lookup found. Only FOUND uses a credit.
 *
 * A lookup returns FOUND, NOT_FOUND or DUPLICATE; the other values appear in
 * the lookup history and in webhooks for lookups the API answered with an
 * error.
 */
enum Outcome: string
{
    case Found = 'FOUND';
    case NotFound = 'NOT_FOUND';
    case Duplicate = 'DUPLICATE';
    case Pending = 'PENDING';
    case Unavailable = 'UNAVAILABLE';
    case InvalidQr = 'INVALID_QR';
    case InvalidFile = 'INVALID_FILE';
    case QrTampered = 'QR_TAMPERED';
    case TooOld = 'TOO_OLD';
}
