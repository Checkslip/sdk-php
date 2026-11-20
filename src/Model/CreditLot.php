<?php

declare(strict_types=1);

namespace Checkslip\Model;

use Checkslip\Enum\LotKind;
use Checkslip\Internal\Arr;
use Checkslip\Internal\Enums;

/**
 * One lot of credits with its own expiry. The account's credits are the sum of
 * its lots. A credit is used only when a matching transfer is found, and it is
 * taken from the usable lot that expires first; when two expire at the same
 * moment, a campaign lot is used before a bought one.
 */
final class CreditLot
{
    public function __construct(
        /** This lot's id, the same value as CreditEntry::$lotId in the credit history. */
        public readonly string $id,
        /** The kind of lot. New kinds may be added: show an unknown kind as credits with their expiry. */
        public readonly LotKind|string $kind,
        /** The campaign that gave this lot, on CAMPAIGN lots only. null on other lots. */
        public readonly ?string $campaignId,
        /**
         * The campaign's name in Thai and English, as the campaign had it when
         * this account claimed (a later rename does not change it). Both
         * languages always come; pick which one to show for your user.
         * CAMPAIGN lots only; null on other lots.
         */
        public readonly ?ThEn $campaignName,
        /** The credits this lot has left. */
        public readonly int $creditsLeft,
        /**
         * On a CAMPAIGN lot, how many credits the campaign gave at the start;
         * with $creditsLeft it shows how many were used. On the PAID lot it is
         * null, because that lot grows with every purchase.
         */
        public readonly ?int $granted,
        /** When the credits left in this lot expire. */
        public readonly \DateTimeImmutable $expiresAt,
        /**
         * True when a slip lookup can be charged to this lot now. False when it
         * has expired (and is not yet gone from the list), our team has paused
         * its use, or a campaign lot has used its daily number for today (it
         * works again from 00:00 Thailand time). Meanwhile lookups are charged
         * to another usable lot.
         */
        public readonly bool $usable,
        /**
         * On a campaign lot with a daily number: the credits this lot can still
         * give today, by the Thailand-time day, which starts again at 00:00.
         * null means this lot has no daily limit.
         */
        public readonly ?int $usableToday,
    ) {}

    /**
     * @param array<string, mixed> $d
     */
    public static function fromArray(array $d): self
    {
        $name = Arr::objOrNull($d, 'campaign_name');

        return new self(
            Arr::str($d, 'id'),
            Enums::of(LotKind::class, Arr::str($d, 'kind')),
            Arr::strOrNull($d, 'campaign_id'),
            $name !== null ? ThEn::fromArray($name) : null,
            Arr::int($d, 'credits_left'),
            Arr::intOrNull($d, 'granted'),
            Arr::date($d, 'expires_at'),
            Arr::bool($d, 'usable'),
            Arr::intOrNull($d, 'usable_today'),
        );
    }
}
