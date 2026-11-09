<?php

declare(strict_types=1);

namespace Checkslip\Tests\Unit;

use Checkslip\Client;
use Checkslip\Internal\Transport;
use Checkslip\Resource\AbstractResource;
use Checkslip\Tests\TestCase;

final class ClientTest extends TestCase
{
    public function testEmptyApiKeyIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Client(apiKey: '  ', httpClient: $this->http);
    }

    public function testBaseUrlWithPathIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Client(apiKey: 'csk_x', baseUrl: 'https://api.checkslip.com/v1', httpClient: $this->http);
    }

    public function testNegativeRetriesAreRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Client(apiKey: 'csk_x', maxRetries: -1, httpClient: $this->http);
    }

    public function testSendsAuthAcceptAndUserAgent(): void
    {
        $this->http->addResponse(self::json(200, self::fixture('balance')));
        $this->client()->billing->balance();

        $r = $this->lastRequest();
        self::assertSame('GET', $r->getMethod());
        self::assertSame('https://api.checkslip.com/api/billing/balance', (string) $r->getUri());
        self::assertSame('Bearer csk_test_key', $r->getHeaderLine('Authorization'));
        self::assertStringContainsString('application/json', $r->getHeaderLine('Accept'));
        self::assertMatchesRegularExpression('#^checkslip-php/\d+\.\d+\.\d+ PHP/\d+\.\d+$#', $r->getHeaderLine('User-Agent'));
    }

    public function testTrailingSlashOnBaseUrlIsDropped(): void
    {
        $this->http->addResponse(self::json(200, self::fixture('banks')));
        $this->client(baseUrl: 'http://localhost:8080/')->banks->list();

        self::assertSame('http://localhost:8080/api/banks', (string) $this->lastRequest()->getUri());
    }

    public function testBuildsADefaultHttpClientWhenNoneIsGiven(): void
    {
        // Guzzle is installed as a dev dependency, so the SDK builds it with the timeout.
        $client = new Client(apiKey: 'csk_x', timeout: 5.0);

        $transport = (new \ReflectionProperty(AbstractResource::class, 'transport'))->getValue($client->slips);
        self::assertInstanceOf(Transport::class, $transport);
        $http = (new \ReflectionProperty(Transport::class, 'http'))->getValue($transport);
        self::assertInstanceOf(\GuzzleHttp\Client::class, $http);
        self::assertSame(5.0, $http->getConfig('timeout'));
        self::assertFalse($http->getConfig('http_errors'));
    }

    public function testApiKeyIsHiddenFromStackTraces(): void
    {
        $param = (new \ReflectionMethod(Client::class, '__construct'))->getParameters()[0];
        self::assertSame('apiKey', $param->getName());
        self::assertNotEmpty($param->getAttributes(\SensitiveParameter::class));
    }
}
