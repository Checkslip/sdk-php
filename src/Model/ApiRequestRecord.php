<?php

declare(strict_types=1);

namespace Checkslip\Model;

use Checkslip\Enum\Channel;
use Checkslip\Internal\Arr;
use Checkslip\Internal\Enums;

/**
 * One request your account sent to the API, from the audit log.
 * Holds no names, phone numbers, emails or full account numbers.
 */
final class ApiRequestRecord
{
    /**
     * @param array{qr_sha256?: string, trans_ref?: string}|null $fingerprint
     * @param list<array{path: string, reason: string}> $redactions
     */
    public function __construct(
        public readonly string $requestId,
        public readonly \DateTimeImmutable $receivedAt,
        public readonly Channel|string|null $channel,
        public readonly string $method,
        public readonly string $route,
        public readonly int $httpStatus,
        public readonly ?string $apiKeyId,
        public readonly ?string $userId,
        /** The caller's network range (/24 or /48), never the full address. */
        public readonly ?string $clientIpPrefix,
        /** A keyed hash of the full address, to match requests from the same machine. */
        public readonly ?string $clientIpHmac,
        public readonly ?string $userAgent,
        public readonly ?string $outcomeCode,
        public readonly ?array $fingerprint,
        public readonly array $redactions,
    ) {}

    /**
     * @param array<string, mixed> $d
     */
    public static function fromArray(array $d): self
    {
        $fp = Arr::objOrNull($d, 'fingerprint');
        $fingerprint = null;
        if ($fp !== null) {
            $fingerprint = [];
            foreach (['qr_sha256', 'trans_ref'] as $k) {
                $v = Arr::strOrNull($fp, $k);
                if ($v !== null) {
                    $fingerprint[$k] = $v;
                }
            }
        }

        return new self(
            Arr::str($d, 'request_id'),
            Arr::date($d, 'received_at'),
            Enums::ofNullable(Channel::class, Arr::strOrNull($d, 'channel')),
            Arr::str($d, 'method'),
            Arr::str($d, 'route'),
            Arr::int($d, 'http_status'),
            Arr::strOrNull($d, 'api_key_id'),
            Arr::strOrNull($d, 'user_id'),
            Arr::strOrNull($d, 'client_ip_prefix'),
            Arr::strOrNull($d, 'client_ip_hmac'),
            Arr::strOrNull($d, 'user_agent'),
            Arr::strOrNull($d, 'outcome_code'),
            $fingerprint,
            self::redactions($d),
        );
    }

    /**
     * @param array<string, mixed> $d
     *
     * @return list<array{path: string, reason: string}>
     */
    public static function redactions(array $d): array
    {
        return array_map(
            static fn(array $r): array => ['path' => Arr::str($r, 'path'), 'reason' => Arr::str($r, 'reason')],
            Arr::objects($d, 'redactions'),
        );
    }
}
