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
    /** No longer sent. The API sends OTHER in its place. */
    case NoCredit = 'NO_CREDIT';
    /** No longer sent either, and replaced by OTHER the same way. */
    case HoldExpired = 'HOLD_EXPIRED';
    case SystemError = 'SYSTEM_ERROR';
    /** The lookup was not charged and no reason was given. */
    case Other = 'OTHER';
}
