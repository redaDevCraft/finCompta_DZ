<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\BankTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lightweight shape used by the bank reconciliation unmatched-feed table.
 *
 * @property-read BankTransaction $resource
 */
final class BankTransactionListResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        /** @var BankTransaction $tx */
        $tx = $this->resource;

        return [
            'id' => $tx->id,
            'transaction_date' => optional($tx->transaction_date)->toDateString(),
            'value_date' => optional($tx->value_date)->toDateString(),
            'label' => $tx->label,
            'amount' => (float) $tx->amount,
            'direction' => $tx->direction,
            'balance_after' => $tx->balance_after !== null ? (float) $tx->balance_after : null,
            'reconcile_status' => $tx->reconcile_status,
            'bank_account' => $tx->relationLoaded('bankAccount') && $tx->bankAccount
                ? [
                    'id' => $tx->bankAccount->id,
                    'bank_name' => $tx->bankAccount->bank_name,
                    'account_number' => $tx->bankAccount->account_number,
                ]
                : null,
        ];
    }
}
