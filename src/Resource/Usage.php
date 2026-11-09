<?php

declare(strict_types=1);

namespace Checkslip\Resource;

use Checkslip\Enum\UsageInterval;
use Checkslip\Internal\Query;
use Checkslip\Model\Usage as UsageModel;

/**
 * Credit usage over time.
 */
final class Usage extends AbstractResource
{
    /**
     * Lookups and credits used per day or month between two dates (Asia/Bangkok).
     */
    public function get(
        \DateTimeInterface|string $from,
        \DateTimeInterface|string $to,
        UsageInterval|string $interval = UsageInterval::Day,
    ): UsageModel {
        [$data] = $this->transport->json('GET', '/api/usage', [
            'interval' => $interval instanceof UsageInterval ? $interval->value : $interval,
            'from' => Query::date($from),
            'to' => Query::date($to),
        ]);

        return UsageModel::fromArray($data);
    }
}
