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
        /**
         * The last 4 digits of the receiver's account as the bank shows them.
         * null when the bank sends no number, when $kind is not BANK_ACCOUNT
         * because the destination is not a bank account, or when the data was
         * erased (see SlipCheck::$namesErased). Different accounts can share
         * the same last 4 digits, so do not use it on its own to identify an
         * account.
         */
        public readonly ?string $account,
        /**
         * The account number with most digits hidden, in a fixed shape that
         * follows the account's length: xxx-x-xxxxx-x for a 10-digit account
         * and xxx-x-xxxxx-xxx for a 12-digit one. The digits you see are the
         * ones the bank itself shows, in their real places in the account
         * number. Which digits, and how many, depends on the account holder's
         * bank, because each bank hides different digits, for example
         * xxx-x-x1234-x or 12x-x-xx345-6. The same transfer always gives the
         * same value. null when the bank shows no digit, when $kind is not
         * BANK_ACCOUNT, or when the data was erased. Do not use it on its own
         * to identify an account.
         */
        public readonly ?string $accountMasked = null,
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
            Arr::strOrNull($d, 'account_masked'),
        );
    }
}
