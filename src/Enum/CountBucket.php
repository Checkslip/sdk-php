<?php

declare(strict_types=1);

namespace Checkslip\Enum;

/**
 * Bucket size for lookup counts over time.
 */
enum CountBucket: string
{
    case Minute = 'MINUTE';
    case FiveMinute = 'FIVE_MINUTE';
    case FifteenMinute = 'FIFTEEN_MINUTE';
    case Hour = 'HOUR';
    case ThreeHour = 'THREE_HOUR';
    case SixHour = 'SIX_HOUR';
    case TwelveHour = 'TWELVE_HOUR';
    case Day = 'DAY';
}
