<?php

declare(strict_types=1);

namespace Checkslip\Webhook;

use Checkslip\Enum\Channel;
use Checkslip\Enum\Outcome;
use Checkslip\Internal\Arr;
use Checkslip\Internal\Enums;
use Checkslip\Model\DuplicateOf;
use Checkslip\Model\Slip;
use Checkslip\Model\SlipSummary;

/**
 * The data of a slip.checked message: a lookup finished, from the API, the
 * web app or the assistant.
 */
final class SlipCheckedData
{
    public function __construct(
        /**
         * The id of this lookup, the same request_id you got when you
         * asked for it. Use it with GET /api/slips/{request_id} to read
         * the same result. One lookup gives one message per endpoint,
         * so the same request_id again is the same message sent again.
         */
        public readonly string $requestId,
        /**
         * The result of the transfer lookup with the bank, with the
         * same values and meanings as outcome in GET
         * /api/slips/{request_id}. This message carries the final
         * result of that lookup, and today it is never PENDING, which
         * is not in use yet.
         */
        public readonly Outcome|string $outcome,
        public readonly \DateTimeImmutable $checkedAt,
        public readonly Channel|string $channel,
        public readonly ?string $clientReference,
        public readonly Slip|SlipSummary|null $slip,
        public readonly ?DuplicateOf $duplicateOf,
        /**
         * Always false today: no lookup is answered PENDING, so none is
         * resolved after one. The field stays in the payload, and required, so
         * code that reads it keeps working.
         */
        public readonly bool $resolvedAfterPending,
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
            Arr::strOrNull($d, 'client_reference'),
            $slip !== null ? Slip::fromEither($slip) : null,
            DuplicateOf::fromNullable(Arr::objOrNull($d, 'duplicate_of')),
            Arr::boolOrNull($d, 'resolved_after_pending') ?? false,
        );
    }
}
