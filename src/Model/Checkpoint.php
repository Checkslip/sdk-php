<?php

declare(strict_types=1);

namespace Checkslip\Model;

use Checkslip\Enum\Stream;
use Checkslip\Internal\Arr;
use Checkslip\Internal\Enums;

/**
 * A signed daily checkpoint of the audit log. Check $signature with the
 * Ed25519 key $keyId from {@see \Checkslip\Resource\Audit::signingKeys()}.
 */
final class Checkpoint
{
    /**
     * @param list<array{lane: int, seq: int, row_hash: string}> $lanes
     */
    public function __construct(
        public readonly string $checkpointId,
        public readonly string $accountId,
        public readonly Stream|string $stream,
        public readonly \DateTimeImmutable $day,
        public readonly bool $sealed,
        public readonly array $lanes,
        public readonly \DateTimeImmutable $createdAt,
        public readonly string $keyId,
        public readonly string $signature,
    ) {}

    /**
     * @param array<string, mixed> $d
     */
    public static function fromArray(array $d): self
    {
        return new self(
            Arr::str($d, 'checkpoint_id'),
            Arr::str($d, 'account_id'),
            Enums::of(Stream::class, Arr::str($d, 'stream')),
            Arr::date($d, 'day'),
            Arr::bool($d, 'sealed'),
            array_map(
                static fn(array $l): array => ['lane' => Arr::int($l, 'lane'), 'seq' => Arr::int($l, 'seq'), 'row_hash' => Arr::str($l, 'row_hash')],
                Arr::objects($d, 'lanes'),
            ),
            Arr::date($d, 'created_at'),
            Arr::str($d, 'key_id'),
            Arr::str($d, 'signature'),
        );
    }
}
