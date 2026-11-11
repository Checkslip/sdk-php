<?php

declare(strict_types=1);

namespace Checkslip\Enum;

/**
 * Why a webhook endpoint is off.
 *
 * MANUAL means you turned it off, GONE that your system answered 410, and
 * FAILING that every send failed for 5 days in a row. STAFF means the
 * Checkslip team turned it off, and you cannot turn it back on yourself, so
 * open a case. The API can add a reason later, and an unknown one reaches you
 * as the raw string, so an exhaustive match needs a catch-all.
 */
enum EndpointDisabledReason: string
{
    case Manual = 'MANUAL';
    case Gone = 'GONE';
    case Failing = 'FAILING';
    /** The Checkslip team turned it off. */
    case Staff = 'STAFF';
}
