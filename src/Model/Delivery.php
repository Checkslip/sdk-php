<?php

declare(strict_types=1);

namespace Checkslip\Model;

use Checkslip\Enum\DeliveryStatus;
use Checkslip\Enum\EventType;
use Checkslip\Internal\Arr;
use Checkslip\Internal\Enums;

/**
 * One message sent (or being sent) to one endpoint.
 */
final class Delivery
{
    public function __construct(
        public readonly string $id,
        /** The webhook-id header of the message. Deduplicate on this. */
        public readonly string $messageId,
        public readonly string $endpointId,
        public readonly EventType|string $eventType,
        public readonly DeliveryStatus|string $status,
        public readonly int $attemptCount,
        public readonly ?\DateTimeImmutable $nextAttemptAt,
        public readonly ?\DateTimeImmutable $lastAttemptAt,
        public readonly ?int $lastHttpStatus,
        public readonly \DateTimeImmutable $createdAt,
        /**
         * The id of the lookup whose result this message carries, the
         * same as data.request_id in the slip.checked message. Use it
         * with GET /api/slips/{request_id} to read the same result, for
         * example when the message cannot be resent. It is null for a
         * test message (webhook.test), which has no result to read, so
         * do not call GET with it. It is not the X-Request-Id of the
         * request that reads this delivery.
         */
        public readonly ?string $requestId = null,
    ) {}

    /**
     * @param array<string, mixed> $d
     */
    public static function fromArray(array $d): self
    {
        return new self(
            Arr::str($d, 'id'),
            Arr::str($d, 'message_id'),
            Arr::str($d, 'endpoint_id'),
            Enums::of(EventType::class, Arr::str($d, 'event_type')),
            Enums::of(DeliveryStatus::class, Arr::str($d, 'status')),
            Arr::int($d, 'attempt_count'),
            Arr::dateOrNull($d, 'next_attempt_at'),
            Arr::dateOrNull($d, 'last_attempt_at'),
            Arr::intOrNull($d, 'last_http_status'),
            Arr::date($d, 'created_at'),
            Arr::strOrNull($d, 'request_id'),
        );
    }
}
