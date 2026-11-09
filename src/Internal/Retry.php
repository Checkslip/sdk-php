<?php

declare(strict_types=1);

namespace Checkslip\Internal;

use Psr\Http\Message\ResponseInterface;

/**
 * When and how long to wait before sending a request again.
 *
 * @internal
 */
final class Retry
{
    /** HTTP methods that give the same result when sent twice. */
    private const IDEMPOTENT_METHODS = ['GET', 'HEAD', 'PUT', 'DELETE', 'OPTIONS'];

    public function __construct(
        public readonly int $maxRetries = 2,
        public readonly float $baseDelay = 0.5,
        public readonly float $maxDelay = 8.0,
        /** A Retry-After longer than this is not waited out; the error goes to the caller. */
        public readonly float $maxRetryAfter = 60.0,
    ) {
        if ($maxRetries < 0) {
            throw new \InvalidArgumentException('maxRetries cannot be negative.');
        }
    }

    /**
     * True when sending the request again cannot do the work twice: the method is
     * idempotent, or the request carries an Idempotency-Key.
     */
    public static function isSafe(string $method, bool $hasIdempotencyKey): bool
    {
        return $hasIdempotencyKey || \in_array(strtoupper($method), self::IDEMPOTENT_METHODS, true);
    }

    /**
     * Should an HTTP error response be retried? $attempt counts retries already done.
     */
    public function shouldRetryResponse(int $attempt, int $status, bool $safe, ?string $errorCode): bool
    {
        if ($attempt >= $this->maxRetries) {
            return false;
        }

        return match (true) {
            // Rate limited: the API did not run the request, so any method can go again.
            $status === 429 => true,
            // The first request with this Idempotency-Key is still running; asking
            // again with the same key returns its result once it finishes.
            $status === 409 && $errorCode === 'IDEMPOTENCY_IN_FLIGHT' => $safe,
            $status === 408, $status === 500, $status === 502, $status === 503, $status === 504 => $safe,
            default => false,
        };
    }

    public function shouldRetryNetworkError(int $attempt, bool $safe): bool
    {
        return $safe && $attempt < $this->maxRetries;
    }

    /**
     * Seconds to wait before retry number $attempt + 1, or null when the server
     * asked for a wait longer than maxRetryAfter.
     */
    public function delay(int $attempt, ?int $retryAfter): ?float
    {
        if ($retryAfter !== null) {
            return $retryAfter <= $this->maxRetryAfter ? (float) $retryAfter : null;
        }
        $cap = min($this->maxDelay, $this->baseDelay * (2 ** $attempt));

        // "Equal jitter": half of the backoff is fixed, the other half random, so
        // many clients that failed at once do not all come back at the same moment.
        return $cap / 2 + ($cap / 2) * (random_int(0, 1000) / 1000);
    }

    /**
     * Reads Retry-After as seconds. Accepts both forms: "120" and an HTTP date.
     */
    public static function afterSeconds(ResponseInterface $response): ?int
    {
        $value = trim($response->getHeaderLine('Retry-After'));
        if ($value === '') {
            return null;
        }
        if (ctype_digit($value)) {
            return (int) $value;
        }
        $at = strtotime($value);
        if ($at === false) {
            return null;
        }

        return max(0, $at - time());
    }
}
