<?php

declare(strict_types=1);

namespace Checkslip\Enum;

/**
 * Why a lookup used no credit.
 */
enum FreeReason: string
{
    case NotFound = 'NOT_FOUND';
    case Duplicate = 'DUPLICATE';
    case Pending = 'PENDING';
    case Unavailable = 'UNAVAILABLE';
    case InvalidQr = 'INVALID_QR';
    case InvalidFile = 'INVALID_FILE';
    case QrTampered = 'QR_TAMPERED';
    case TooOld = 'TOO_OLD';
    case NoCredit = 'NO_CREDIT';
    case HoldExpired = 'HOLD_EXPIRED';
    case SystemError = 'SYSTEM_ERROR';
}
