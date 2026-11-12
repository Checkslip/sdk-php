<?php

declare(strict_types=1);

namespace Checkslip\Model;

use Checkslip\Enum\Outcome;
use Checkslip\Internal\Arr;
use Checkslip\Internal\Enums;

/**
 * The result of POST /api/slips/lookup.
 *
 * $outcome says what the bank had: FOUND (the transfer and its data are in
 * $slip), NOT_FOUND, DUPLICATE (your account already looked this transfer up
 * and found it, see $duplicateOf) or PENDING (the bank has not posted it
 * yet). Only FOUND uses a credit. Checkslip reports what the bank returned;
 * whether to accept the payment is your call, made against your own order.
 */
final class SlipCheck
{
    public function __construct(
        public readonly string $requestId,
        public readonly Outcome|string $outcome,
        public readonly \DateTimeImmutable $checkedAt,
        public readonly Slip|SlipSummary $slip,
        public readonly ?DuplicateOf $duplicateOf = null,
        public readonly ?string $clientReference = null,
        // latency_ms, upstream_ms and checked_elsewhere left the Checkslip
        // API, so this client does not carry them.
        /**
         * Seconds the API asked you to wait before reading the result again.
         * Set only on a PENDING answer (HTTP 202).
         */
        public readonly ?int $retryAfter = null,
    ) {}

    /**
     * @param array<string, mixed> $d
     */
    public static function fromArray(array $d, ?int $retryAfter = null): self
    {
        return new self(
            Arr::str($d, 'request_id'),
            Enums::of(Outcome::class, Arr::str($d, 'outcome')),
            Arr::date($d, 'checked_at'),
            Slip::fromEither(Arr::obj($d, 'slip')),
            DuplicateOf::fromNullable(Arr::objOrNull($d, 'duplicate_of')),
            Arr::strOrNull($d, 'client_reference'),
            $retryAfter,
        );
    }

    public function isFound(): bool
    {
        return $this->outcome === Outcome::Found;
    }

    public function isPending(): bool
    {
        return $this->outcome === Outcome::Pending;
    }

    public function isDuplicate(): bool
    {
        return $this->outcome === Outcome::Duplicate;
    }

    public function isNotFound(): bool
    {
        return $this->outcome === Outcome::NotFound;
    }
}
