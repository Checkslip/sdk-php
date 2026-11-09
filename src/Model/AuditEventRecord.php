<?php

declare(strict_types=1);

namespace Checkslip\Model;

use Checkslip\Enum\ActorType;
use Checkslip\Enum\Channel;
use Checkslip\Internal\Arr;
use Checkslip\Internal\Enums;

/**
 * One action in the account's activity log, such as a key created or credits bought.
 */
final class AuditEventRecord
{
    /**
     * @param array<string, mixed>|null $before
     * @param array<string, mixed>|null $after
     */
    public function __construct(
        public readonly string $eventId,
        /** MONEY, ACCESS, DATA_CHANGE, SENSITIVE_READ or SECURITY. */
        public readonly string $category,
        /** SUCCESS, FAILURE or DENIED. */
        public readonly string $outcome,
        public readonly \DateTimeImmutable $occurredAt,
        public readonly ?string $requestId,
        public readonly ActorType|string $actorType,
        public readonly ?string $actorId,
        public readonly ?string $actorOnBehalfOfUserId,
        public readonly Channel|string $channel,
        /** Such as "api_key.created". */
        public readonly string $action,
        public readonly string $targetType,
        public readonly string $targetId,
        public readonly ?array $before,
        public readonly ?array $after,
        public readonly ?string $reason,
        public readonly ?string $undoOfEventId,
    ) {}

    /**
     * @param array<string, mixed> $d
     */
    public static function fromArray(array $d): self
    {
        $actor = Arr::obj($d, 'actor');
        $target = Arr::obj($d, 'target');

        return new self(
            Arr::str($d, 'event_id'),
            Arr::str($d, 'category'),
            Arr::str($d, 'outcome'),
            Arr::date($d, 'occurred_at'),
            Arr::strOrNull($d, 'request_id'),
            Enums::of(ActorType::class, Arr::str($actor, 'type')),
            Arr::strOrNull($actor, 'id'),
            Arr::strOrNull($actor, 'on_behalf_of_user_id'),
            Enums::of(Channel::class, Arr::str($d, 'channel')),
            Arr::str($d, 'action'),
            Arr::str($target, 'type'),
            Arr::str($target, 'id'),
            Arr::objOrNull($d, 'before'),
            Arr::objOrNull($d, 'after'),
            Arr::strOrNull($d, 'reason'),
            Arr::strOrNull($d, 'undo_of_event_id'),
        );
    }
}
