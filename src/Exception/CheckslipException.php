<?php

declare(strict_types=1);

namespace Checkslip\Exception;

use Psr\Http\Message\ResponseInterface;

/**
 * Base class for every error the SDK throws.
 *
 * When the API answered, the fields come from its problem+json body (RFC 9457).
 * When it never answered (a network error), $status and the problem fields are null.
 *
 * The API's error code is in $errorCode (for example "NO_CREDIT"). PHP reserves
 * Exception::$code for an integer, so the SDK cannot use that name.
 */
class CheckslipException extends \RuntimeException
{
    /**
     * @param list<array{pointer: string, code: string, detail?: string}> $errors
     * @param array<string, mixed> $problem The decoded problem+json body, as sent.
     */
    public function __construct(
        string $message,
        public readonly ?int $status = null,
        public readonly ?string $errorCode = null,
        public readonly ?string $title = null,
        public readonly ?string $detail = null,
        public readonly ?string $requestId = null,
        public readonly ?string $type = null,
        public readonly ?string $instance = null,
        public readonly array $errors = [],
        public readonly array $problem = [],
        public readonly ?ResponseInterface $response = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * The API error code, such as "INVALID_QR" or "RATE_LIMITED".
     */
    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    public function getStatus(): ?int
    {
        return $this->status;
    }

    public function getRequestId(): ?string
    {
        return $this->requestId;
    }
}
