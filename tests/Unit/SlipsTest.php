<?php

declare(strict_types=1);

namespace Checkslip\Tests\Unit;

use Checkslip\Enum\CustomerFreeReason;
use Checkslip\Enum\ExportColumn;
use Checkslip\Enum\FreeReason;
use Checkslip\Enum\Outcome;
use Checkslip\Enum\ReceiverKind;
use Checkslip\Exception\UnprocessableEntityException;
use Checkslip\Model\Slip;
use Checkslip\Model\SlipCheck;
use Checkslip\Model\SlipCheckRecord;
use Checkslip\Model\SlipSummary;
use Checkslip\Tests\TestCase;
use Checkslip\Upload;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;

final class SlipsTest extends TestCase
{
    private const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

    public function testLookupByQrReturnsTheTransfer(): void
    {
        $this->http->addResponse(self::json(200, self::fixture('lookup-found')));

        $check = $this->client()->slips->lookup(slip: '0033000600000101030690', clientReference: 'ORDER-10023');

        $r = $this->lastRequest();
        self::assertSame('POST', $r->getMethod());
        self::assertSame('https://api.checkslip.com/api/slips/lookup', (string) $r->getUri());
        self::assertSame('application/json', $r->getHeaderLine('Content-Type'));
        self::assertSame(['qr' => '0033000600000101030690', 'client_reference' => 'ORDER-10023'], self::body($r));

        self::assertSame(Outcome::Found, $check->outcome);
        self::assertTrue($check->isFound());
        self::assertSame('0192d4e0-7c1a-7b3e-9f00-1a2b3c4d5e6f', $check->requestId);
        self::assertNull($check->retryAfter);
        $slip = $check->slip;
        self::assertInstanceOf(Slip::class, $slip);
        self::assertSame('630210534087', $slip->transRef);
        self::assertSame(5000, $slip->amount->satang);
        self::assertSame('50.00', $slip->amount->toBaht());
        self::assertSame('KKP', $slip->sender->bank->code);
        self::assertSame('069', $slip->sender->bank->botCode);
        self::assertSame('MR. SOMCHAI J', $slip->sender->nameEn);
        self::assertSame(ReceiverKind::BankAccount, $slip->receiver->kind);
        self::assertSame('GSB', $slip->receiver->bank?->code);
        self::assertSame('2026-10-29T10:33:30+07:00', $slip->transferredAt->format(\DATE_RFC3339));
    }

    public function testLookupSendsAGeneratedIdempotencyKey(): void
    {
        $this->http->addResponse(self::json(200, self::fixture('lookup-found')));
        $this->client()->slips->lookup(slip: 'abc');

        self::assertMatchesRegularExpression(self::UUID, $this->lastRequest()->getHeaderLine('Idempotency-Key'));
        self::assertSame(['qr' => 'abc'], self::body($this->lastRequest()));
    }

    public function testLookupUsesTheCallersIdempotencyKey(): void
    {
        $this->http->addResponse(self::json(200, self::fixture('lookup-found')));
        $this->client()->slips->lookup(slip: 'abc', idempotencyKey: 'order-42-slip-1');

        self::assertSame('order-42-slip-1', $this->lastRequest()->getHeaderLine('Idempotency-Key'));
    }

    public function testBillPaymentReceiverHasNoBank(): void
    {
        $this->http->addResponse(self::json(200, self::fixture('lookup-found-bill-payment')));
        $slip = $this->client()->slips->lookup(slip: 'abc')->slip;

        self::assertInstanceOf(Slip::class, $slip);
        self::assertSame(ReceiverKind::BillPayment, $slip->receiver->kind);
        self::assertNull($slip->receiver->bank);
        self::assertSame('1250.50', $slip->amount->toBaht());
    }

    public function testDuplicateCarriesTheSummaryAndTheEarlierLookup(): void
    {
        $this->http->addResponse(self::json(200, self::fixture('lookup-duplicate')));
        $check = $this->client()->slips->lookup(slip: 'abc');

        self::assertTrue($check->isDuplicate());
        self::assertInstanceOf(SlipSummary::class, $check->slip);
        self::assertSame('KKP', $check->slip->senderBank->code);
        self::assertSame('0192d4e0-7c1a-7b3e-9f00-1a2b3c4d5e6f', $check->duplicateOf?->requestId);
    }

    public function testPendingAnswerExposesRetryAfter(): void
    {
        $pending = [
            'request_id' => '0192d4e0-7c1a-7b3e-9f00-1a2b3c4d5e72',
            'outcome' => 'PENDING',
            'checked_at' => '2026-10-29T10:41:02+07:00',
            'slip' => ['trans_ref' => '630210534087', 'sender_bank' => ['code' => 'KKP', 'bot_code' => '069', 'name_th' => null, 'name_en' => null]],
        ];
        $this->http->addResponse(self::json(202, $pending, ['Retry-After' => '30', 'Location' => '/api/slips/0192d4e0-7c1a-7b3e-9f00-1a2b3c4d5e72']));

        $check = $this->client()->slips->lookup(slip: 'abc');

        self::assertTrue($check->isPending());
        self::assertSame(30, $check->retryAfter);
        self::assertSame([], $this->sleeps, 'a 202 is an answer, not something to retry');
    }

    public function testFailedLookupThrowsWithTheCode(): void
    {
        $this->http->addResponse(self::problem(422, 'INVALID_QR'));

        try {
            $this->client()->slips->lookup(slip: 'not a slip');
            self::fail('expected an exception');
        } catch (UnprocessableEntityException $e) {
            self::assertSame('INVALID_QR', $e->errorCode);
            self::assertSame(422, $e->status);
            self::assertSame('req-422', $e->requestId);
        }
        self::assertCount(1, $this->http->getRequests());
    }

    public function testLookupImageSendsMultipart(): void
    {
        $this->http->addResponse(self::json(200, self::fixture('lookup-found')));
        $png = "\x89PNG\r\n\x1A\n" . str_repeat("\0", 32);

        $this->client()->slips->lookup(Upload::fromString($png, 'slip "1".png'), clientReference: 'ORDER-7');

        $r = $this->lastRequest();
        self::assertMatchesRegularExpression('#^multipart/form-data; boundary=(checkslip-[0-9a-f]{32})$#', $r->getHeaderLine('Content-Type'));
        $boundary = substr($r->getHeaderLine('Content-Type'), \strlen('multipart/form-data; boundary='));
        $body = (string) $r->getBody();
        self::assertStringContainsString("--{$boundary}\r\nContent-Disposition: form-data; name=\"client_reference\"\r\n\r\nORDER-7\r\n", $body);
        self::assertStringContainsString("Content-Disposition: form-data; name=\"image\"; filename=\"slip _1_.png\"\r\nContent-Type: image/png\r\n\r\n{$png}\r\n", $body);
        self::assertStringEndsWith("--{$boundary}--\r\n", $body);
        self::assertMatchesRegularExpression(self::UUID, $r->getHeaderLine('Idempotency-Key'));
    }

    public function testUploadSniffsTheImageType(): void
    {
        self::assertSame('image/jpeg', Upload::fromString("\xFF\xD8\xFF\xE0rest")->contentType);
        self::assertSame('image/webp', Upload::fromString('RIFF1234WEBPVP8 ')->contentType);
        self::assertSame('application/octet-stream', Upload::fromString('GIF89a')->contentType);
        self::assertSame('image/png', Upload::fromString('whatever', 'x', 'image/png')->contentType);
    }

    public function testUploadFromPathAndStreams(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'cs');
        self::assertIsString($path);
        file_put_contents($path, "\xFF\xD8\xFFjpeg");
        try {
            $fromPath = Upload::fromPath($path);
            self::assertSame("\xFF\xD8\xFFjpeg", $fromPath->contents);
            self::assertSame(basename($path), $fromPath->filename);

            $h = fopen($path, 'rb');
            self::assertIsResource($h);
            self::assertSame('image/jpeg', Upload::fromStream($h)->contentType);
            fclose($h);

            $psr = Utils::streamFor("\xFF\xD8\xFFjpeg");
            $psr->getContents();
            self::assertSame("\xFF\xD8\xFFjpeg", Upload::fromStream($psr)->contents, 'a PSR-7 stream is rewound first');
        } finally {
            unlink($path);
        }
    }

    public function testUploadRejectsMissingAndEmptyFiles(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Upload::fromPath('/no/such/slip.jpg');
    }

    public function testUploadRejectsEmptyBytes(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Upload::fromString('');
    }

    public function testListBuildsTheQueryTheWayTheSpecSays(): void
    {
        $this->http->addResponse(self::json(200, ['items' => [self::fixture('slip-check-record'), self::fixture('slip-check-record-invalid')], 'next_cursor' => null]));

        $page = $this->client()->slips->list(
            outcome: [Outcome::Found, 'NOT_FOUND'],
            charged: [true, 'unsettled'],
            freeReason: [CustomerFreeReason::NotFound, CustomerFreeReason::Duplicate],
            from: new \DateTimeImmutable('2026-11-01T00:00:00+07:00'),
            limit: 50,
        );

        // outcome is style=form, explode=false: one comma-separated value.
        // charged and free_reason are explode=true: the key repeats.
        self::assertSame(
            'outcome=FOUND%2CNOT_FOUND&charged=true&charged=unsettled&free_reason=NOT_FOUND&free_reason=DUPLICATE&from=2026-11-01T00%3A00%3A00%2B07%3A00&limit=50',
            $this->lastRequest()->getUri()->getQuery(),
        );
        self::assertCount(2, $page);
        self::assertFalse($page->hasMore());

        $first = $page->items[0];
        self::assertSame(Outcome::Found, $first->outcome);
        self::assertSame('ORDER-10023', $first->clientReference);
        self::assertTrue($first->charged);
        self::assertInstanceOf(Slip::class, $first->slip);

        $second = $page->items[1];
        self::assertSame(Outcome::InvalidQr, $second->outcome);
        self::assertSame(FreeReason::InvalidQr, $second->freeReason);
        self::assertNull($second->slip);
    }

    public function testTheContractDecidesWhatIsExposed(): void
    {
        // These three are being withdrawn from the Checkslip API, so the
        // SDK does not expose them.
        foreach (['checkedElsewhere', 'latencyMs', 'upstreamMs'] as $field) {
            self::assertFalse(property_exists(SlipCheck::class, $field), $field);
            self::assertFalse(property_exists(SlipCheckRecord::class, $field), $field);
        }
        $columns = array_column(ExportColumn::cases(), 'value');
        self::assertNotContains('latency_ms', $columns);
        self::assertNotContains('upstream_ms', $columns);
        self::assertNotContains('checked_elsewhere', $columns);

        // These left the contract and must not come back.
        foreach (['traceId', 'recordedBy', 'chain', 'build', 'sourceDomain'] as $field) {
            self::assertFalse(property_exists(SlipCheck::class, $field), $field);
            self::assertFalse(property_exists(SlipCheckRecord::class, $field), $field);
        }
    }

    public function testGetEncodesThePathSegment(): void
    {
        $this->http->addResponse(self::json(200, self::fixture('slip-check-record')));
        $this->client()->slips->get('a/b');

        self::assertSame('/api/slips/a%2Fb', $this->lastRequest()->getUri()->getPath());
    }

    public function testCounts(): void
    {
        $this->http->addResponse(self::json(200, self::fixture('slip-counts')));
        $counts = $this->client()->slips->counts('2026-11-08T00:00:00+07:00', '2026-11-09T00:00:00+07:00', 'HOUR', outcome: [Outcome::Found]);

        self::assertStringContainsString('bucket=HOUR', $this->lastRequest()->getUri()->getQuery());
        self::assertSame(['FOUND' => 12, 'NOT_FOUND' => 1], $counts->series[0]->counts);
    }

    public function testExportAndDownloadPart(): void
    {
        $this->http->addResponse(self::json(200, self::fixture('slip-export')));
        $this->http->addResponse(new Response(200, ['Content-Type' => 'text/csv'], "\u{FEFF}request_id,outcome\n"));
        $client = $this->client();

        $export = $client->slips->exports->create(columns: [ExportColumn::RequestId, ExportColumn::Outcome], outcome: [Outcome::Found]);
        self::assertSame(['columns' => ['request_id', 'outcome'], 'outcome' => ['FOUND']], self::body($this->lastRequest()));
        self::assertTrue($export->isReady());
        self::assertCount(1, $export->parts);

        $csv = $client->slips->exports->downloadPart($export->parts[0]);
        self::assertStringContainsString('request_id,outcome', (string) $csv);
        self::assertSame('sig=abc', $this->lastRequest()->getUri()->getQuery());
        self::assertSame('Bearer csk_test_key', $this->lastRequest()->getHeaderLine('Authorization'));
    }

    public function testDownloadRefusesAnotherHost(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->client()->slips->exports->downloadPart('https://evil.example.com/api/slips/exports/x/parts/1?sig=a');
    }

    public function testExportNeedsAColumn(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->client()->slips->exports->create(columns: []);
    }
}
