<?php

declare(strict_types=1);

namespace Checkslip\Model;

use Checkslip\Internal\Arr;

/**
 * One text in two languages; both are always there.
 */
final class ThEn
{
    public function __construct(
        /** The text in Thai. */
        public readonly string $th,
        /** The text in English. */
        public readonly string $en,
    ) {}

    /**
     * @param array<string, mixed> $d
     */
    public static function fromArray(array $d): self
    {
        return new self(
            Arr::str($d, 'th'),
            Arr::str($d, 'en'),
        );
    }
}
