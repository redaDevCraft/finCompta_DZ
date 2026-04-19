<?php

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
    ) {}

    public function confirm(Expense $expense, Company $company, User $user): array
    {
        if ($expense->status !== 'draft') {
            abort(422, 'Dépense déjà confirmée');
        }

        $warnings = $this->verifyTaxConsistency($expense);

        DB::transaction(function () use ($expense, $company) {
            $expense->update([
                'status' => 'confirmed',
            ]);

            $this->journal->draftPurchaseEntry($expense->fresh(), $company);
        });

        return [
            'success' => true,
            'warnings' => $warnings,
        ];
    }

    /**
     * Soft checks on HT / TVA / TTC coherence and missing mandatory fields.
     *
     * @return string[]
     */
    private function verifyTaxConsistency(Expense $expense): array
    {
        $warnings = [];

        $ht = (float) $expense->total_ht;
        $vat = (float) $expense->total_vat;
        $ttc = (float) $expense->total_ttc;

        if (abs(($ht + $vat) - $ttc) > 0.01) {
            $warnings[] = sprintf(
                'Incohérence HT + TVA ≠ TTC (%.2f + %.2f = %.2f, attendu %.2f).',
                $ht, $vat, $ht + $vat, $ttc
            );
        }

        if ($ttc > 0 && $vat === 0.0) {
            $warnings[] = 'Aucune TVA saisie — vérifier l’exonération.';
        }

        if ($expense->contact_id === null) {
            $warnings[] = 'Aucun fournisseur associé à la dépense.';
        }

        if (empty($expense->account_id)) {
            $warnings[] = 'Aucun compte de charge sélectionné.';
        }

        return $warnings;
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
