<?php

declare(strict_types=1);

namespace Checkslip\Model;

use Checkslip\Internal\Arr;

/**
 * The short form of a slip, sent when the full transfer is not returned
 * (DUPLICATE, PENDING). Only the reference and the sending bank are certain.
 */
final class SlipSummary
{
    public function __construct(
        public readonly string $transRef,
        public readonly Bank $senderBank,
        public readonly ?Bank $receiverBank = null,
        public readonly ?\DateTimeImmutable $transferredAt = null,
        public readonly ?Money $amount = null,
    ) {}

    /**
     * @param array<string, mixed> $d
     */
    public static function fromArray(array $d): self
    {
        $receiver = Arr::objOrNull($d, 'receiver_bank');
        $amount = Arr::objOrNull($d, 'amount');

        return new self(
            Arr::str($d, 'trans_ref'),
            Bank::fromArray(Arr::obj($d, 'sender_bank')),
            $receiver !== null ? Bank::fromArray($receiver) : null,
            Arr::dateOrNull($d, 'transferred_at'),
            $amount !== null ? Money::fromArray($amount) : null,
        );
    }
}
