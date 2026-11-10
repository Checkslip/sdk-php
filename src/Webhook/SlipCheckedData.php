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
        public readonly string $requestId,
        public readonly Outcome|string $outcome,
        public readonly \DateTimeImmutable $checkedAt,
        public readonly Channel|string $channel,
        public readonly ?string $clientReference,
        public readonly Slip|SlipSummary|null $slip,
        public readonly ?DuplicateOf $duplicateOf,
        /** True when this lookup first came back PENDING and the bank has now answered. */
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
