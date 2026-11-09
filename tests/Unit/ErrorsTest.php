<?php

declare(strict_types=1);

namespace Checkslip\Tests\Unit;

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
use Checkslip\Exception\UnexpectedResponseException;
use Checkslip\Exception\UnprocessableEntityException;
use Checkslip\Tests\TestCase;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;

final class ErrorsTest extends TestCase
{
    /**
     * @return iterable<string, array{int, string, class-string<CheckslipException>}>
     */
    public static function statuses(): iterable
    {
        yield '400' => [400, 'VALIDATION_FAILED', BadRequestException::class];
        yield '401' => [401, 'UNAUTHENTICATED', AuthenticationException::class];
        yield '402' => [402, 'NO_CREDIT', InsufficientCreditException::class];
        yield '403' => [403, 'FORBIDDEN', PermissionDeniedException::class];
        yield '404' => [404, 'ENDPOINT_NOT_FOUND', NotFoundException::class];
        yield '409' => [409, 'ENDPOINT_LIMIT_REACHED', ConflictException::class];
        yield '410' => [410, 'PAYLOAD_EXPIRED', GoneException::class];
        yield '413' => [413, 'PAYLOAD_TOO_LARGE', UnprocessableEntityException::class];
        yield '415' => [415, 'UNSUPPORTED_MEDIA_TYPE', UnprocessableEntityException::class];
        yield '422' => [422, 'QR_TAMPERED', UnprocessableEntityException::class];
        yield '429' => [429, 'RATE_LIMITED', RateLimitException::class];
        yield '500' => [500, 'INTERNAL', ServerException::class];
        yield '503' => [503, 'CREDIT_UNAVAILABLE', ServiceUnavailableException::class];
        yield '418' => [418, 'TEAPOT', CheckslipException::class];
    }

    /**
     * @param class-string<CheckslipException> $class
     */
    #[DataProvider('statuses')]
    public function testStatusMapsToException(int $status, string $code, string $class): void
    {
        $this->http->addResponse(self::problem($status, $code, [], ['detail' => 'More words']));

        try {
            $this->client(maxRetries: 0)->billing->balance->get();
            self::fail('expected an exception');
        } catch (CheckslipException $e) {
            self::assertSame($class, $e::class);
            self::assertSame($status, $e->status);
            self::assertSame($code, $e->errorCode);
            self::assertSame('Title for ' . $code, $e->title);
            self::assertSame('More words', $e->detail);
            self::assertSame('req-' . $status, $e->requestId);
            self::assertSame('/api/test', $e->instance);
            self::assertStringContainsString((string) $status, $e->getMessage());
            self::assertStringContainsString($code, $e->getMessage());
            self::assertSame(0, $e->getCode());
            self::assertNotNull($e->response);
        }
    }

    public function testValidationErrorsAreListed(): void
    {
        $this->http->addResponse(self::problem(400, 'VALIDATION_FAILED', [], [
            'errors' => [
                ['pointer' => '/qr', 'code' => 'REQUIRED'],
                ['pointer' => '/client_reference', 'code' => 'TOO_LONG', 'detail' => 'At most 128'],
                'junk',
            ],
        ]));

        try {
            $this->client()->slips->lookup(slip: 'not-a-slip-qr');
            self::fail('expected an exception');
        } catch (BadRequestException $e) {
            self::assertSame([
                ['pointer' => '/qr', 'code' => 'REQUIRED'],
                ['pointer' => '/client_reference', 'code' => 'TOO_LONG', 'detail' => 'At most 128'],
            ], $e->errors);
        }
    }

    public function testErrorWithoutJsonBodyStillMaps(): void
    {
        $this->http->addResponse(new Response(502, ['Content-Type' => 'text/html'], '<html>Bad gateway</html>'));

        try {
            $this->client(maxRetries: 0)->billing->balance->get();
            self::fail('expected an exception');
        } catch (ServerException $e) {
            self::assertSame(502, $e->status);
            self::assertNull($e->errorCode);
            self::assertSame([], $e->problem);
        }
    }

    public function testSuccessWithBrokenJsonThrows(): void
    {
        $this->http->addResponse(new Response(200, ['Content-Type' => 'application/json'], '{not json'));

        $this->expectException(UnexpectedResponseException::class);
        $this->client()->billing->balance->get();
    }

    public function testMissingRequiredFieldThrows(): void
    {
        $this->http->addResponse(self::json(200, ['credits' => 'many']));

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('"account_id"');
        $this->client()->billing->balance->get();
    }
}
