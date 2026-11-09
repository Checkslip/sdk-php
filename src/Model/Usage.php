<?php

declare(strict_types=1);

namespace Checkslip\Model;

use Checkslip\Enum\UsageInterval;
use Checkslip\Internal\Arr;
use Checkslip\Internal\Enums;

/**
 * Credit usage per day or month, in the Asia/Bangkok time zone.
 */
final class Usage
{
    /**
     * @param list<UsageBucket> $buckets
     */
    public function __construct(
        public readonly UsageInterval|string $interval,
        public readonly \DateTimeImmutable $asOf,
        public readonly \DateTimeImmutable $from,
        public readonly \DateTimeImmutable $to,
        public readonly string $timezone,
        public readonly array $buckets,
        public readonly UsageCounts $totals,
    ) {}

    /**
     * @param array<string, mixed> $d
     */
    public static function fromArray(array $d): self
    {
        return new self(
            Enums::of(UsageInterval::class, Arr::str($d, 'interval')),
            Arr::date($d, 'as_of'),
            Arr::date($d, 'from'),
            Arr::date($d, 'to'),
            Arr::str($d, 'timezone'),
            array_map(UsageBucket::fromArray(...), Arr::objects($d, 'buckets')),
            UsageCounts::fromArray(Arr::obj($d, 'totals')),
        );
    }
}
