<?php

declare(strict_types=1);

namespace Checkslip\Webhook;

use Checkslip\Enum\EventType;
use Checkslip\Internal\Arr;
use Checkslip\Internal\Enums;

/**
 * A webhook message that passed {@see Webhook::verify()}.
 */
final class WebhookEvent
{
    /**
     * Fields the SDK drops from $data because the API is retiring them.
     */
    private const RETIRED_FIELDS = ['checked_elsewhere', 'latency_ms', 'upstream_ms', 'trace_id', 'build'];

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        /** The webhook-id header. The same message sent again keeps it, so deduplicate on it. */
        public readonly ?string $id,
        public readonly EventType|string $type,
        public readonly \DateTimeImmutable $timestamp,
        public readonly array $data,
    ) {}

    /**
     * @param array<string, mixed> $d
     */
    public static function fromArray(array $d, ?string $id = null): self
    {
        $data = Arr::obj($d, 'data');
        foreach (self::RETIRED_FIELDS as $f) {
            unset($data[$f]);
        }

        return new self($id, Enums::of(EventType::class, Arr::str($d, 'type')), Arr::date($d, 'timestamp'), $data);
    }

    /**
     * The typed data of a slip.checked message, or null for any other type.
     */
    public function slipChecked(): ?SlipCheckedData
    {
        return $this->type === EventType::SlipChecked ? SlipCheckedData::fromArray($this->data) : null;
    }
}
