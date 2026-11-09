<?php

declare(strict_types=1);

namespace Checkslip\Model;

use Checkslip\Enum\Channel;
use Checkslip\Enum\FreeReason;
use Checkslip\Enum\Outcome;
use Checkslip\Internal\Arr;
use Checkslip\Internal\Enums;

/**
 * One past lookup from the history (GET /api/slips).
 *
 * Unlike {@see SlipCheck}, the outcome here can also be a failed lookup such
 * as INVALID_QR or UNAVAILABLE, and $slip is null when there was no transfer to show.
 */
final class SlipCheckRecord
{
    public function __construct(
        public readonly string $requestId,
        public readonly Outcome|string $outcome,
        public readonly \DateTimeImmutable $checkedAt,
        public readonly Channel|string $channel,
        public readonly ?string $apiKeyId = null,
        /** HTTP status the lookup answered with. */
        public readonly ?int $status = null,
        public readonly Slip|SlipSummary|null $slip = null,
        public readonly ?DuplicateOf $duplicateOf = null,
        public readonly ?string $clientReference = null,
        /** True once the credit for this lookup is settled. Null while it is not known yet. */
        public readonly ?bool $chargeConfirmed = null,
        /** True when the lookup used a credit. Null while it is not settled. */
        public readonly ?bool $charged = null,
        public readonly FreeReason|string|null $freeReason = null,
        // latency_ms, upstream_ms and checked_elsewhere are being withdrawn
        // from the Checkslip API. The SDK does not carry them, so your code
        // will not break when the API stops sending them.
    ) {}

    /**
     * @param array<string, mixed> $d
     */
    public static function fromArray(array $d): self
    {
        $slip = Arr::objOrNull($d, 'slip');

        return new self(
            Arr::str($d, 'request_id'),
            Enums::of(Outcome::class, Arr::str($d, 'outcome')),
            Arr::date($d, 'checked_at'),
            Enums::of(Channel::class, Arr::str($d, 'channel')),
            Arr::strOrNull($d, 'api_key_id'),
            Arr::intOrNull($d, 'status'),
            $slip !== null ? Slip::fromEither($slip) : null,
            DuplicateOf::fromNullable(Arr::objOrNull($d, 'duplicate_of')),
            Arr::strOrNull($d, 'client_reference'),
            Arr::boolOrNull($d, 'charge_confirmed'),
            Arr::boolOrNull($d, 'charged'),
            Enums::ofNullable(FreeReason::class, Arr::strOrNull($d, 'free_reason')),
        );
    }
}
