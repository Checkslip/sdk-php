<?php

declare(strict_types=1);

namespace Checkslip\Model;

use Checkslip\Internal\Arr;

final class RecoverResult
{
    public function __construct(
        public readonly string $endpointId,
        public readonly \DateTimeImmutable $since,
        public readonly \DateTimeImmutable $until,
    ) {}

    /**
     * @param array<string, mixed> $d
     */
    public static function fromArray(array $d): self
    {
        return new self(Arr::str($d, 'endpoint_id'), Arr::date($d, 'since'), Arr::date($d, 'until'));
    }
}
