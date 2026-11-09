<?php

declare(strict_types=1);

namespace Checkslip\Enum;

/**
 * Why a webhook endpoint is off: MANUAL (you turned it off), GONE (your system answered 410) or FAILING (every send failed for 5 days).
 */
enum EndpointDisabledReason: string
{
    case Manual = 'MANUAL';
    case Gone = 'GONE';
    case Failing = 'FAILING';
}
