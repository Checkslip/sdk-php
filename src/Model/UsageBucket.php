<?php

declare(strict_types=1);

namespace Checkslip\Model;

use Checkslip\Internal\Arr;

final class UsageBucket
{
    public function __construct(
        public readonly \DateTimeImmutable $start,
        public readonly UsageCounts $counts,
    ) {}

    /**
     * @param array<string, mixed> $d
     */
    public static function fromArray(array $d): self
    {
        return new self(Arr::date($d, 'start'), UsageCounts::fromArray($d));
    }
}
