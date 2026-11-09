<?php

declare(strict_types=1);

namespace Checkslip\Tests\Unit;

use Checkslip\Exception\ConflictException;
use Checkslip\Exception\ConnectionException;
use Checkslip\Exception\RateLimitException;
use Checkslip\Exception\ServerException;
use Checkslip\Exception\ServiceUnavailableException;
use Checkslip\Internal\Retry;
use Checkslip\Tests\NetworkError;
use Checkslip\Tests\TestCase;
use GuzzleHttp\Psr7\Request;

final class RetryTest extends TestCase
{
    public function testRateLimitIsRetriedAfterRetryAfter(): void
    {
        $this->http->addResponse(self::problem(429, 'RATE_LIMITED', ['Retry-After' => '3']));
        $this->http->addResponse(self::json(200, self::fixture('balance')));

        $balance = $this->client()->billing->balance();

        self::assertSame(1250, $balance->credits);
        self::assertSame([3.0], $this->sleeps);
        self::assertCount(2, $this->http->getRequests());
    }

    public function testRateLimitOnPostIsRetriedEvenWithoutIdempotencyKey(): void
    {
        $this->http->addResponse(self::problem(429, 'RATE_LIMITED', ['Retry-After' => '1']));
        $this->http->addResponse(self::json(201, self::fixture('endpoint-with-secret')));

        $this->client()->webhooks->endpoints->create('https://shop.example.com/hooks');

        self::assertCount(2, $this->http->getRequests(), 'a 429 means the API did not run the request');
    }

    public function testRateLimitGivesUpAfterMaxRetries(): void
    {
        for ($i = 0; $i < 3; ++$i) {
            $this->http->addResponse(self::problem(429, 'RATE_LIMITED', ['Retry-After' => '2']));
        }

        try {
            $this->client(maxRetries: 2)->billing->balance();
            self::fail('expected an exception');
        } catch (RateLimitException $e) {
            self::assertSame(2, $e->retryAfter);
            self::assertSame('RATE_LIMITED', $e->getErrorCode());
        }
        self::assertCount(3, $this->http->getRequests());
        self::assertSame([2.0, 2.0], $this->sleeps);
    }

    public function testLongRetryAfterIsNotWaitedOut(): void
    {
        $this->http->addResponse(self::problem(429, 'RATE_LIMITED', ['Retry-After' => '3600']));

        $this->expectException(RateLimitException::class);
        try {
            $this->client()->billing->balance();
        } finally {
            self::assertSame([], $this->sleeps);
        }
    }

    public function testLookup503IsRetriedWithTheSameIdempotencyKey(): void
    {
        $this->http->addResponse(self::problem(503, 'UNAVAILABLE'));
        $this->http->addResponse(self::json(200, self::fixture('lookup-found')));

        $this->client()->slips->lookup(qr: 'abc');

        $requests = $this->http->getRequests();
        self::assertCount(2, $requests);
        $key = $requests[0]->getHeaderLine('Idempotency-Key');
        self::assertNotSame('', $key);
        self::assertSame($key, $requests[1]->getHeaderLine('Idempotency-Key'));
        self::assertSame((string) $requests[0]->getBody(), (string) $requests[1]->getBody());
        self::assertCount(1, $this->sleeps);
        self::assertGreaterThanOrEqual(0.25, $this->sleeps[0]);
        self::assertLessThanOrEqual(0.5, $this->sleeps[0]);
    }

    public function testNetworkErrorOnLookupIsRetried(): void
    {
        $this->http->addException(new NetworkError(new Request('POST', '/')));
        $this->http->addResponse(self::json(200, self::fixture('lookup-found')));

        $check = $this->client()->slips->lookup(qr: 'abc', idempotencyKey: 'k-1');

        self::assertTrue($check->isFound());
        self::assertCount(2, $this->http->getRequests());
    }

    public function testServerErrorOnPostWithoutKeyIsNotRetried(): void
    {
        $this->http->addResponse(self::problem(500, 'INTERNAL'));

        $this->expectException(ServerException::class);
        try {
            $this->client()->webhooks->endpoints->create('https://shop.example.com/hooks');
        } finally {
            self::assertCount(1, $this->http->getRequests());
        }
    }

    public function testNetworkErrorOnPostWithoutKeyIsNotRetried(): void
    {
        $this->http->addException(new NetworkError(new Request('POST', '/')));

        $this->expectException(ConnectionException::class);
        try {
            $this->client()->webhooks->endpoints->create('https://shop.example.com/hooks');
        } finally {
            self::assertCount(1, $this->http->getRequests());
        }
    }

    public function testNetworkErrorOnGetIsRetriedThenSurfaces(): void
    {
        for ($i = 0; $i < 3; ++$i) {
            $this->http->addException(new NetworkError(new Request('GET', '/')));
        }

        try {
            $this->client()->billing->balance();
            self::fail('expected an exception');
        } catch (ConnectionException $e) {
            self::assertNull($e->status);
            self::assertInstanceOf(NetworkError::class, $e->getPrevious());
        }
        self::assertCount(3, $this->http->getRequests());
        self::assertCount(2, $this->sleeps);
    }

    public function testIdempotencyInFlightIsRetried(): void
    {
        $this->http->addResponse(self::problem(409, 'IDEMPOTENCY_IN_FLIGHT'));
        $this->http->addResponse(self::json(200, self::fixture('lookup-found')));

        $this->client()->slips->lookup(qr: 'abc');

        self::assertCount(2, $this->http->getRequests());
    }

    public function testOtherConflictsAreNotRetried(): void
    {
        $this->http->addResponse(self::problem(409, 'ROTATION_IN_PROGRESS'));

        $this->expectException(ConflictException::class);
        try {
            $this->client()->webhooks->endpoints->rotateSecret('ep_1');
        } finally {
            self::assertCount(1, $this->http->getRequests());
        }
    }

    public function testRetriesCanBeTurnedOff(): void
    {
        $this->http->addResponse(self::problem(503, 'UNAVAILABLE', ['Retry-After' => '1']));

        try {
            $this->client(maxRetries: 0)->slips->lookup(qr: 'abc');
            self::fail('expected an exception');
        } catch (ServiceUnavailableException $e) {
            self::assertSame(1, $e->retryAfter);
        }
        self::assertCount(1, $this->http->getRequests());
    }

    public function testBackoffGrowsAndStaysUnderTheCap(): void
    {
        $retry = new Retry(maxRetries: 10, baseDelay: 0.5, maxDelay: 8.0);
        foreach ([0 => 0.5, 1 => 1.0, 2 => 2.0, 3 => 4.0, 4 => 8.0, 8 => 8.0] as $attempt => $cap) {
            $d = $retry->delay($attempt, null);
            self::assertNotNull($d);
            self::assertGreaterThanOrEqual($cap / 2, $d, "attempt {$attempt}");
            self::assertLessThanOrEqual($cap, $d, "attempt {$attempt}");
        }
    }

    public function testRetryAfterAcceptsAnHttpDate(): void
    {
        $at = gmdate('D, d M Y H:i:s \G\M\T', time() + 5);
        $this->http->addResponse(self::problem(429, 'RATE_LIMITED', ['Retry-After' => $at]));
        $this->http->addResponse(self::json(200, self::fixture('balance')));

        $this->client()->billing->balance();

        self::assertCount(1, $this->sleeps);
        self::assertGreaterThanOrEqual(3.0, $this->sleeps[0]);
        self::assertLessThanOrEqual(5.0, $this->sleeps[0]);
    }
}
