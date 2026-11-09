<?php

declare(strict_types=1);

namespace Checkslip\Enum;

/**
 * Why the credit balance changed.
 */
enum CreditReason: string
{
    case TesterGrant = 'TESTER_GRANT';
    case Purchase = 'PURCHASE';
    case SlipCharge = 'SLIP_CHARGE';
    case SlipFree = 'SLIP_FREE';
    case Expire = 'EXPIRE';
    case StaffAdjust = 'STAFF_ADJUST';
    case AccountDeleted = 'ACCOUNT_DELETED';
    case DevGrant = 'DEV_GRANT';
}
