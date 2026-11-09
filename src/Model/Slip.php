<?php

declare(strict_types=1);

namespace Checkslip\Model;

use Checkslip\Internal\Arr;

/**
 * The transfer as the sending bank holds it. Compare $amount, $receiver and
 * $transferredAt with your own order before you accept the payment.
 */
final class Slip
{
    public function __construct(
        /** The bank's transfer reference. */
        public readonly string $transRef,
        public readonly \DateTimeImmutable $transferredAt,
        public readonly Money $amount,
        public readonly Party $sender,
        public readonly Receiver $receiver,
        /** True when the names were erased after the retention period. */
        public readonly bool $namesErased = false,
    ) {}

    /**
     * @param array<string, mixed> $d
     */
    public static function fromArray(array $d): self
    {
        return new self(
            Arr::str($d, 'trans_ref'),
            Arr::date($d, 'transferred_at'),
            Money::fromArray(Arr::obj($d, 'amount')),
            Party::fromArray(Arr::obj($d, 'sender')),
            Receiver::fromArray(Arr::obj($d, 'receiver')),
            Arr::boolOrNull($d, 'names_erased') ?? false,
        );
    }

    /**
     * Reads either shape the API uses for "slip": the full transfer, or the short
     * summary sent with DUPLICATE and PENDING.
     *
     * @param array<string, mixed> $d
     */
    public static function fromEither(array $d): self|SlipSummary
    {
        return \array_key_exists('sender', $d) ? self::fromArray($d) : SlipSummary::fromArray($d);
    }
}
