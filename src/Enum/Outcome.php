<?php

declare(strict_types=1);

namespace Checkslip\Enum;

/**
 * What the lookup found. Only FOUND uses a credit.
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
