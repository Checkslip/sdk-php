<?php

declare(strict_types=1);

namespace Checkslip\Enum;

/**
 * State of a slip export or an evidence file.
 */
enum ExportState: string
{
    case Queued = 'QUEUED';
    case Running = 'RUNNING';
    case Ready = 'READY';
    case Failed = 'FAILED';
    case Expired = 'EXPIRED';
}
