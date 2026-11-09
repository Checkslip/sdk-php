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
        public readonly ?string $account,
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
        );
    }
}
