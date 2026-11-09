<?php

declare(strict_types=1);

namespace Checkslip\Enum;

/**
 * Where a request came from.
 */
enum Channel: string
{
    case Api = 'API';
    case Dashboard = 'DASHBOARD';
    case Assistant = 'ASSISTANT';
    case Staff = 'STAFF';
    case System = 'SYSTEM';
}
