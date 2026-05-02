<?php

namespace App\Enums;

enum AiIntent: string
{
    case INVOICES  = 'invoices';
    case EXPENSES  = 'expenses';
    case CASH_FLOW = 'cash_flow';
    case VAT       = 'vat';
    case LEDGER    = 'ledger';
    case CLIENTS   = 'clients';
    case OVERVIEW  = 'overview';
    case UNKNOWN   = 'unknown';
}