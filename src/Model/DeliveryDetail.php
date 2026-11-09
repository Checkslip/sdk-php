<?php

declare(strict_types=1);

namespace Checkslip\Model;

use Checkslip\Internal\Arr;
use Checkslip\Webhook\WebhookEvent;

/**
 * A delivery with its payload and every attempt.
 */
final class DeliveryDetail
{
    /**
     * @param list<DeliveryAttempt> $attempts
     */
    public function __construct(
        public readonly Delivery $delivery,
        /** The message that was sent. Null once the payload has expired. */
        public readonly ?WebhookEvent $payload,
        public readonly \DateTimeImmutable $payloadExpiresAt,
        public readonly array $attempts,
    ) {}

    /**
     * @param array<string, mixed> $d
     */
    public static function fromArray(array $d): self
    {
        $payload = Arr::objOrNull($d, 'payload');

        return new self(
            Delivery::fromArray($d),
            $payload !== null ? WebhookEvent::fromArray($payload, Arr::str($d, 'message_id')) : null,
            Arr::date($d, 'payload_expires_at'),
            array_map(DeliveryAttempt::fromArray(...), Arr::objects($d, 'attempts')),
        );
    }
}
