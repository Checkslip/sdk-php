<?php

declare(strict_types=1);

namespace Checkslip\Model;

use Checkslip\Enum\EndpointDisabledReason;
use Checkslip\Enum\EventType;
use Checkslip\Internal\Arr;
use Checkslip\Internal\Enums;

/**
 * A URL of your system that receives webhook messages.
 */
final class Endpoint
{
    /**
     * @param list<EventType|string> $eventTypes
     */
    public function __construct(
        public readonly string $id,
        public readonly string $url,
        public readonly ?string $description,
        public readonly array $eventTypes,
        public readonly bool $enabled,
        public readonly EndpointDisabledReason|string|null $disabledReason,
        /** Last 4 characters of the signing secret, to tell secrets apart. */
        public readonly string $secretHint,
        /** While a rotation is in progress, the old secret works until this time. */
        public readonly ?\DateTimeImmutable $previousSecretExpiresAt,
        public readonly \DateTimeImmutable $createdAt,
        public readonly \DateTimeImmutable $updatedAt,
    ) {}

    /**
     * @param array<string, mixed> $d
     */
    public static function fromArray(array $d): self
    {
        return new self(
            Arr::str($d, 'id'),
            Arr::str($d, 'url'),
            Arr::strOrNull($d, 'description'),
            Enums::list(EventType::class, Arr::strings($d, 'event_types')),
            Arr::bool($d, 'enabled'),
            Enums::ofNullable(EndpointDisabledReason::class, Arr::strOrNull($d, 'disabled_reason')),
            Arr::str($d, 'secret_hint'),
            Arr::dateOrNull($d, 'previous_secret_expires_at'),
            Arr::date($d, 'created_at'),
            Arr::date($d, 'updated_at'),
        );
    }
}
