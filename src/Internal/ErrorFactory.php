<?php

declare(strict_types=1);

namespace Checkslip\Internal;

use Checkslip\Exception\AuthenticationException;
use Checkslip\Exception\BadRequestException;
use Checkslip\Exception\CheckslipException;
use Checkslip\Exception\ConflictException;
use Checkslip\Exception\GoneException;
use Checkslip\Exception\InsufficientCreditException;
use Checkslip\Exception\NotFoundException;
use Checkslip\Exception\PermissionDeniedException;
use Checkslip\Exception\RateLimitException;
use Checkslip\Exception\ServerException;
use Checkslip\Exception\ServiceUnavailableException;
use Checkslip\Exception\UnprocessableEntityException;
use Psr\Http\Message\ResponseInterface;

/**
 * @internal
 */
final class ErrorFactory
{
    public static function fromResponse(ResponseInterface $response, string $body): CheckslipException
    {
        $status = $response->getStatusCode();
        $problem = self::problem($body);

        $code = \is_string($problem['code'] ?? null) ? $problem['code'] : null;
        $title = \is_string($problem['title'] ?? null) ? $problem['title'] : null;
        $detail = \is_string($problem['detail'] ?? null) ? $problem['detail'] : null;
        $type = \is_string($problem['type'] ?? null) ? $problem['type'] : null;
        $instance = \is_string($problem['instance'] ?? null) ? $problem['instance'] : null;
        $requestId = $response->getHeaderLine('X-Request-Id');
        $requestId = $requestId !== '' ? $requestId : null;
        $errors = self::fieldErrors($problem['errors'] ?? null);

        $message = \sprintf('Checkslip API %d%s', $status, $code !== null ? ' ' . $code : '');
        if ($title !== null) {
            $message .= ': ' . $title;
        }
        if ($detail !== null) {
            $message .= ' (' . $detail . ')';
        }
        if ($requestId !== null) {
            $message .= ' [request ' . $requestId . ']';
        }

        $args = [$message, $status, $code, $title, $detail, $requestId, $type, $instance, $errors, $problem, $response];

        return match (true) {
            $status === 400 => new BadRequestException(...$args),
            $status === 401 => new AuthenticationException(...$args),
            $status === 402 => new InsufficientCreditException(...$args),
            $status === 403 => new PermissionDeniedException(...$args),
            $status === 404 => new NotFoundException(...$args),
            $status === 409 => new ConflictException(...$args),
            $status === 410 => new GoneException(...$args),
            \in_array($status, [413, 415, 422], true) => new UnprocessableEntityException(...$args),
            $status === 429 => new RateLimitException(...$args, previous: null, retryAfter: Retry::afterSeconds($response)),
            $status === 503 => new ServiceUnavailableException(...$args, previous: null, retryAfter: Retry::afterSeconds($response)),
            $status >= 500 => new ServerException(...$args),
            default => new CheckslipException(...$args),
        };
    }

    /**
     * Reads the error code without building the whole exception (used to decide on a retry).
     */
    public static function code(string $body): ?string
    {
        $code = self::problem($body)['code'] ?? null;

        return \is_string($code) ? $code : null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function problem(string $body): array
    {
        if ($body === '') {
            return [];
        }
        try {
            $decoded = json_decode($body, true, 64, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }
        if (!\is_array($decoded)) {
            return [];
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * @return list<array{pointer: string, code: string, detail?: string}>
     */
    private static function fieldErrors(mixed $raw): array
    {
        if (!\is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $e) {
            if (!\is_array($e) || !\is_string($e['pointer'] ?? null) || !\is_string($e['code'] ?? null)) {
                continue;
            }
            $one = ['pointer' => $e['pointer'], 'code' => $e['code']];
            if (\is_string($e['detail'] ?? null)) {
                $one['detail'] = $e['detail'];
            }
            $out[] = $one;
        }

        return $out;
    }
}
