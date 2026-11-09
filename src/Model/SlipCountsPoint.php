<?php

declare(strict_types=1);

namespace Checkslip\Model;

use Checkslip\Internal\Arr;

final class SlipCountsPoint
{
    /**
     * @param array<string, int> $counts Count per outcome, such as ['FOUND' => 12, 'NOT_FOUND' => 1].
     */
    public function __construct(
        public readonly \DateTimeImmutable $start,
        public readonly array $counts,
    ) {}

    /**
     * @param array<string, mixed> $d
     */
    public static function fromArray(array $d): self
    {
        $counts = [];
        foreach (Arr::obj($d, 'counts') as $k => $v) {
            if (\is_int($v)) {
                $counts[(string) $k] = $v;
            }
        }

        return new self(Arr::date($d, 'start'), $counts);
    }
}
