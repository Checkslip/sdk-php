<?php

declare(strict_types=1);

namespace Checkslip\Tests\Unit;

use Checkslip\Enum\BuyerType;
use Checkslip\Enum\Channel;
use Checkslip\Enum\CreditReason;
use Checkslip\Enum\DeliveryStatus;
use Checkslip\Enum\EventType;
use Checkslip\Enum\ExportState;
use Checkslip\Enum\OrderStatus;
use Checkslip\Enum\PaymentMethod;
use Checkslip\Enum\Stream;
use Checkslip\Enum\UsageInterval;
use Checkslip\Model\ApiRequestRecord;
use Checkslip\Model\AuditEventRecord;
use Checkslip\Model\BillingInfo;
use Checkslip\Model\Order;
use Checkslip\Tests\TestCase;
use GuzzleHttp\Psr7\Response;

final class ResourcesTest extends TestCase
{
    public function testBalance(): void
    {
        $this->http->addResponse(self::json(200, self::fixture('balance')));
        $b = $this->client()->billing->balance->get();

        self::assertSame(1250, $b->credits);
        self::assertFalse($b->low);
        self::assertSame('AVG_7_DAYS', $b->thresholdBasis);
        self::assertSame('2027-10-29', $b->expiresAt?->format('Y-m-d'));
    }

    public function testUsageSendsDatesAndReadsBuckets(): void
    {
        $this->http->addResponse(self::json(200, self::fixture('usage')));
        $u = $this->client()->usage->get(new \DateTimeImmutable('2026-11-01'), '2026-11-02', UsageInterval::Day);

        self::assertSame('/api/usage', $this->lastRequest()->getUri()->getPath());
        self::assertSame('interval=DAY&from=2026-11-01&to=2026-11-02', $this->lastRequest()->getUri()->getQuery());
        self::assertSame(UsageInterval::Day, $u->interval);
        self::assertCount(2, $u->buckets);
        self::assertSame(7, $u->buckets[0]->counts->charged);
        self::assertSame(20, $u->totals->checks);
    }

    public function testBanks(): void
    {
        $this->http->addResponse(self::json(200, self::fixture('banks')));
        $banks = $this->client()->banks->list();

        self::assertCount(2, $banks);
        self::assertSame('KBANK', $banks[0]->code);
        self::assertSame(60, $banks[1]->lookbackDays);
    }

    public function testPackagesOrdersReceipts(): void
    {
        $this->http->addResponse(self::json(200, self::fixture('packages')));
        $this->http->addResponse(self::json(200, ['items' => [self::fixture('order')], 'next_cursor' => null]));
        $this->http->addResponse(self::json(200, self::fixture('receipt')));
        $billing = $this->client()->billing;

        $packages = $billing->packages->list();
        self::assertTrue($packages->checkoutOpen);
        self::assertSame('199.00', $packages->items[0]->price->toBaht());

        $orders = $billing->orders->list(status: OrderStatus::Paid);
        self::assertSame('status=PAID', $this->lastRequest()->getUri()->getQuery());
        self::assertInstanceOf(Order::class, $orders->items[0]);
        self::assertSame(PaymentMethod::PromptPay, $orders->items[0]->paymentMethod);

        $receipt = $billing->receipts->get('0192d4e0-0000-7000-8000-0000000000d2');
        self::assertSame(700, $receipt->vat?->rateBp);
        self::assertSame('บริษัท โฮโลแกรม จำกัด', $receipt->seller['name']);
        self::assertSame(BuyerType::Company, $receipt->buyer->buyerType);
    }

    public function testLedgerJoinsReasons(): void
    {
        $this->http->addResponse(self::json(200, ['items' => [], 'next_cursor' => null]));
        $this->client()->billing->ledger->list(reason: [CreditReason::Purchase, CreditReason::Expire]);

        self::assertSame('reason=PURCHASE%2CEXPIRE', $this->lastRequest()->getUri()->getQuery());
    }

    public function testUpdateBillingInfoSendsOnlySetFields(): void
    {
        $this->http->addResponse(self::json(200, ['buyer_type' => 'PERSON', 'name' => 'สมชาย']));
        $info = $this->client()->billing->info->update(new BillingInfo(buyerType: BuyerType::Person, name: 'สมชาย'));

        self::assertSame('PUT', $this->lastRequest()->getMethod());
        self::assertSame(['buyer_type' => 'PERSON', 'name' => 'สมชาย'], self::body($this->lastRequest()));
        self::assertSame('สมชาย', $info->name);
    }

    public function testCreateEndpointReturnsTheSecretOnce(): void
    {
        $this->http->addResponse(self::json(201, self::fixture('endpoint-with-secret')));
        $created = $this->client()->webhooks->endpoints->create('https://shop.example.com/hooks/checkslip', description: 'Main shop');

        self::assertSame(['url' => 'https://shop.example.com/hooks/checkslip', 'event_types' => ['slip.checked'], 'description' => 'Main shop'], self::body($this->lastRequest()));
        self::assertStringStartsWith('whsec_', $created->secret);
        self::assertSame([EventType::SlipChecked], $created->endpoint->eventTypes);
        self::assertSame('', $this->lastRequest()->getHeaderLine('Idempotency-Key'));
    }

    public function testUpdateEndpointIsAMergePatch(): void
    {
        $this->http->addResponse(self::json(200, self::fixture('endpoint')));
        $this->client()->webhooks->endpoints->update('ep_1', enabled: false, clearDescription: true);

        $r = $this->lastRequest();
        self::assertSame('PATCH', $r->getMethod());
        self::assertSame('application/merge-patch+json', $r->getHeaderLine('Content-Type'));
        self::assertSame(['description' => null, 'enabled' => false], self::body($r));
    }

    public function testDeleteEndpoint(): void
    {
        $this->http->addResponse(new Response(204));
        $this->client()->webhooks->endpoints->delete('ep_1');

        self::assertSame('DELETE', $this->lastRequest()->getMethod());
        self::assertSame('/api/webhooks/endpoints/ep_1', $this->lastRequest()->getUri()->getPath());
    }

    public function testEndpointActionsCarryAnIdempotencyKey(): void
    {
        $this->http->addResponse(self::json(200, ['secret' => 'whsec_x', 'previous_secret_expires_at' => '2026-11-02T10:00:00Z']));
        $this->http->addResponse(self::json(202, self::fixture('delivery')));
        $this->http->addResponse(self::json(202, ['endpoint_id' => 'ep_1', 'since' => '2026-11-01T00:00:00Z', 'until' => '2026-11-02T00:00:00Z']));
        $this->http->addResponse(self::json(202, self::fixture('delivery')));
        $client = $this->client();

        $client->webhooks->endpoints->rotateSecret('ep_1', idempotencyKey: 'rot-1');
        $client->webhooks->endpoints->sendTest('ep_1');
        $result = $client->webhooks->endpoints->recover('ep_1', since: new \DateTimeImmutable('2026-11-01T00:00:00Z'));
        $client->webhooks->deliveries->redeliver('dlv_1');

        $requests = $this->http->getRequests();
        self::assertSame('rot-1', $requests[0]->getHeaderLine('Idempotency-Key'));
        foreach ($requests as $r) {
            self::assertNotSame('', $r->getHeaderLine('Idempotency-Key'), (string) $r->getUri());
        }
        self::assertSame('/api/webhooks/endpoints/ep_1/secret/rotate', $requests[0]->getUri()->getPath());
        self::assertSame(['since' => '2026-11-01T00:00:00+00:00'], self::body($requests[2]));
        self::assertSame('ep_1', $result->endpointId);
        self::assertSame('/api/webhooks/deliveries/dlv_1/redeliver', $requests[3]->getUri()->getPath());
    }

    public function testDeliveriesListAndDetail(): void
    {
        $this->http->addResponse(self::json(200, ['items' => [self::fixture('delivery')], 'next_cursor' => null]));
        $this->http->addResponse(self::json(200, self::fixture('delivery-detail')));
        $client = $this->client();

        $page = $client->webhooks->deliveries->list(status: DeliveryStatus::Failed, eventType: EventType::SlipChecked);
        self::assertSame('status=FAILED&event_type=slip.checked', $this->lastRequest()->getUri()->getQuery());
        self::assertSame(DeliveryStatus::Succeeded, $page->items[0]->status);

        $detail = $client->webhooks->deliveries->get('dlv_01JBX9K2Q7M3N4P5R6S7T8V9W1');
        self::assertSame('msg_01JBX9K2Q7M3N4P5R6S7T8V9W2', $detail->payload?->id);
        self::assertSame('FOUND', $detail->payload->data['outcome'] ?? null);
        self::assertSame('SUCCESS', $detail->attempts[0]->outcome);
    }

    public function testEventTypes(): void
    {
        $this->http->addResponse(self::json(200, ['items' => [['type' => 'slip.checked', 'description' => 'A lookup finished', 'subscribable' => true]]]));
        $types = $this->client()->webhooks->eventTypes->list();

        self::assertSame(EventType::SlipChecked, $types[0]->type);
    }

    public function testAuditRecordsLeaveRetiredFieldsOut(): void
    {
        $this->http->addResponse(self::json(200, ['items' => [self::fixture('api-request')], 'next_cursor' => null]));
        $this->http->addResponse(self::json(200, self::fixture('audit-event')));
        $client = $this->client();

        $req = $client->audit->requests->list(channel: Channel::Api, transRef: '630210534087')->items[0];
        self::assertSame('channel=API&trans_ref=630210534087', $this->lastRequest()->getUri()->getQuery());
        self::assertSame(200, $req->httpStatus);
        self::assertSame(['qr_sha256' => str_repeat('e', 64), 'trans_ref' => '630210534087'], $req->fingerprint);

        $event = $client->audit->events->get('0192d4e0-0000-7000-8000-0000000000a1');
        self::assertSame('api_key.created', $event->action);
        self::assertSame(['name' => 'shop'], $event->after);

        // The published OpenAPI document decides what the SDK exposes. These
        // fields left it, so no model may carry them.
        foreach (['traceId', 'upstreamMs', 'recordedBy', 'build', 'chain', 'redactions'] as $field) {
            self::assertFalse(property_exists(ApiRequestRecord::class, $field), $field);
        }
        foreach (['sourceDomain', 'build', 'chain', 'redactions', 'causationId', 'actorAssurance'] as $field) {
            self::assertFalse(property_exists(AuditEventRecord::class, $field), $field);
        }
        // latency_ms stayed, and the contract marks it required.
        self::assertTrue(property_exists(ApiRequestRecord::class, 'latencyMs'));
        self::assertSame(812, $req->latencyMs);
    }

    public function testEvidenceExportFlow(): void
    {
        $this->http->addResponse(self::json(202, self::fixture('evidence-export')));
        $this->http->addResponse(new Response(200, ['Content-Type' => 'application/x-ndjson'], "{\"kind\":\"MANIFEST\"}\n"));
        $client = $this->client();

        $export = $client->audit->exports->create('2026-11-01', new \DateTimeImmutable('2026-11-02'), streams: [Stream::AuditEvents]);
        $create = $this->lastRequest();
        self::assertSame(['from_day' => '2026-11-01', 'to_day' => '2026-11-02', 'streams' => ['AUDIT_EVENTS']], self::body($create));
        self::assertNotSame('', $create->getHeaderLine('Idempotency-Key'));
        self::assertSame(ExportState::Ready, $export->status);
        self::assertSame(str_repeat('a', 64), $export->sha256);

        $file = $client->audit->exports->download($export->exportId);
        self::assertStringContainsString('MANIFEST', (string) $file);
        self::assertSame('/api/audit/exports/0192d4e0-0000-7000-8000-0000000000e1/file', $this->lastRequest()->getUri()->getPath());
    }

    public function testUnknownEnumValuesComeBackAsStrings(): void
    {
        $order = self::fixture('order');
        $order['status'] = 'REFUNDED';
        $this->http->addResponse(self::json(200, $order));

        self::assertSame('REFUNDED', $this->client()->billing->orders->get('x')->status);
    }
}
