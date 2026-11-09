<?php

declare(strict_types=1);

namespace Checkslip\Resource;

use Checkslip\Internal\Arr;
use Checkslip\Model\SupportedBank;

final class Banks extends AbstractResource
{
    /**
     * The banks Checkslip can look transfers up with. Needs no API key scope.
     *
     * @return list<SupportedBank>
     */
    public function list(): array
    {
        return array_map(SupportedBank::fromArray(...), Arr::objects($this->fetch('/api/banks'), 'items'));
    }
}
