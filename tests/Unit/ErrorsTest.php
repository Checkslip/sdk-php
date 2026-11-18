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
            // README promises customers this is always 0, so that it is clearly
            // not the API's error code. Do not change it to the status or the code.
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

    /**
     * The README's error example, property for property. A reader copies that
     * block, so every name in it has to resolve on a real exception.
     */
    public function testReadmeErrorExampleReadsTheDocumentedProperties(): void
    {
        $this->http->addResponse(self::problem(422, 'INVALID_QR'));

        try {
            $this->client(maxRetries: 0)->slips->lookup(slip: '0033000600000101030690');
            self::fail('expected an exception');
        } catch (UnprocessableEntityException $e) {
            self::assertSame('INVALID_QR', $e->errorCode);
            self::assertSame(422, $e->status);
            self::assertSame('req-422', $e->requestId);
        }

        $this->http->addResponse(self::problem(429, 'RATE_LIMITED', ['Retry-After' => '30']));

        try {
            $this->client(maxRetries: 0)->slips->lookup(slip: '0033000600000101030690');
            self::fail('expected an exception');
        } catch (RateLimitException $e) {
            self::assertSame(30, $e->retryAfter);
            self::assertSame('RATE_LIMITED', $e->errorCode);
        }
    }

    /**
     * Why the README cannot say $e->code: PHP's own Exception::$code is a
     * protected int, so reading it from outside is a fatal Error rather than a
     * wrong value. A reader who copied $e->code got that Error inside their
     * catch block (pm h-mwl02hww166).
     */
    public function testApiCodeIsNotOnTheReservedCodeProperty(): void
    {
        $this->http->addResponse(self::problem(422, 'INVALID_QR'));

        try {
            $this->client(maxRetries: 0)->slips->lookup(slip: '0033000600000101030690');
            self::fail('expected an exception');
        } catch (UnprocessableEntityException $e) {
            self::assertSame('INVALID_QR', $e->errorCode);
            // README promises customers this is always 0, so that it is clearly
            // not the API's error code. Do not change it to the status or the code.
            self::assertSame(0, $e->getCode());

            $this->expectException(\Error::class);
            /** @phpstan-ignore-next-line reading the reserved property on purpose */
            $e->code; // @phpcs:ignore
        }
    }

    /**
     * Guards the documentation itself. The example is only correct while the
     * README names errorCode, and both languages are checked because they are
     * translated apart.
     *
     * The boundary is the ```php blocks, not the whole file: the prose now
     * warns about $e->code on purpose ("reading $e->code from outside throws
     * an Error", checkslip/proofreader h-mwme55o8a63), so a file-wide ban
     * would fail on our own explanation. If someone tightens this, exempt
     * that sentence rather than deleting it: a red test invites the quickest
     * green, which here means dropping the explanation and leaving the next
     * reader with no idea why their copied code broke.
     */
    public function testReadmeExamplesUseErrorCodeNotTheReservedProperty(): void
    {
        foreach (['README.md', 'README.th.md'] as $name) {
            $text = self::readme($name);

            \preg_match_all('/^```php\n(.*?)^```/ms', $text, $matches);
            $blocks = $matches[1];
            self::assertNotEmpty($blocks, $name . ' has no php example blocks');

            foreach ($blocks as $i => $block) {
                self::assertStringNotContainsString(
                    '$e->code',
                    $block,
                    $name . ' example block ' . (string) $i . ' reads the reserved $code',
                );
            }

            self::assertStringContainsString('$e->errorCode', \implode("\n", $blocks), $name);
        }
    }

    /**
     * The README promises the getErrorCode() method by name, so the docs point
     * at something that exists even if the property is refactored later.
     */
    public function testReadmesPromiseTheGetErrorCodeMethod(): void
    {
        foreach (['README.md', 'README.th.md'] as $name) {
            self::assertStringContainsString('getErrorCode()', self::readme($name), $name);
        }
    }

    public function testGetErrorCodeReturnsTheSameValueAsTheProperty(): void
    {
        $this->http->addResponse(self::problem(422, 'INVALID_QR'));

        try {
            $this->client(maxRetries: 0)->slips->lookup(slip: '0033000600000101030690');
            self::fail('expected an exception');
        } catch (UnprocessableEntityException $e) {
            self::assertSame('INVALID_QR', $e->errorCode);
            self::assertSame($e->errorCode, $e->getErrorCode());
        }
    }

    private static function readme(string $name): string
    {
        $text = \file_get_contents(\dirname(__DIR__, 2) . '/' . $name);
        self::assertIsString($text);

        return $text;
    }
}
