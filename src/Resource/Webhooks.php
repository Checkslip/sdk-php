<?php

declare(strict_types=1);

namespace Checkslip\Resource;

use Checkslip\Internal\Arr;
use Checkslip\Internal\Transport;
use Checkslip\Model\EventTypeInfo;

/**
 * Webhook endpoints and deliveries. To check incoming messages, use
 * {@see \Checkslip\Webhook\Webhook}.
 */
final class Webhooks extends AbstractResource
{
    public readonly WebhookEndpoints $endpoints;

    public readonly WebhookDeliveries $deliveries;

    public function __construct(Transport $transport)
    {
        parent::__construct($transport);
        $this->endpoints = new WebhookEndpoints($transport);
        $this->deliveries = new WebhookDeliveries($transport);
    }

    /**
     * Every message type Checkslip can send, such as slip.checked.
     *
     * @return list<EventTypeInfo>
     */
    public function eventTypes(): array
    {
        return array_map(EventTypeInfo::fromArray(...), Arr::objects($this->fetch('/api/webhooks/event-types'), 'items'));
    }
}
