<?php

declare(strict_types=1);

namespace Checkslip\Resource;

use Checkslip\Enum\ActorType;
use Checkslip\Enum\Channel;
use Checkslip\Enum\Stream;
use Checkslip\Internal\Arr;
use Checkslip\Internal\Query;
use Checkslip\Model\ApiRequestRecord;
use Checkslip\Model\AuditEventRecord;
use Checkslip\Model\Checkpoint;
use Checkslip\Model\EvidenceExport;
use Checkslip\Page;
use Psr\Http\Message\StreamInterface;

/**
 * The account's audit log: API requests, activity, evidence files and signed checkpoints.
 */
final class Audit extends AbstractResource
{
    /**
     * Requests the account sent, newest first.
     *
     * @return Page<ApiRequestRecord>
     */
    public function requests(
        \DateTimeInterface|string|null $from = null,
        \DateTimeInterface|string|null $to = null,
        ?int $httpStatus = null,
        ?string $outcomeCode = null,
        ?string $route = null,
        Channel|string|null $channel = null,
        ?string $apiKeyId = null,
        ?string $transRef = null,
        ?string $qrSha256 = null,
        ?int $limit = null,
        ?string $cursor = null,
    ): Page {
        return $this->page('/api/audit/requests', [
            'from' => Query::dateTime($from),
            'to' => Query::dateTime($to),
            'http_status' => $httpStatus,
            'outcome_code' => $outcomeCode,
            'route' => $route,
            'channel' => $channel instanceof Channel ? $channel->value : $channel,
            'api_key_id' => $apiKeyId,
            'trans_ref' => $transRef,
            'qr_sha256' => $qrSha256,
            'limit' => $limit,
            'cursor' => $cursor,
        ], ApiRequestRecord::fromArray(...));
    }

    public function request(string $requestId): ApiRequestRecord
    {
        return ApiRequestRecord::fromArray($this->fetch(self::path('/api/audit/requests/%s', $requestId)));
    }

    /**
     * Account activity, newest first.
     *
     * @param string|null $action Such as "api_key.created", or a prefix like "api_key.*"
     *
     * @return Page<AuditEventRecord>
     */
    public function events(
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

    public function event(string $eventId): AuditEventRecord
    {
        return AuditEventRecord::fromArray($this->fetch(self::path('/api/audit/events/%s', $eventId)));
    }

    /**
     * @return Page<EvidenceExport>
     */
    public function exports(?int $limit = null, ?string $cursor = null): Page
    {
        return $this->page('/api/audit/exports', ['limit' => $limit, 'cursor' => $cursor], EvidenceExport::fromArray(...));
    }

    /**
     * Requests an evidence file for closed UTC days (not today; yesterday from
     * 00:10 UTC), at most 31 days. Comes back QUEUED; poll {@see getExport()}.
     * The same Idempotency-Key returns the same file instead of building another.
     *
     * @param list<Stream|string>|null $streams API_REQUESTS and/or AUDIT_EVENTS. Both when null.
     */
    public function createExport(
        \DateTimeInterface|string $fromDay,
        \DateTimeInterface|string $toDay,
        ?array $streams = null,
        ?string $idempotencyKey = null,
    ): EvidenceExport {
        $body = ['from_day' => Query::date($fromDay), 'to_day' => Query::date($toDay)];
        if ($streams !== null) {
            $body['streams'] = Query::list($streams) ?? [];
        }

        return EvidenceExport::fromArray($this->transport->json('POST', '/api/audit/exports', [], $body, self::idempotency($idempotencyKey))[0]);
    }

    public function getExport(string $exportId): EvidenceExport
    {
        return EvidenceExport::fromArray($this->fetch(self::path('/api/audit/exports/%s', $exportId)));
    }

    /**
     * Downloads a READY evidence file (newline-delimited JSON) as a stream.
     * Compare its SHA-256 with {@see EvidenceExport::$sha256}.
     */
    public function downloadExport(string $exportId): StreamInterface
    {
        return $this->transport->send('GET', self::path('/api/audit/exports/%s/file', $exportId))->getBody();
    }

    /**
     * The newest checkpoint of each stream.
     *
     * @return list<Checkpoint>
     */
    public function latestCheckpoints(): array
    {
        return array_map(Checkpoint::fromArray(...), Arr::objects($this->fetch('/api/audit/checkpoints/latest'), 'items'));
    }

    /**
     * @return Page<Checkpoint>
     */
    public function checkpoints(
        \DateTimeInterface|string|null $fromDay = null,
        \DateTimeInterface|string|null $toDay = null,
        Stream|string|null $stream = null,
        ?int $limit = null,
        ?string $cursor = null,
    ): Page {
        return $this->page('/api/audit/checkpoints', [
            'from_day' => Query::date($fromDay),
            'to_day' => Query::date($toDay),
            'stream' => $stream instanceof Stream ? $stream->value : $stream,
            'limit' => $limit,
            'cursor' => $cursor,
        ], Checkpoint::fromArray(...));
    }

    /**
     * The Ed25519 public keys that sign checkpoints, as a JWK Set
     * (['keys' => [...]]). Retired keys stay listed.
     *
     * @return array<string, mixed>
     */
    public function signingKeys(): array
    {
        return $this->fetch('/api/audit/signing-keys');
    }
}
