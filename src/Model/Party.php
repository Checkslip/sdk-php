<?php

declare(strict_types=1);

namespace Checkslip\Model;

use Checkslip\Internal\Arr;

/**
 * The sender of a transfer, as the bank reports it. Names and the account
 * number come back masked by the bank (for example "MR. SOMCHAI J", "0439").
 */
final class Party
{
    public function __construct(
        public readonly Bank $bank,
        public readonly ?string $nameTh,
        public readonly ?string $nameEn,
        /**
         * The last 4 digits of the account as the bank shows them. null when
         * the bank sends no number, or when the data was erased (see
         * SlipCheck::$namesErased). Different accounts can share the same last
         * 4 digits, so do not use it on its own to identify an account.
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
         * same value. null when the bank shows no digit, or when the data was
         * erased. Do not use it on its own to identify an account.
         */
        public readonly ?string $accountMasked = null,
    ) {}

    /**
     * @param array<string, mixed> $d
     */
    public static function fromArray(array $d): self
    {
        return new self(
            Bank::fromArray(Arr::obj($d, 'bank')),
            Arr::strOrNull($d, 'name_th'),
            Arr::strOrNull($d, 'name_en'),
            Arr::strOrNull($d, 'account'),
            Arr::strOrNull($d, 'account_masked'),
        );
    }
}
