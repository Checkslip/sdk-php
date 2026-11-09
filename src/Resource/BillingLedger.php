<?php

declare(strict_types=1);

namespace Checkslip\Resource;

use Checkslip\Enum\CreditReason;
use Checkslip\Internal\Query;
use Checkslip\Model\CreditEntry;
use Checkslip\Page;

/**
 * Every change to the credit balance.
 */
final class BillingLedger extends AbstractResource
{
    /**
     * One page of ledger entries, newest first.
     *
     * @param list<CreditReason|string>|null $reason
     *
     * @return Page<CreditEntry>
     */
    public function list(
        ?array $reason = null,
        \DateTimeInterface|string|null $from = null,
        \DateTimeInterface|string|null $to = null,
        ?int $limit = null,
        ?string $cursor = null,
    ): Page {
        return $this->page('/api/billing/ledger', [
            'reason' => Query::csv($reason),
            'from' => Query::dateTime($from),
            'to' => Query::dateTime($to),
            'limit' => $limit,
            'cursor' => $cursor,
        ], CreditEntry::fromArray(...));
    }

    /**
     * Every ledger entry across all pages, fetching each page as it gets there.
     *
     * @param list<CreditReason|string>|null $reason
     *
     * @return \Generator<int, CreditEntry>
     */
    public function listAll(
        ?array $reason = null,
        \DateTimeInterface|string|null $from = null,
        \DateTimeInterface|string|null $to = null,
        ?int $limit = null,
        ?string $cursor = null,
    ): \Generator {
        return $this->list($reason, $from, $to, $limit, $cursor)->autoPaging();
    }
}
