<?php

declare(strict_types=1);

namespace Checkslip\Tests\Unit;

use Checkslip\Enum\EventType;
use Checkslip\Enum\Outcome;
use Checkslip\Exception\WebhookVerificationException;
use Checkslip\Model\Slip;
use Checkslip\Webhook\Webhook;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

final class WebhookTest extends TestCase
{
    private const SECRET = 'whsec_MfKQ9r8GKYqrTwjUPD8ILPZIo2LaLaSw';

    private const OTHER = 'whsec_AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=';

    private const NOW = 1_762_000_000;

    public function testStandardWebhooksTestVector(): void
    {
        // The published example from the Standard Webhooks spec, so this SDK
        // agrees with every other implementation of the scheme.
        $webhook = new Webhook(self::SECRET, clock: static fn(): int => 1614265330);
        self::assertSame(
            'v1,g0hM9SsE+OTPJTGt/tmIKtSyZlE3uFJELVlNIOLJ1OE=',
            $webhook->sign('msg_p5jXN8AQM9LWM0D4loKWxJek', 1614265330, '{"test": 2432232314}'),
        );
    }

    public function testVerifiesASlipCheckedMessage(): void
    {
        $body = $this->payload();
        $webhook = $this->webhook();
        $headers = $this->headers($webhook, $body);

        $event = $webhook->verify($body, $headers);

        self::assertSame('msg_01JBX9K2Q7M3N4P5R6S7T8V9W2', $event->id);
        self::assertSame(EventType::SlipChecked, $event->type);
        self::assertArrayNotHasKey('checked_elsewhere', $event->data, 'retired field is dropped');

        $data = $event->slipChecked();
        self::assertNotNull($data);
        self::assertSame(Outcome::Found, $data->outcome);
        self::assertSame('ORDER-10023', $data->clientReference);
        self::assertInstanceOf(Slip::class, $data->slip);
        self::assertSame(5000, $data->slip->amount->satang);
        self::assertFalse($data->resolvedAfterPending);
    }

    public function testHeaderNamesInAnyCaseAndServerStyle(): void
    {
        $body = $this->payload();
        $webhook = $this->webhook();
        $h = $this->headers($webhook, $body);

        $upper = ['Webhook-Id' => [$h['webhook-id']], 'WEBHOOK-TIMESTAMP' => $h['webhook-timestamp'], 'Webhook-Signature' => $h['webhook-signature']];
        self::assertNotNull($webhook->verify($body, $upper)->id);

        $server = ['HTTP_WEBHOOK_ID' => $h['webhook-id'], 'HTTP_WEBHOOK_TIMESTAMP' => $h['webhook-timestamp'], 'HTTP_WEBHOOK_SIGNATURE' => $h['webhook-signature']];
        self::assertNotNull($webhook->verify($body, $server)->id);
    }

    public function testVerifiesAPsr7Request(): void
    {
        $body = $this->payload();
        $webhook = $this->webhook();
        $request = new ServerRequest('POST', '/hooks', $this->headers($webhook, $body), $body);

        self::assertSame(EventType::SlipChecked, $webhook->verifyRequest($request)->type);
    }

    public function testRotationHeaderWithTwoSignatures(): void
    {
        $body = $this->payload();
        $both = new Webhook([self::OTHER, self::SECRET], clock: static fn(): int => self::NOW);
        $headers = $this->headers($both, $body);
        self::assertSame(2, substr_count($headers['webhook-signature'], 'v1,'));

        // A receiver that still has only the old secret accepts it...
        self::assertNotNull($this->webhook()->verify($body, $headers)->id);
        // ...and so does one that already switched to the new one.
        self::assertNotNull((new Webhook(self::OTHER, clock: static fn(): int => self::NOW))->verify($body, $headers)->id);
    }

    public function testReceiverCanHoldTwoSecrets(): void
    {
        $body = $this->payload();
        $headers = $this->headers($this->webhook(), $body);

        $receiver = new Webhook([self::OTHER, self::SECRET], clock: static fn(): int => self::NOW);
        self::assertNotNull($receiver->verify($body, $headers)->id);
    }

    public function testWrongSecretFails(): void
    {
        $body = $this->payload();
        $headers = $this->headers($this->webhook(), $body);

        $this->expectException(WebhookVerificationException::class);
        (new Webhook(self::OTHER, clock: static fn(): int => self::NOW))->verify($body, $headers);
    }

    public function testChangedBodyFails(): void
    {
        $body = $this->payload();
        $headers = $this->headers($this->webhook(), $body);

        $this->expectException(WebhookVerificationException::class);
        $this->webhook()->verify(str_replace('5000', '500000', $body), $headers);
    }

    public function testChangedIdFails(): void
    {
        $body = $this->payload();
        $headers = $this->headers($this->webhook(), $body);
        $headers['webhook-id'] = 'msg_other';

        $this->expectException(WebhookVerificationException::class);
        $this->webhook()->verify($body, $headers);
    }

    public function testOldTimestampFails(): void
    {
        $body = $this->payload();
        $signer = new Webhook(self::SECRET);
        $ts = self::NOW - 301;
        $headers = [
            'webhook-id' => 'msg_1',
            'webhook-timestamp' => (string) $ts,
            'webhook-signature' => $signer->sign('msg_1', $ts, $body),
        ];

        $this->expectException(WebhookVerificationException::class);
        $this->expectExceptionMessage('301 seconds');
        $this->webhook()->verify($body, $headers);
    }

    public function testFutureTimestampFails(): void
    {
        $body = $this->payload();
        $ts = self::NOW + 600;
        $headers = [
            'webhook-id' => 'msg_1',
            'webhook-timestamp' => (string) $ts,
            'webhook-signature' => $this->webhook()->sign('msg_1', $ts, $body),
        ];

        $this->expectException(WebhookVerificationException::class);
        $this->webhook()->verify($body, $headers);
    }

    public function testMissingHeaderFails(): void
    {
        $this->expectException(WebhookVerificationException::class);
        $this->webhook()->verify($this->payload(), ['webhook-id' => 'msg_1', 'webhook-timestamp' => (string) self::NOW]);
    }

    public function testNonNumericTimestampFails(): void
    {
        $this->expectException(WebhookVerificationException::class);
        $this->webhook()->verify($this->payload(), ['webhook-id' => 'm', 'webhook-timestamp' => '2026-11-01', 'webhook-signature' => 'v1,x']);
    }

    public function testUnknownSignatureVersionIsIgnored(): void
    {
        $body = $this->payload();
        $headers = $this->headers($this->webhook(), $body);
        $headers['webhook-signature'] = str_replace('v1,', 'v2,', $headers['webhook-signature']);

        $this->expectException(WebhookVerificationException::class);
        $this->webhook()->verify($body, $headers);
    }

    public function testSignedButNotJsonFails(): void
    {
        $webhook = $this->webhook();
        $headers = $this->headers($webhook, 'not json');

        $this->expectException(WebhookVerificationException::class);
        $webhook->verify('not json', $headers);
    }

    public function testBadSecretIsRejectedUpFront(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Webhook('not-a-secret');
    }

    public function testTestMessageHasNoSlipData(): void
    {
        $body = '{"type":"webhook.test","timestamp":"2026-11-01T10:00:00Z","data":{}}';
        $webhook = $this->webhook();

        $event = $webhook->verify($body, $this->headers($webhook, $body));

        self::assertSame(EventType::WebhookTest, $event->type);
        self::assertNull($event->slipChecked());
    }

    private function webhook(): Webhook
    {
        return new Webhook(self::SECRET, clock: static fn(): int => self::NOW);
    }

    private function payload(): string
    {
        $raw = file_get_contents(__DIR__ . '/../Fixtures/webhook-slip-checked.json');
        self::assertIsString($raw);

        return $raw;
    }

    /**
     * @return array{webhook-id: string, webhook-timestamp: string, webhook-signature: string}
     */
    private function headers(Webhook $signer, string $body): array
    {
        $id = 'msg_01JBX9K2Q7M3N4P5R6S7T8V9W2';

        return [
            'webhook-id' => $id,
            'webhook-timestamp' => (string) self::NOW,
            'webhook-signature' => $signer->sign($id, self::NOW, $body),
        ];
    }
}
