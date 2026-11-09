<?php

declare(strict_types=1);

namespace Checkslip\Model;

use Checkslip\Internal\Arr;

final class DeliveryAttempt
{
    public function __construct(
        public readonly int $attemptNo,
        /** SCHEDULE, REDELIVER or RECOVER. */
        public readonly ?string $trigger,
        public readonly \DateTimeImmutable $attemptedAt,
        /** SUCCESS, HTTP_ERROR, TIMEOUT, CONNECT_ERROR, TLS_ERROR, REDIRECT_NOT_FOLLOWED or DESTINATION_BLOCKED. */
        public readonly string $outcome,
        public readonly ?int $httpStatus,
        public readonly int $durationMs,
        /** The start of your system's response body. */
        public readonly ?string $responseExcerpt,
    ) {}

    /**
     * @param array<string, mixed> $d
     */
    public static function fromArray(array $d): self
    {
        return new self(
            Arr::int($d, 'attempt_no'),
            Arr::strOrNull($d, 'trigger'),
            Arr::date($d, 'attempted_at'),
            Arr::str($d, 'outcome'),
            Arr::intOrNull($d, 'http_status'),
            Arr::int($d, 'duration_ms'),
            Arr::strOrNull($d, 'response_excerpt'),
        );
    }
}
