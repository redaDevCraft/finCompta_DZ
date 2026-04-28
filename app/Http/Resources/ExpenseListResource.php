<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lightweight shape used by the expenses index table.
 *
 * @property-read Expense $resource
 */
final class ExpenseListResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        /** @var Expense $expense */
        $expense = $this->resource;

        return [
            'id' => $expense->id,
            'reference' => $expense->reference,
            'description' => $expense->description,
            'status' => $expense->status,
            'expense_date' => optional($expense->expense_date)->toDateString(),
            'created_at' => optional($expense->created_at)->toDateTimeString(),
            'due_date' => optional($expense->due_date)->toDateString(),
            'total_ht' => (float) $expense->total_ht,
            'total_vat' => (float) $expense->total_vat,
            'total_ttc' => (float) $expense->total_ttc,
            'contact' => $expense->relationLoaded('contact') && $expense->contact
                ? [
                    'id' => $expense->contact->id,
                    'display_name' => $expense->contact->display_name,
                ]
                : null,
            'account' => $expense->relationLoaded('account') && $expense->account
                ? [
                    'id' => $expense->account->id,
                    'code' => $expense->account->code,
                    'label' => $expense->account->label,
                ]
                : null,
        ];
    }
}
