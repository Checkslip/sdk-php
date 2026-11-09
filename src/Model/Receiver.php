<?php

declare(strict_types=1);

namespace Checkslip\Model;

use Checkslip\Enum\ReceiverKind;
use Checkslip\Internal\Arr;
use Checkslip\Internal\Enums;

/**
 * Who received the transfer. $bank is null when the money did not go to a bank
 * account (for example a PromptPay e-wallet).
 */
final class Receiver
{
    public function __construct(
        public readonly ReceiverKind|string $kind,
        public readonly ?Bank $bank,
        public readonly ?string $nameTh,
        public readonly ?string $nameEn,
        public readonly ?string $account,
    ) {}

    /**
     * @param array<string, mixed> $d
     */
    public static function fromArray(array $d): self
    {
        $bank = Arr::objOrNull($d, 'bank');

        return new self(
            Enums::of(ReceiverKind::class, Arr::str($d, 'kind')),
            $bank !== null ? Bank::fromArray($bank) : null,
            Arr::strOrNull($d, 'name_th'),
            Arr::strOrNull($d, 'name_en'),
            Arr::strOrNull($d, 'account'),
        );
    }
}
