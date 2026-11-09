<?php

declare(strict_types=1);

namespace Checkslip\Enum;

/**
 * How an order was paid.
 */
enum PaymentMethod: string
{
    case Card = 'CARD';
    case Promptpay = 'PROMPTPAY';
}
