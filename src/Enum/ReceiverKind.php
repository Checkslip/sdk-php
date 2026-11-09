<?php

declare(strict_types=1);

namespace Checkslip\Enum;

/**
 * Who received the transfer.
 */
enum ReceiverKind: string
{
    case BankAccount = 'BANK_ACCOUNT';
    case BillPayment = 'BILL_PAYMENT';
    case NotBankAccount = 'NOT_BANK_ACCOUNT';
}
