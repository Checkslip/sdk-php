<?php

declare(strict_types=1);

namespace Checkslip\Enum;

/**
 * Who did something in the audit log.
 */
enum ActorType: string
{
    case User = 'USER';
    case ApiKey = 'API_KEY';
    case Assistant = 'ASSISTANT';
    case Staff = 'STAFF';
    case System = 'SYSTEM';
}
