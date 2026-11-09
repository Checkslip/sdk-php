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
     * $httpStatus, $outcomeCode, $route and $channel each take one value or
     * several; a record matches when it matches any one of them. The API
     * allows at most 20 statuses, codes or routes, and at most 5 channels.
     *
     * @param int|list<int>|null $httpStatus
     * @param string|list<string>|null $outcomeCode
     * @param string|list<string>|null $route
     * @param Channel|string|list<Channel|string>|null $channel
     *
     * @return Page<ApiRequestRecord>
     */
    public function list(
        \DateTimeInterface|string|null $from = null,
        \DateTimeInterface|string|null $to = null,
        int|array|null $httpStatus = null,
        string|array|null $outcomeCode = null,
        string|array|null $route = null,
        Channel|string|array|null $channel = null,
        ?string $apiKeyId = null,
        ?string $transRef = null,
        ?string $qrSha256 = null,
        ?int $limit = null,
        ?string $cursor = null,
    ): Page {
        return $this->page('/api/audit/requests', [
            'from' => Query::dateTime($from),
            'to' => Query::dateTime($to),
            'http_status' => Query::many($httpStatus),
            'outcome_code' => Query::many($outcomeCode),
            'route' => Query::many($route),
            'channel' => Query::many($channel),
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
     * @param int|list<int>|null $httpStatus
     * @param string|list<string>|null $outcomeCode
     * @param string|list<string>|null $route
     * @param Channel|string|list<Channel|string>|null $channel
     *
     * @return \Generator<int, ApiRequestRecord>
     */
    public function listAll(
        \DateTimeInterface|string|null $from = null,
        \DateTimeInterface|string|null $to = null,
        int|array|null $httpStatus = null,
        string|array|null $outcomeCode = null,
        string|array|null $route = null,
        Channel|string|array|null $channel = null,
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
