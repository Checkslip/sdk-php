<?php

declare(strict_types=1);

namespace Checkslip\Tests\Unit;

use Checkslip\Enum\FreeReason;
use Checkslip\Enum\Outcome;
use Checkslip\Model\ApiRequestRecord;
use Checkslip\Model\SlipCheck;
use Checkslip\Model\UsageCounts;
use Checkslip\Resource\AbstractResource;
use Checkslip\Tests\TestCase;

/**
 * Guards the surface the four Checkslip SDKs share, so the clients cannot
 * drift apart.
 */
final class ContractTest extends TestCase
{
    /**
     * Every canonical resource.method, spelled the PHP way: 39 operations,
     * 9 listAll generators and the local webhooks.verify.
     *
     * @var list<string>
     */
    private const CANONICAL = [
        'slips.lookup', 'slips.get', 'slips.list', 'slips.listAll', 'slips.counts',
        'slips.exports.create', 'slips.exports.get', 'slips.exports.downloadPart',
        'banks.list',
        'billing.balance.get',
        'billing.info.get', 'billing.info.update',
        'billing.packages.list',
        'billing.ledger.list', 'billing.ledger.listAll',
        'billing.orders.list', 'billing.orders.listAll', 'billing.orders.get', 'billing.orders.sync',
        'billing.receipts.list', 'billing.receipts.listAll', 'billing.receipts.get',
        'usage.get',
        'audit.events.list', 'audit.events.listAll', 'audit.events.get',
        'audit.requests.list', 'audit.requests.listAll', 'audit.requests.get',
        'audit.exports.list', 'audit.exports.listAll', 'audit.exports.create',
        'audit.exports.get', 'audit.exports.download',
        'webhooks.endpoints.list', 'webhooks.endpoints.listAll', 'webhooks.endpoints.create',
        'webhooks.endpoints.get', 'webhooks.endpoints.update', 'webhooks.endpoints.delete',
        'webhooks.endpoints.recover', 'webhooks.endpoints.rotateSecret', 'webhooks.endpoints.sendTest',
        'webhooks.deliveries.list', 'webhooks.deliveries.listAll', 'webhooks.deliveries.get',
        'webhooks.deliveries.redeliver',
        'webhooks.eventTypes.list',
        'webhooks.verify',
    ];

    /**
     * Walks the client's resources and returns every public method as a
     * dotted path.
     *
     * @return list<string>
     */
    private static function walk(object $node, string $prefix, int $depth = 1): array
    {
        if ($depth > 3) {
            return [];
        }
        $found = [];
        $class = new \ReflectionClass($node);
        foreach ($class->getMethods(\ReflectionMethod::IS_PUBLIC) as $m) {
            if ($m->isConstructor() || $m->isStatic()) {
                continue;
            }
            $found[] = $prefix . $m->getName();
        }
        foreach ($class->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
            $value = $prop->getValue($node);
            if ($value instanceof AbstractResource) {
                $found = [...$found, ...self::walk($value, $prefix . $prop->getName() . '.', $depth + 1)];
            }
        }

        return $found;
    }

    /**
     * @return list<string>
     */
    private function surface(): array
    {
        $client = $this->client();
        $found = [];
        foreach (['slips', 'banks', 'billing', 'usage', 'audit', 'webhooks'] as $group) {
            $found = [...$found, ...self::walk($client->{$group}, $group . '.')];
        }

        return $found;
    }

    public function testTheTableHas49Names(): void
    {
        self::assertCount(49, array_unique(self::CANONICAL));
    }

    public function testEveryCanonicalMethodExists(): void
    {
        $found = $this->surface();
        self::assertNotEmpty($found, 'walked no methods at all; this check would pass vacuously');
        foreach (self::CANONICAL as $name) {
            self::assertContains($name, $found, $name);
        }
    }

    public function testNothingOutsideTheTableIsExposed(): void
    {
        $extra = array_values(array_diff($this->surface(), self::CANONICAL));
        sort($extra);
        self::assertSame([], $extra);
    }

    public function testBareItemsAreUnwrappedAndThePackageListIsNot(): void
    {
        $this->http->addResponse(self::json(200, ['items' => [[
            'code' => 'KKP', 'bot_code' => '069', 'name_th' => 'a', 'name_en' => 'b',
            'supported_since' => '2026-01-01', 'landing_page' => true, 'lookback_days' => 90,
        ]]]));
        $this->http->addResponse(self::json(200, ['items' => [['type' => 'slip.checked', 'description' => 'd', 'subscribable' => true]]]));
        $this->http->addResponse(self::json(200, self::fixture('packages')));
        $client = $this->client();

        $banks = $client->banks->list();
        self::assertIsArray($banks);
        self::assertSame('KKP', $banks[0]->code);

        $types = $client->webhooks->eventTypes->list();
        self::assertIsArray($types);
        self::assertCount(1, $types);

        // The package list carries more than items, so it keeps its envelope.
        $packages = $client->billing->packages->list();
        self::assertIsBool($packages->checkoutOpen);
        self::assertIsBool($packages->pricesIncludeVat);
    }

    public function testAcceptedIsNotAnError(): void
    {
        $pending = self::fixture('lookup-found');
        $pending['outcome'] = 'PENDING';
        $this->http->addResponse(self::json(202, $pending, ['Retry-After' => '5']));

        $check = $this->client()->slips->lookup(slip: 'some-qr-text');
        self::assertSame(Outcome::Pending, $check->outcome);
        self::assertSame(5, $check->retryAfter);
    }

    public function testUnmodelledFieldsDoNotBreakDecoding(): void
    {
        // A response may carry fields the SDK does not model, including ones
        // that left the customer API. Decoding must not depend on the set.
        $without = self::fixture('lookup-found');
        $withExtras = $without + [
            'trace_id' => 't1',
            'recorded_by' => 'gw',
            'chain' => [],
            'build' => '2026.11.9',
        ];

        $a = SlipCheck::fromArray($withExtras);
        $b = SlipCheck::fromArray($without);
        self::assertEquals($a, $b);
    }

    public function testLatencyMsIsCarriedBecauseTheContractRequiresIt(): void
    {
        $record = ApiRequestRecord::fromArray([
            'request_id' => 'rq1',
            'received_at' => '2026-11-01T00:00:00Z',
            'latency_ms' => 42,
            'channel' => 'API',
            'method' => 'POST',
            'route' => '/api/slips/lookup',
            'http_status' => 200,
        ]);
        self::assertSame(42, $record->latencyMs);
    }

    public function testLookupTakesQrTextOrAnUploadButNotNothing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->client()->slips->lookup(slip: '   ');
    }

    /**
     * The API added free_other, stopped counting free_no_credit and
     * free_hold_expired, and will drop those two fields. The SDK and the API
     * are deployed separately, so both shapes have to parse.
     */
    public function testUsageCountsParseWithOrWithoutTheDeprecatedFields(): void
    {
        $common = [
            'checks' => 12,
            'charged' => 7,
            'free_not_found' => 1,
            'free_duplicate' => 0,
            'free_unavailable' => 0,
            'free_invalid_qr' => 0,
            'free_invalid_file' => 0,
            'free_qr_tampered' => 0,
            'free_too_old' => 0,
            'free_system_error' => 0,
        ];

        $before = UsageCounts::fromArray($common + ['free_no_credit' => 3, 'free_hold_expired' => 2]);
        self::assertSame(3, $before->freeNoCredit);
        self::assertSame(2, $before->freeHoldExpired);
        self::assertSame(0, $before->freeOther, 'a server without the field counts nothing there');

        $after = UsageCounts::fromArray($common + ['free_other' => 5]);
        self::assertSame(5, $after->freeOther);
        self::assertSame(0, $after->freeNoCredit);
        self::assertSame(0, $after->freeHoldExpired);

        self::assertSame('OTHER', FreeReason::Other->value);
        self::assertSame(FreeReason::Other, FreeReason::tryFrom('OTHER'));
    }
}
