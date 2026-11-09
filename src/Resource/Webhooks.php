<?php

declare(strict_types=1);

namespace Checkslip\Resource;

use Checkslip\Internal\Transport;
use Checkslip\Webhook\Webhook;
use Checkslip\Webhook\WebhookEvent;

/**
 * Webhook endpoints, deliveries and signature checks.
 */
final class Webhooks extends AbstractResource
{
    /** The URLs that receive messages. */
    public readonly WebhookEndpoints $endpoints;

    /** Messages queued for endpoints, with every send attempt. */
    public readonly WebhookDeliveries $deliveries;

    /** The event types Checkslip can send. */
    public readonly WebhookEventTypes $eventTypes;

    public function __construct(Transport $transport)
    {
        parent::__construct($transport);
        $this->endpoints = new WebhookEndpoints($transport);
        $this->deliveries = new WebhookDeliveries($transport);
        $this->eventTypes = new WebhookEventTypes($transport);
    }

    /**
     * Checks the signature of a message your server received and returns the
     * parsed event. It sends nothing, so it works on a client whose API key is
     * never used. During a secret rotation pass both secrets.
     *
     * {@see \Checkslip\Webhook\Webhook} does the same without a client.
     *
     * @param string|list<string> $secret The endpoint's signing secret, or both during a rotation.
     * @param array<string, string|list<string>> $headers The request headers.
     */
    public function verify(string $payload, array $headers, string|array $secret): WebhookEvent
    {
        return (new Webhook($secret))->verify($payload, $headers);
    }
}
