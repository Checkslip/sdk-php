<?php

declare(strict_types=1);

namespace Checkslip\Model;

use Checkslip\Internal\Arr;

final class Bank
{
    public function __construct(
        /** Short code, such as "KBANK" or "GSB". */
        public readonly string $code,
        /** Three-digit Bank of Thailand code, such as "004". */
        public readonly string $botCode,
        public readonly ?string $nameTh,
        public readonly ?string $nameEn,
    ) {}

    /**
     * @param array<string, mixed> $d
     */
    public static function fromArray(array $d): self
    {
        return new self(Arr::str($d, 'code'), Arr::str($d, 'bot_code'), Arr::strOrNull($d, 'name_th'), Arr::strOrNull($d, 'name_en'));
    }
}
