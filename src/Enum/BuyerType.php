<?php

declare(strict_types=1);

namespace Checkslip\Enum;

/**
 * Buyer on a receipt.
 */
enum BuyerType: string
{
    case Person = 'PERSON';
    case Company = 'COMPANY';
}
