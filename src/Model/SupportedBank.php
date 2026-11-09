<?php

declare(strict_types=1);

namespace Checkslip\Model;

use Checkslip\Internal\Arr;

/**
 * A bank Checkslip can look transfers up with.
 */
final class SupportedBank
{
    public function __construct(
        public readonly string $code,
        public readonly string $botCode,
        public readonly string $nameTh,
        public readonly string $nameEn,
        public readonly \DateTimeImmutable $supportedSince,
        public readonly bool $landingPage,
        /** How many days back this bank answers lookups. Older slips get TOO_OLD. */
        public readonly int $lookbackDays,
    ) {}

    /**
     * @param array<string, mixed> $d
     */
    public static function fromArray(array $d): self
    {
        return new self(
            Arr::str($d, 'code'),
            Arr::str($d, 'bot_code'),
            Arr::str($d, 'name_th'),
            Arr::str($d, 'name_en'),
            Arr::date($d, 'supported_since'),
            Arr::bool($d, 'landing_page'),
            Arr::int($d, 'lookback_days'),
        );
    }
}
