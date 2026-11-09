<?php

declare(strict_types=1);

namespace Checkslip\Enum;

/**
 * Webhook message types.
 */
enum EventType: string
{
    case SlipChecked = 'slip.checked';
    case WebhookTest = 'webhook.test';
}
