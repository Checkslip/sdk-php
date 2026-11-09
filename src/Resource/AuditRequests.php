<?php

declare(strict_types=1);

namespace Checkslip\Resource;

use Checkslip\Enum\Channel;
use Checkslip\Internal\Query;
use Checkslip\Model\ApiRequestRecord;
use Checkslip\Page;

/**
 * Requests this account sent.
 */
final class AuditRequests extends AbstractResource
{
    /**
     * One page of requests the account sent, newest first.
     *
     * @return Page<ApiRequestRecord>
     */
    public function list(
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

    /**
     * Every matching request across all pages.
     *
     * @return \Generator<int, ApiRequestRecord>
     */
    public function listAll(
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
    ): \Generator {
        return $this->list($from, $to, $httpStatus, $outcomeCode, $route, $channel, $apiKeyId, $transRef, $qrSha256, $limit, $cursor)->autoPaging();
    }

    public function get(string $requestId): ApiRequestRecord
    {
        return ApiRequestRecord::fromArray($this->fetch(self::path('/api/audit/requests/%s', $requestId)));
    }
}
