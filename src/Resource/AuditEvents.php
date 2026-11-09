<?php

declare(strict_types=1);

namespace Checkslip\Resource;

use Checkslip\Enum\ActorType;
use Checkslip\Enum\Channel;
use Checkslip\Internal\Query;
use Checkslip\Model\AuditEventRecord;
use Checkslip\Page;

/**
 * Account activity.
 */
final class AuditEvents extends AbstractResource
{
    /**
     * One page of account activity, newest first.
     *
     * @param string|null $action Such as "api_key.created", or a prefix like "api_key.*"
     *
     * @return Page<AuditEventRecord>
     */
    public function list(
        \DateTimeInterface|string|null $from = null,
        \DateTimeInterface|string|null $to = null,
        ?string $action = null,
        ActorType|string|null $actorType = null,
        ?string $actorId = null,
        Channel|string|null $channel = null,
        ?string $targetType = null,
        ?string $targetId = null,
        ?int $limit = null,
        ?string $cursor = null,
    ): Page {
        return $this->page('/api/audit/events', [
            'from' => Query::dateTime($from),
            'to' => Query::dateTime($to),
            'action' => $action,
            'actor_type' => $actorType instanceof ActorType ? $actorType->value : $actorType,
            'actor_id' => $actorId,
            'channel' => $channel instanceof Channel ? $channel->value : $channel,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'limit' => $limit,
            'cursor' => $cursor,
        ], AuditEventRecord::fromArray(...));
    }

    /**
     * Every matching activity record across all pages.
     *
     * @return \Generator<int, AuditEventRecord>
     */
    public function listAll(
        \DateTimeInterface|string|null $from = null,
        \DateTimeInterface|string|null $to = null,
        ?string $action = null,
        ActorType|string|null $actorType = null,
        ?string $actorId = null,
        Channel|string|null $channel = null,
        ?string $targetType = null,
        ?string $targetId = null,
        ?int $limit = null,
        ?string $cursor = null,
    ): \Generator {
        return $this->list($from, $to, $action, $actorType, $actorId, $channel, $targetType, $targetId, $limit, $cursor)->autoPaging();
    }

    public function get(string $eventId): AuditEventRecord
    {
        return AuditEventRecord::fromArray($this->fetch(self::path('/api/audit/events/%s', $eventId)));
    }
}
