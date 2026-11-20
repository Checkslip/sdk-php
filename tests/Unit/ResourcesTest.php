<?php

declare(strict_types=1);

namespace Checkslip\Tests\Unit;

use Checkslip\Enum\BuyerType;
use Checkslip\Enum\Channel;
use Checkslip\Enum\CreditReason;
use Checkslip\Enum\DeliveryStatus;
use Checkslip\Enum\EventType;
use Checkslip\Enum\ExportState;
use Checkslip\Enum\LotKind;
use Checkslip\Enum\OrderStatus;
use Checkslip\Enum\PaymentMethod;
use Checkslip\Enum\Stream;
use Checkslip\Enum\UsageInterval;
use Checkslip\Model\ApiRequestRecord;
use Checkslip\Model\AuditEventRecord;
use Checkslip\Model\BillingInfo;
use Checkslip\Model\Order;
use Checkslip\Model\PackageOffer;
use Checkslip\Model\ThEn;
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
        // The live answer carries no threshold_basis, and has not since billing
        // 0.48.0 (9 Nov 2026). While this was a required string, the call threw
        // UnexpectedResponseException for every customer.
        self::assertNull($b->thresholdBasis);

        // The lots behind that total (billing contract 0.72.1): the campaign
        // lot is charged first because it expires first, and $credits is their
        // sum.
        self::assertSame(1800, $b->creditsPerYear);
        self::assertCount(2, $b->lots);
        $campaignLot = $b->lots[0];
        self::assertSame(LotKind::Campaign, $campaignLot->kind);
        self::assertSame(250, $campaignLot->creditsLeft);
        self::assertSame(500, $campaignLot->granted);
        self::assertSame(20, $campaignLot->usableToday);
        self::assertInstanceOf(ThEn::class, $campaignLot->campaignName);
        self::assertSame('Launch offer', $campaignLot->campaignName->en);
        self::assertSame('โปรเปิดตัว', $campaignLot->campaignName->th);
        // The paid lot has no campaign and no daily number, and null is not 0:
        // a reader can tell "no limit" from "nothing left today".
        $paidLot = $b->lots[1];
        self::assertSame(LotKind::Paid, $paidLot->kind);
        self::assertNull($paidLot->campaignId);
        self::assertNull($paidLot->campaignName);
        self::assertNull($paidLot->granted);
        self::assertNull($paidLot->usableToday);
        self::assertSame($b->credits, $campaignLot->creditsLeft + $paidLot->creditsLeft);
        self::assertSame('2027-02-01', $b->nextExpiresAt?->format('Y-m-d'));
        self::assertSame('2027-10-29', $b->expiresAt?->format('Y-m-d'));
    }

    /**
     * The property is nullable now, not gone: a body that still carries the
     * word reads through, so an older server does not break the call.
     */
    public function testBalanceStillReadsAThresholdBasisWhenOneArrives(): void
    {
        $this->http->addResponse(self::json(200, self::fixture('balance') + ['threshold_basis' => 'AVG_7_DAYS']));
        $b = $this->client()->billing->balance->get();

        self::assertSame('AVG_7_DAYS', $b->thresholdBasis);
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

    public function testAuditRequestFiltersTakeOneValueOrSeveral(): void
    {
        // style=form with explode=true, so each value gets its own key.
        $this->http->addResponse(self::json(200, ['items' => [], 'next_cursor' => null]));
        $this->client()->audit->requests->list(
            httpStatus: [200, 429],
            outcomeCode: ['FOUND', 'NOT_FOUND'],
            route: ['/api/slips/lookup', '/api/usage'],
            channel: [Channel::Api, Channel::Dashboard],
        );
        $query = $this->lastRequest()->getUri()->getQuery();
        foreach (['http_status=200', 'http_status=429', 'outcome_code=FOUND', 'outcome_code=NOT_FOUND', 'channel=API', 'channel=DASHBOARD'] as $pair) {
            self::assertStringContainsString($pair, $query);
        }
        self::assertStringContainsString('route=' . rawurlencode('/api/slips/lookup'), $query);

        // One value still works, and reads better for a single filter.
        $this->http->addResponse(self::json(200, ['items' => [], 'next_cursor' => null]));
        $this->client()->audit->requests->list(httpStatus: 429, channel: Channel::Api);
        $one = $this->lastRequest()->getUri()->getQuery();
        self::assertStringContainsString('http_status=429', $one);
        self::assertStringContainsString('channel=API', $one);
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
        // The VAT numbers are read, never derived: a caller who multiplies or
        // adds instead of reading them is the mistake these fields prevent.
        self::assertSame(700, $packages->vatRateBp);
        self::assertSame('212.93', $packages->items[0]->priceTotal->toBaht());
        self::assertSame('1.990', $packages->items[0]->pricePerCreditBaht);
        // An offer running now: the regular price stays, and the amount to pay
        // comes from $priceTotal, never from multiplying the per-credit price.
        $offer = $packages->items[0]->offer;
        self::assertInstanceOf(PackageOffer::class, $offer);
        self::assertSame('149.00', $offer->price->toBaht());
        self::assertSame('0.149', $offer->pricePerCreditBaht);
        self::assertSame('2026-12-01', $offer->endsAt->format('Y-m-d'));

        $orders = $billing->orders->list(status: OrderStatus::Paid);
        self::assertSame('status=PAID', $this->lastRequest()->getUri()->getQuery());
        self::assertInstanceOf(Order::class, $orders->items[0]);
        self::assertSame(PaymentMethod::PromptPay, $orders->items[0]->paymentMethod);
        // The code a customer sees and can quote, rather than the uuid.
        self::assertSame('ORD-7K2M9-4PQR8', $orders->items[0]->code);
        // VAT sits inside $amount: the fixture's 13.02 is the VAT within
        // 199.00, not a surcharge on it.
        self::assertSame('13.02', $orders->items[0]->vat?->toBaht());
        // An order with no VAT leaves the field null rather than zero, so a
        // caller can tell "no VAT" from "zero VAT".
        $noVat = self::fixture('order');
        unset($noVat['vat']);
        self::assertNull(Order::fromArray($noVat)->vat);

        $receipt = $billing->receipts->get('0192d4e0-0000-7000-8000-0000000000d2');
        self::assertSame(700, $receipt->vat?->rateBp);
        // The order code, for pointing at the order. The legal number stays
        // $receiptNo.
        self::assertSame('ORD-7K2M9-4PQR8', $receipt->orderCode);
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
        // The lookup this message carries, for GET /api/slips/{request_id}.
        self::assertSame('0192d4e0-0000-7000-8000-00000000d001', $page->items[0]->requestId);

        $detail = $client->webhooks->deliveries->get('dlv_01JBX9K2Q7M3N4P5R6S7T8V9W1');
        self::assertSame('msg_01JBX9K2Q7M3N4P5R6S7T8V9W2', $detail->payload?->id);
        self::assertSame('FOUND', $detail->payload->data['outcome'] ?? null);
        self::assertSame('SUCCESS', $detail->attempts[0]->outcome);
    }

    /**
     * A test message has no lookup behind it, so request_id is null and a
     * reader can tell not to call GET with it (checkslip/webhook
     * h-mwp6pbmg810).
     */
    public function testATestMessageCarriesNoLookupId(): void
    {
        $body = \array_merge(self::fixture('delivery'), ['event_type' => 'webhook.test', 'request_id' => null]);
        $this->http->addResponse(self::json(200, ['items' => [$body], 'next_cursor' => null]));

        $page = $this->client()->webhooks->deliveries->list();

        self::assertNull($page->items[0]->requestId);
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
