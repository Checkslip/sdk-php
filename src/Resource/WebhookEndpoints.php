<?php

declare(strict_types=1);

namespace Checkslip\Resource;

use Checkslip\Enum\EventType;
use Checkslip\Internal\Enums;
use Checkslip\Internal\Query;
use Checkslip\Model\Delivery;
use Checkslip\Model\Endpoint;
use Checkslip\Model\EndpointWithSecret;
use Checkslip\Model\RecoverResult;
use Checkslip\Model\SecretRotation;
use Checkslip\Page;

final class WebhookEndpoints extends AbstractResource
{
    /**
     * @return Page<Endpoint>
     */
    public function list(?int $limit = null, ?string $cursor = null): Page
    {
        return $this->page('/api/webhooks/endpoints', ['limit' => $limit, 'cursor' => $cursor], Endpoint::fromArray(...));
    }

    /**
     * Every matching item across all pages.
     *
     * @return \Generator<int, Endpoint>
     */
    public function listAll(?int $limit = null, ?string $cursor = null): \Generator
    {
        return $this->list($limit, $cursor)->autoPaging();
    }


    /**
     * Registers a URL of your system. The URL must be https and resolve only to
     * public addresses. The response holds the signing secret, shown only this once.
     *
     * Not retried on a 5xx or a network error (POST without an Idempotency-Key),
     * so a lost answer cannot create the endpoint twice. List the endpoints to check.
     *
     * @param list<EventType|string> $eventTypes
     */
    public function create(string $url, array $eventTypes = [EventType::SlipChecked], ?string $description = null): EndpointWithSecret
    {
        $body = ['url' => $url, 'event_types' => array_map(Enums::value(...), $eventTypes)];
        if ($description !== null) {
            $body['description'] = $description;
        }

        return EndpointWithSecret::fromArray($this->transport->json('POST', '/api/webhooks/endpoints', [], $body)[0]);
    }

    public function get(string $endpointId): Endpoint
    {
        return Endpoint::fromArray($this->fetch(self::path('/api/webhooks/endpoints/%s', $endpointId)));
    }

    /**
     * Changes only the fields you pass. Set $clearDescription to remove the description.
     *
     * Setting enabled to true fails with 409 ENDPOINT_DISABLED_BY_STAFF if the
     * Checkslip team turned the endpoint off. Reply to the email about it, or
     * open a case to have it turned on.
     *
     * @param list<EventType|string>|null $eventTypes
     */
    public function update(
        string $endpointId,
        ?string $url = null,
        ?string $description = null,
        ?array $eventTypes = null,
        ?bool $enabled = null,
        bool $clearDescription = false,
    ): Endpoint {
        $body = [];
        if ($url !== null) {
            $body['url'] = $url;
        }
        if ($clearDescription) {
            $body['description'] = null;
        } elseif ($description !== null) {
            $body['description'] = $description;
        }
        if ($eventTypes !== null) {
            $body['event_types'] = array_map(Enums::value(...), $eventTypes);
        }
        if ($enabled !== null) {
            $body['enabled'] = $enabled;
        }

        return Endpoint::fromArray($this->transport->json(
            'PATCH',
            self::path('/api/webhooks/endpoints/%s', $endpointId),
            [],
            $body,
            [],
            'application/merge-patch+json',
        )[0]);
    }

    public function delete(string $endpointId): void
    {
        $this->transport->send('DELETE', self::path('/api/webhooks/endpoints/%s', $endpointId));
    }

    /**
     * Issues a new signing secret. The old one keeps working for 24 hours, and
     * meanwhile each message is signed with both. You cannot rotate again in
     * that window (ROTATION_IN_PROGRESS).
     */
    public function rotateSecret(string $endpointId, ?string $idempotencyKey = null): SecretRotation
    {
        return SecretRotation::fromArray($this->transport->json(
            'POST',
            self::path('/api/webhooks/endpoints/%s/secret/rotate', $endpointId),
            [],
            null,
            self::idempotency($idempotencyKey),
        )[0]);
    }

    /**
     * Sends a webhook.test message to the endpoint.
     */
    public function sendTest(string $endpointId, ?string $idempotencyKey = null): Delivery
    {
        return Delivery::fromArray($this->transport->json(
            'POST',
            self::path('/api/webhooks/endpoints/%s/test', $endpointId),
            [],
            null,
            self::idempotency($idempotencyKey),
        )[0]);
    }

    /**
     * Resends every FAILED delivery created from $since up to (not including)
     * $until, for example after your system was down. At most 14 days, reaching
     * back at most 30 days. Each message keeps its original webhook-id.
     */
    public function recover(
        string $endpointId,
        \DateTimeInterface|string $since,
        \DateTimeInterface|string|null $until = null,
        ?string $idempotencyKey = null,
    ): RecoverResult {
        $body = ['since' => Query::dateTime($since)];
        if ($until !== null) {
            $body['until'] = Query::dateTime($until);
        }

        return RecoverResult::fromArray($this->transport->json(
            'POST',
            self::path('/api/webhooks/endpoints/%s/recover', $endpointId),
            [],
            $body,
            self::idempotency($idempotencyKey),
        )[0]);
    }
}
