<?php

declare(strict_types=1);

namespace Checkslip\Internal;

use Checkslip\Exception\ConnectionException;
use Checkslip\Exception\UnexpectedResponseException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Sends requests to the API: auth header, retries, and error mapping.
 *
 * @internal
 */
final class Transport
{
    /** @var \Closure(float): void */
    private readonly \Closure $sleep;

    /**
     * @param (\Closure(float): void)|null $sleep Waits the given number of seconds. Tests pass a fake.
     */
    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl,
        private readonly ClientInterface $http,
        private readonly RequestFactoryInterface $requests,
        private readonly StreamFactoryInterface $streams,
        private readonly Retry $retry,
        private readonly string $userAgent,
        ?\Closure $sleep = null,
    ) {
        $this->sleep = $sleep ?? static function (float $seconds): void {
            if ($seconds > 0) {
                usleep((int) round($seconds * 1_000_000));
            }
        };
    }

    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * Sends a request and returns the decoded JSON object of a 2xx answer.
     *
     * @param array<string, scalar|\DateTimeInterface|list<string>|null> $query
     * @param array<string, mixed>|null $json
     * @param array<string, string> $headers
     *
     * @return array{0: array<string, mixed>, 1: ResponseInterface}
     */
    public function json(string $method, string $path, array $query = [], ?array $json = null, array $headers = [], string $contentType = 'application/json'): array
    {
        $body = null;
        if ($json !== null) {
            $body = json_encode($json === [] ? new \stdClass() : $json, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
            $headers['Content-Type'] = $contentType;
        }
        $response = $this->send($method, $path, $query, $body, $headers);

        return [self::decode($response), $response];
    }

    /**
     * Sends a request and returns the raw 2xx response, for file downloads and empty bodies.
     *
     * @param array<string, scalar|\DateTimeInterface|list<string>|null> $query
     * @param array<string, string> $headers
     */
    public function send(string $method, string $path, array $query = [], ?string $body = null, array $headers = []): ResponseInterface
    {
        $url = str_starts_with($path, 'https://') || str_starts_with($path, 'http://') ? $path : $this->baseUrl . $path;
        $qs = Query::build($query);
        if ($qs !== '') {
            $url .= (str_contains($url, '?') ? '&' : '?') . $qs;
        }

        $safe = Retry::isSafe($method, isset($headers['Idempotency-Key']));
        $attempt = 0;

        while (true) {
            $request = $this->buildRequest($method, $url, $body, $headers);
            try {
                $response = $this->http->sendRequest($request);
            } catch (NetworkExceptionInterface $e) {
                if ($this->retry->shouldRetryNetworkError($attempt, $safe)) {
                    ($this->sleep)($this->retry->delay($attempt, null) ?? 0.0);
                    ++$attempt;
                    continue;
                }
                throw new ConnectionException('Could not reach the Checkslip API: ' . $e->getMessage(), previous: $e);
            } catch (ClientExceptionInterface $e) {
                throw new ConnectionException('The HTTP client could not send the request: ' . $e->getMessage(), previous: $e);
            }

            $status = $response->getStatusCode();
            if ($status < 400) {
                return $response;
            }

            $raw = (string) $response->getBody();
            if ($this->retry->shouldRetryResponse($attempt, $status, $safe, ErrorFactory::code($raw))) {
                $wait = $this->retry->delay($attempt, Retry::afterSeconds($response));
                if ($wait !== null) {
                    ($this->sleep)($wait);
                    ++$attempt;
                    continue;
                }
            }

            throw ErrorFactory::fromResponse($response, $raw);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function decode(ResponseInterface $response): array
    {
        $raw = (string) $response->getBody();
        if ($raw === '') {
            return [];
        }
        try {
            $decoded = json_decode($raw, true, 512, \JSON_THROW_ON_ERROR | \JSON_BIGINT_AS_STRING);
        } catch (\JsonException $e) {
            throw new UnexpectedResponseException('The API answered with a body that is not JSON.', status: $response->getStatusCode(), requestId: self::requestId($response), response: $response, previous: $e);
        }
        if (!\is_array($decoded)) {
            throw new UnexpectedResponseException('The API answered with JSON that is not an object.', status: $response->getStatusCode(), requestId: self::requestId($response), response: $response);
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    public static function requestId(ResponseInterface $response): ?string
    {
        $id = $response->getHeaderLine('X-Request-Id');

        return $id !== '' ? $id : null;
    }

    /**
     * @param array<string, string> $headers
     */
    private function buildRequest(string $method, string $url, ?string $body, array $headers): RequestInterface
    {
        $request = $this->requests->createRequest($method, $url)
            ->withHeader('Accept', 'application/json, application/problem+json')
            ->withHeader('User-Agent', $this->userAgent);

        // Never send the API key to another host, even if a link in a response points there.
        if (self::sameOrigin($url, $this->baseUrl)) {
            $request = $request->withHeader('Authorization', 'Bearer ' . $this->apiKey);
        }
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }
        if ($body !== null) {
            $request = $request->withBody($this->streams->createStream($body));
        }

        return $request;
    }

    private static function sameOrigin(string $url, string $base): bool
    {
        $a = parse_url($url);
        $b = parse_url($base);
        if (!\is_array($a) || !\is_array($b)) {
            return false;
        }

        return strtolower($a['scheme'] ?? '') === strtolower($b['scheme'] ?? '')
            && strtolower($a['host'] ?? '') === strtolower($b['host'] ?? '')
            && ($a['port'] ?? null) === ($b['port'] ?? null);
    }
}
