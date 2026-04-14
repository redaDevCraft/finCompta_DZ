d<?php

namespace App\Services;

use App\Models\AiSuggestion;
use App\Models\Company;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ExpenseService
{
    public function __construct(
        protected TaxComputationService $tax,
        protected ComplianceEngine $compliance,
        protected JournalService $journal,
    ) {
    }

    public function confirm(Expense $expense): Expense
    {
        $expenseDate = $expense->expense_date instanceof Carbon
            ? $expense->expense_date
            : Carbon::parse($expense->expense_date);

        if ((float) $expense->total_ht <= 0) {
            abort(422, 'Le total HT doit être supérieur à zéro.');
        }

        if ((float) $expense->total_ttc < (float) $expense->total_ht) {
            abort(422, 'Le total TTC ne peut pas être inférieur au total HT.');
        }

        if (! $expenseDate || $expenseDate->lt(now()->subYear())) {
            abort(422, 'La date de dépense est invalide ou trop ancienne.');
        }

        if (! $expense->isEditable()) {
            abort(422, 'Cette dépense ne peut plus être confirmée.');
        }

        // Draft and post journal entry
        $journalEntry = $this->journal->draftPurchaseEntry($expense, $expense->company);
        $this->journal->post($journalEntry, auth()->user());

        $expense->update([
            'status' => 'posted',
            'posted_at' => now(),
            'posted_by' => auth()->id(),
            'journal_entry_id' => $journalEntry->id,
        ]);

        return $expense->fresh();
        // journal posting + status update here
        // $journalEntry = ...
        // $expense->update([...]);

        return $expense;
    }

    public function applyAiSuggestions(Expense $expense, array $acceptedFields): void
    {
        if (empty($acceptedFields)) {
            return;
        }

        DB::transaction(function () use ($expense, $acceptedFields) {
            $allowedColumns = [
                'reference',
                'expense_date',
                'due_date',
                'description',
                'total_ht',
                'total_vat',
                'total_ttc',
            ];

            $updates = [];

            foreach ($acceptedFields as $field => $value) {
                if (! in_array($field, $allowedColumns, true)) {
                    continue;
                }

                $updates[$field] = $value;

                AiSuggestion::query()
                    ->where('company_id', $expense->company_id)
                    ->where('source_type', 'expense')
                    ->where('source_id', $expense->id)
                    ->where('field_name', $field)
                    ->whereNull('accepted')
                    ->update([
                        'accepted' => true,
                        'final_value' => is_scalar($value) ? (string) $value : json_encode($value),
                    ]);
            }

            if (! empty($updates)) {
                $expense->update($updates);
            }
        });
    }
}