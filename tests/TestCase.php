<?php

declare(strict_types=1);

namespace Checkslip\Tests;

use Checkslip\Client;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use Http\Mock\Client as MockClient;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    protected MockClient $http;

    /** @var list<float> Seconds the client asked to sleep between retries. */
    protected array $sleeps = [];

    protected function setUp(): void
    {
        $this->http = new MockClient(new HttpFactory());
        $this->sleeps = [];
    }

    protected function client(int $maxRetries = 2, string $baseUrl = 'https://api.checkslip.com'): Client
    {
        $factory = new HttpFactory();

        return new Client(
            apiKey: 'csk_test_key',
            baseUrl: $baseUrl,
            maxRetries: $maxRetries,
            httpClient: $this->http,
            requestFactory: $factory,
            streamFactory: $factory,
            sleep: function (float $s): void {
                $this->sleeps[] = $s;
            },
        );
    }

    /**
     * @param array<mixed> $body
     * @param array<string, string> $headers
     */
    protected static function json(int $status, array $body, array $headers = []): ResponseInterface
    {
        $type = $status >= 400 ? 'application/problem+json' : 'application/json';

        return new Response($status, $headers + ['Content-Type' => $type, 'X-Request-Id' => 'req-' . $status], json_encode($body, \JSON_THROW_ON_ERROR));
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed> $extra
     */
    protected static function problem(int $status, string $code, array $headers = [], array $extra = []): ResponseInterface
    {
        return self::json($status, $extra + [
            'type' => 'https://checkslip.com/problems/' . strtolower(str_replace('_', '-', $code)),
            'title' => 'Title for ' . $code,
            'status' => $status,
            'instance' => '/api/test',
            'code' => $code,
        ], $headers);
    }

    protected function lastRequest(): RequestInterface
    {
        $r = $this->http->getLastRequest();
        self::assertInstanceOf(RequestInterface::class, $r);

        return $r;
    }

    /**
     * @return array<string, mixed>
     */
    protected static function body(RequestInterface $request): array
    {
        $decoded = json_decode((string) $request->getBody(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * @return array<string, mixed>
     */
    protected static function fixture(string $name): array
    {
        $raw = file_get_contents(__DIR__ . '/Fixtures/' . $name . '.json');
        self::assertIsString($raw);
        $decoded = json_decode($raw, true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
