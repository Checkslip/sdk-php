<?php

declare(strict_types=1);

namespace Checkslip\Resource;

use Checkslip\Internal\Arr;
use Checkslip\Model\EventTypeInfo;

/**
 * The event types Checkslip can send.
 */
final class WebhookEventTypes extends AbstractResource
{
    /**
     * Every message type Checkslip can send, such as slip.checked.
     *
     * The API wraps them in an object with nothing else in it, so the SDK
     * hands back the event types themselves.
     *
     * @return list<EventTypeInfo>
     */
    public function list(): array
    {
        return array_map(EventTypeInfo::fromArray(...), Arr::objects($this->fetch('/api/webhooks/event-types'), 'items'));
    }
}
