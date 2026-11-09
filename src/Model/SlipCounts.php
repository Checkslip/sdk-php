<?php

declare(strict_types=1);

namespace Checkslip\Model;

use Checkslip\Enum\CountBucket;
use Checkslip\Internal\Arr;
use Checkslip\Internal\Enums;

/**
 * Lookup counts over time (GET /api/slips/counts).
 */
final class SlipCounts
{
    /**
     * @param list<string> $filtersApplied Filters the count used.
     * @param list<string> $filtersIgnored Filters you sent that counts do not support.
     * @param list<SlipCountsPoint> $series
     */
    public function __construct(
        public readonly CountBucket|string $bucket,
        public readonly \DateTimeImmutable $asOf,
        public readonly array $filtersApplied,
        public readonly array $filtersIgnored,
        public readonly array $series,
    ) {}

    /**
     * @param array<string, mixed> $d
     */
    public static function fromArray(array $d): self
    {
        return new self(
            Enums::of(CountBucket::class, Arr::str($d, 'bucket')),
            Arr::date($d, 'as_of'),
            Arr::strings($d, 'filters_applied'),
            Arr::strings($d, 'filters_ignored'),
            array_map(SlipCountsPoint::fromArray(...), Arr::objects($d, 'series')),
        );
    }
}
