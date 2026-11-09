<?php

declare(strict_types=1);

namespace Checkslip\Model;

use Checkslip\Internal\Arr;

/**
 * The earlier lookup in your account that already returned this transfer.
 */
final class DuplicateOf
{
    public function __construct(
        public readonly string $requestId,
        public readonly \DateTimeImmutable $checkedAt,
    ) {}

    /**
     * @param array<string, mixed> $d
     */
    public static function fromArray(array $d): self
    {
        return new self(Arr::str($d, 'request_id'), Arr::date($d, 'checked_at'));
    }

    /**
     * @param array<string, mixed>|null $d
     */
    public static function fromNullable(?array $d): ?self
    {
        return $d === null ? null : self::fromArray($d);
    }
}
