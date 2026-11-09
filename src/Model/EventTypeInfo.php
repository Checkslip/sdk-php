<?php

declare(strict_types=1);

namespace Checkslip\Model;

use Checkslip\Enum\EventType;
use Checkslip\Internal\Arr;
use Checkslip\Internal\Enums;

final class EventTypeInfo
{
    public function __construct(
        public readonly EventType|string $type,
        public readonly string $description,
        /** False for types you cannot subscribe to, such as webhook.test. */
        public readonly bool $subscribable,
    ) {}

    /**
     * @param array<string, mixed> $d
     */
    public static function fromArray(array $d): self
    {
        return new self(Enums::of(EventType::class, Arr::str($d, 'type')), Arr::str($d, 'description'), Arr::bool($d, 'subscribable'));
    }
}
