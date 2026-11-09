<?php

declare(strict_types=1);

namespace Checkslip\Resource;

use Checkslip\Enum\DeliveryStatus;
use Checkslip\Enum\EventType;
use Checkslip\Internal\Query;
use Checkslip\Model\Delivery;
use Checkslip\Model\DeliveryDetail;
use Checkslip\Page;

final class WebhookDeliveries extends AbstractResource
{
    /**
     * @return Page<Delivery>
     */
    public function list(
        ?string $endpointId = null,
        DeliveryStatus|string|null $status = null,
        EventType|string|null $eventType = null,
        \DateTimeInterface|string|null $since = null,
        \DateTimeInterface|string|null $until = null,
        ?int $limit = null,
        ?string $cursor = null,
    ): Page {
        return $this->page('/api/webhooks/deliveries', [
            'endpoint_id' => $endpointId,
            'status' => $status instanceof DeliveryStatus ? $status->value : $status,
            'event_type' => $eventType instanceof EventType ? $eventType->value : $eventType,
            'since' => Query::dateTime($since),
            'until' => Query::dateTime($until),
            'limit' => $limit,
            'cursor' => $cursor,
        ], Delivery::fromArray(...));
    }

    /**
     * One delivery with its payload and every attempt.
     */
    public function get(string $deliveryId): DeliveryDetail
    {
        return DeliveryDetail::fromArray($this->fetch(self::path('/api/webhooks/deliveries/%s', $deliveryId)));
    }

    /**
     * Sends the same payload again with the original webhook-id. Your handler
     * receives it a second time, so deduplicate on webhook-id.
     */
    public function redeliver(string $deliveryId, ?string $idempotencyKey = null): Delivery
    {
        return Delivery::fromArray($this->transport->json(
            'POST',
            self::path('/api/webhooks/deliveries/%s/redeliver', $deliveryId),
            [],
            null,
            self::idempotency($idempotencyKey),
        )[0]);
    }
}
