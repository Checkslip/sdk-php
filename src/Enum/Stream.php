<?php

declare(strict_types=1);

namespace Checkslip\Enum;

/**
 * Audit record streams.
 */
enum Stream: string
{
    case ApiRequests = 'API_REQUESTS';
    case AuditEvents = 'AUDIT_EVENTS';
}
