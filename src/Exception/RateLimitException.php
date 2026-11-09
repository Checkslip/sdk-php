<?php

declare(strict_types=1);

namespace Checkslip\Exception;

use Psr\Http\Message\ResponseInterface;

/**
 * HTTP 429. Too many requests. Wait $retryAfter seconds before the next call.
 *
 * The client already retries 429 responses on its own (see the maxRetries
 * option), so you only see this once those retries ran out.
 */
class RateLimitException extends CheckslipException
{
    /**
     * @param list<array{pointer: string, code: string, detail?: string}> $errors
     * @param array<string, mixed> $problem
     */
    public function __construct(
        string $message,
        ?int $status = null,
        ?string $errorCode = null,
        ?string $title = null,
        ?string $detail = null,
        ?string $requestId = null,
        ?string $type = null,
        ?string $instance = null,
        array $errors = [],
        array $problem = [],
        ?ResponseInterface $response = null,
        ?\Throwable $previous = null,
        /** Seconds from the Retry-After header, or null when it was missing. */
        public readonly ?int $retryAfter = null,
    ) {
        parent::__construct($message, $status, $errorCode, $title, $detail, $requestId, $type, $instance, $errors, $problem, $response, $previous);
    }

    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }
}
