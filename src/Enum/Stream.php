<?php

declare(strict_types=1);

namespace Checkslip\Enum;

/**
 * Audit record streams. Evidence exports carry AUDIT_EVENTS only.
 */
enum Stream: string
{
    case AuditEvents = 'AUDIT_EVENTS';
}
