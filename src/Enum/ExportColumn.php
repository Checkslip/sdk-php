<?php

declare(strict_types=1);

namespace Checkslip\Enum;

/**
 * Columns you can pick for a slip export CSV.
 *
 * latency_ms, upstream_ms and checked_elsewhere are deliberately absent:
 * they left the Checkslip API, so they are not offered
 * as export columns.
 */
enum ExportColumn: string
{
    case RequestId = 'request_id';
    case Outcome = 'outcome';
    case Status = 'status';
    case CheckedAt = 'checked_at';
    case Channel = 'channel';
    case ApiKeyId = 'api_key_id';
    case ClientReference = 'client_reference';
    case DuplicateOfRequestId = 'duplicate_of.request_id';
    case ChargeConfirmed = 'charge_confirmed';
    case Charged = 'charged';
    case FreeReason = 'free_reason';
    case SlipTransRef = 'slip.trans_ref';
    case SlipTransferredAt = 'slip.transferred_at';
    case SlipAmountSatang = 'slip.amount.satang';
    case SlipAmountCurrency = 'slip.amount.currency';
    case SlipSenderBankCode = 'slip.sender.bank.code';
    case SlipSenderBankBotCode = 'slip.sender.bank.bot_code';
    case SlipSenderNameTh = 'slip.sender.name_th';
    case SlipSenderNameEn = 'slip.sender.name_en';
    case SlipSenderAccount = 'slip.sender.account';
    case SlipReceiverBankCode = 'slip.receiver.bank.code';
    case SlipReceiverBankBotCode = 'slip.receiver.bank.bot_code';
    case SlipReceiverNameTh = 'slip.receiver.name_th';
    case SlipReceiverNameEn = 'slip.receiver.name_en';
    case SlipReceiverAccount = 'slip.receiver.account';
}
