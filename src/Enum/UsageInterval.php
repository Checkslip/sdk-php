<?php

declare(strict_types=1);

namespace Checkslip\Enum;

/**
 * Bucket size for credit usage.
 */
enum UsageInterval: string
{
    case Day = 'DAY';
    case Month = 'MONTH';
}
