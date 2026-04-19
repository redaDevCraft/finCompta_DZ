<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Company;
use App\Models\Expense;
use App\Models\FiscalPeriod;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\User;
use Carbon\Carbon;

class JournalService
{
    public function getOrCreatePeriod(Company $company, Carbon $date): FiscalPeriod
    {
        $period = FiscalPeriod::where('company_id', $company->id)
            ->where('year', $date->year)
            ->where('month', $date->month)
            ->first();

        if ($period) {
            if ($period->status === 'locked') {
                abort(422, 'Période clôturée — contactez votre comptable');
            }

            return $period;
        }

        return FiscalPeriod::create([
            'company_id' => $company->id,
            'year' => $date->year,
            'month' => $date->month,
            'status' => 'open',
            'locked_at' => null,
            'locked_by' => null,
        ]);
    }

    public function draftSalesEntry(Invoice $invoice, Company $company): JournalEntry
    {
        $invoice->loadMissing(['vatBuckets', 'contact']);

        $period = $this->getOrCreatePeriod($company, Carbon::parse($invoice->issue_date));

        $entry = JournalEntry::create([
            'company_id' => $company->id,
            'period_id' => $period->id,
            'entry_date' => $invoice->issue_date,
            'journal_code' => 'VT',
            'reference' => $invoice->invoice_number,
            'description' => 'Facture client ' . ($invoice->invoice_number ?? $invoice->id),
            'status' => 'draft',
            'source_type' => 'invoice',
            'source_id' => $invoice->id,
            'posted_at' => null,
            'posted_by' => null,
        ]);

        $clientAccount = $this->findAccount($company, '411');
        $revenueAccount = $this->resolveSalesRevenueAccount($invoice, $company);
        $vatCollectedAccount = $this->findAccount($company, '4451');

        $contactId = $invoice->contact_id;

        $totalDebit = 0.0;
        $sortOrder = 0;

        foreach ($invoice->vatBuckets as $bucket) {
            $baseHt = (float) $bucket->base_ht;
            $vatAmount = (float) $bucket->vat_amount;
            $lineTtc = round($baseHt + $vatAmount, 2);

            $entry->lines()->create([
                'account_id' => $clientAccount->id,
                'contact_id' => $contactId,
                'debit' => $lineTtc,
                'credit' => 0,
                'description' => 'Créance client',
                'sort_order' => $sortOrder++,
            ]);

            $entry->lines()->create([
                'account_id' => $revenueAccount->id,
                'contact_id' => $contactId,
                'debit' => 0,
                'credit' => $baseHt,
                'description' => 'Produit HT',
                'sort_order' => $sortOrder++,
            ]);

            if ($vatAmount > 0) {
                $entry->lines()->create([
                    'account_id' => $vatCollectedAccount->id,
                    'contact_id' => $contactId,
                    'debit' => 0,
                    'credit' => $vatAmount,
                    'description' => 'TVA collectée',
                    'sort_order' => $sortOrder++,
                ]);
            }

            $totalDebit += $lineTtc;
        }

        $entry->load('lines');

        if (! $entry->isBalanced()) {
            abort(422, 'Écriture comptable déséquilibrée');
        }

        $invoice->update([
            'journal_entry_id' => $entry->id,
        ]);

        return $entry->fresh('lines');
    }

    public function draftPurchaseEntry(Expense $expense, Company $company): JournalEntry
    {
        $expense->loadMissing('lines');

        $period = $this->getOrCreatePeriod($company, Carbon::parse($expense->expense_date));

        $entry = JournalEntry::create([
            'company_id' => $company->id,
            'period_id' => $period->id,
            'entry_date' => $expense->expense_date,
            'journal_code' => 'AC',
            'reference' => $expense->reference,
            'description' => $expense->description ?: 'Charge fournisseur',
            'status' => 'draft',
            'source_type' => 'expense',
            'source_id' => $expense->id,
            'posted_at' => null,
            'posted_by' => null,
        ]);

        $supplierAccount = $this->findAccount($company, '401');
        $vatDeductibleAccount = $this->findAccount($company, '4456');

        $contactId = $expense->contact_id;
        $sortOrder = 0;

        // When detailed lines exist we post one debit per expense account to
        // keep the analytical breakdown of charges accurate (and the VAT
        // purchase report per-rate). Otherwise we fall back to the header totals.
        if ($expense->lines->isNotEmpty()) {
            $groupedHt = [];
            $groupedVat = 0.0;

            foreach ($expense->lines as $line) {
                $accountId = $line->account_id ?: $expense->account_id;
                $account = $accountId
                    ? Account::where('company_id', $company->id)
                        ->where('id', $accountId)
                        ->where('is_active', true)
                        ->first()
                    : null;
                $account ??= $this->findAccount($company, '601');

                $groupedHt[$account->id] = ($groupedHt[$account->id] ?? 0) + (float) $line->amount_ht;
                $groupedVat += (float) $line->amount_vat;
            }

            foreach ($groupedHt as $accountId => $amountHt) {
                $entry->lines()->create([
                    'account_id' => $accountId,
                    'contact_id' => $contactId,
                    'debit' => round($amountHt, 2),
                    'credit' => 0,
                    'description' => 'Charge HT',
                    'sort_order' => $sortOrder++,
                ]);
            }

            if ($groupedVat > 0) {
                $entry->lines()->create([
                    'account_id' => $vatDeductibleAccount->id,
                    'contact_id' => $contactId,
                    'debit' => round($groupedVat, 2),
                    'credit' => 0,
                    'description' => 'TVA déductible',
                    'sort_order' => $sortOrder++,
                ]);
            }
        } else {
            $expenseAccount = $expense->account_id
                ? Account::where('company_id', $company->id)
                    ->where('id', $expense->account_id)
                    ->where('is_active', true)
                    ->firstOrFail()
                : $this->findAccount($company, '601');

            $entry->lines()->create([
                'account_id' => $expenseAccount->id,
                'contact_id' => $contactId,
                'debit' => (float) $expense->total_ht,
                'credit' => 0,
                'description' => 'Charge HT',
                'sort_order' => $sortOrder++,
            ]);

            if ((float) $expense->total_vat > 0) {
                $entry->lines()->create([
                    'account_id' => $vatDeductibleAccount->id,
                    'contact_id' => $contactId,
                    'debit' => (float) $expense->total_vat,
                    'credit' => 0,
                    'description' => 'TVA déductible',
                    'sort_order' => $sortOrder++,
                ]);
            }
        }

        $entry->lines()->create([
            'account_id' => $supplierAccount->id,
            'contact_id' => $contactId,
            'debit' => 0,
            'credit' => (float) $expense->total_ttc,
            'description' => 'Dette fournisseur',
            'sort_order' => $sortOrder++,
        ]);

        $entry->load('lines');

        if (! $entry->isBalanced()) {
            abort(422, 'Écriture comptable déséquilibrée');
        }

        $expense->update([
            'journal_entry_id' => $entry->id,
        ]);

        return $entry->fresh('lines');
    }

    public function post(JournalEntry $entry, User $user): void
    {
        $entry->loadMissing('lines');

        if (! $entry->isPostable()) {
            abort(422, 'Écriture non équilibrée ou déjà comptabilisée');
        }

        $entry->update([
            'status' => 'posted',
            'posted_at' => now(),
            'posted_by' => $user->id,
        ]);
    }

    /**
     * Create a counter-entry reversing every line of $original on today's date
     * (or a caller-provided date). The reversal is posted immediately when the
     * original was posted, otherwise kept as a draft.
     */
    public function reverseEntry(JournalEntry $original, Company $company, User $user, ?Carbon $date = null, ?string $reason = null): JournalEntry
    {
        $original->loadMissing('lines');

        $reverseDate = $date ?? Carbon::now();
        $period = $this->getOrCreatePeriod($company, $reverseDate);

        $reversal = JournalEntry::create([
            'company_id' => $company->id,
            'period_id' => $period->id,
            'entry_date' => $reverseDate->toDateString(),
            'journal_code' => $original->journal_code,
            'reference' => 'EXTOURNE — '.($original->reference ?? $original->id),
            'description' => $reason ?: ('Extourne de '.($original->reference ?? $original->id)),
            'status' => 'draft',
            'source_type' => 'reversal',
            'source_id' => $original->id,
            'posted_at' => null,
            'posted_by' => null,
        ]);

        $sortOrder = 0;
        foreach ($original->lines as $line) {
            $reversal->lines()->create([
                'account_id' => $line->account_id,
                'contact_id' => $line->contact_id,
                'debit' => (float) $line->credit,
                'credit' => (float) $line->debit,
                'description' => 'Extourne — '.$line->description,
                'sort_order' => $sortOrder++,
            ]);
        }

        $reversal->load('lines');

        if (! $reversal->isBalanced()) {
            abort(422, 'Écriture d\'extourne déséquilibrée');
        }

        if ($original->status === 'posted') {
            $this->post($reversal, $user);
        }

        return $reversal->fresh('lines');
    }

    private function findAccount(Company $company, string $code): Account
    {
        return Account::where('company_id', $company->id)
            ->where('code', 'LIKE', $code . '%')
            ->where('is_active', true)
            ->orderBy('code')
            ->firstOrFail();
    }

    private function resolveSalesRevenueAccount(Invoice $invoice, Company $company): Account
    {
        $lineAccountIds = $invoice->lines()
            ->whereNotNull('account_id')
            ->pluck('account_id')
            ->filter()
            ->unique()
            ->values();

        if ($lineAccountIds->count() === 1) {
            return Account::where('company_id', $company->id)
                ->whereIn('id', $lineAccountIds)
                ->where('is_active', true)
                ->firstOrFail();
        }

        return $this->findAccount($company, '701');
    }
}