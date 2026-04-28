<?php

namespace App\Services;

use App\Models\Account;
use App\Models\BankTransaction;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\User;
use App\Support\Cache\DashboardCache;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReconciliationService
{
    public function __construct(
        protected JournalService $journal,
    ) {
    }

    public function suggestMatches(BankTransaction $tx, Company $company): Collection
    {
        $txDate = Carbon::parse($tx->transaction_date);
        $txAmount = round((float) $tx->amount, 2);
        $txLabel = mb_strtolower((string) $tx->label);

        $linkedEntryIds = BankTransaction::query()
            ->where('company_id', $company->id)
            ->whereNotNull('journal_entry_id')
            ->pluck('journal_entry_id');

        return JournalEntry::query()
            ->where('company_id', $company->id)
            ->where('status', 'posted')
            ->whereNotIn('id', $linkedEntryIds)
            ->with('lines')
            ->get()
            ->map(function (JournalEntry $entry) use ($txAmount, $txDate, $txLabel) {
                $entryAmount = round($this->resolveEntryAmount($entry), 2);
                $score = 0.0;

                $amountDiff = abs($entryAmount - $txAmount);

                if ($amountDiff <= 0.01) {
                    $score += 0.60;
                } elseif ($amountDiff <= 1.00) {
                    $score += 0.30;
                }

                $entryDate = Carbon::parse($entry->entry_date);
                $dayDiff = abs($entryDate->diffInDays($txDate));

                if ($dayDiff === 0) {
                    $score += 0.25;
                } elseif ($dayDiff <= 3) {
                    $score += 0.15;
                } elseif ($dayDiff <= 7) {
                    $score += 0.05;
                }

                $reference = trim((string) $entry->reference);

                if ($reference !== '' && str_contains($txLabel, mb_strtolower($reference))) {
                    $score += 0.15;
                }

                $score = min(1.0, round($score, 2));

                return [
                    'entry' => $entry,
                    'score' => $score,
                    'entry_amount' => $entryAmount,
                ];
            })
            ->filter(fn (array $candidate) => $candidate['score'] > 0)
            ->sortByDesc('score')
            ->take(10)
            ->values();
    }

    public function confirmMatch(BankTransaction $tx, JournalEntry $entry, User $user): void
    {
        DB::transaction(function () use ($tx, $entry, $user) {
            $tx->update([
                'reconcile_status' => 'matched',
                'journal_entry_id' => $entry->id,
                'matched_by' => $user->id,
                'matched_at' => now(),
            ]);

            if ($entry->status === 'draft') {
                $this->journal->post($entry->fresh('lines'), $user);
            }
        });

        // Matching may post a draft entry → shifts cash, AR/AP, result.
        DashboardCache::forget($tx->company_id);
    }

    public function manualPost(BankTransaction $tx, string $accountId, ?string $description, User $user): void
    {
        DB::transaction(function () use ($tx, $accountId, $description, $user) {
            $company = $tx->company()->firstOrFail();
            $period = $this->journal->getOrCreatePeriod($company, Carbon::parse($tx->transaction_date));

            $bankAccount = $this->findBankGlAccount($company);
            $targetAccount = Account::query()
                ->where('company_id', $company->id)
                ->where('id', $accountId)
                ->where('is_active', true)
                ->firstOrFail();

            $entryDescription = $this->buildManualEntryDescription($tx, $targetAccount, $description);
            $counterpartLineDescription = $this->resolveCounterpartLineDescription($targetAccount, $tx);
            $bankLineDescription = 'Mouvement bancaire';

            $entry = JournalEntry::create([
                'company_id' => $company->id,
                'period_id' => $period->id,
                'entry_date' => $tx->transaction_date,
                'journal_code' => 'BQ',
                'reference' => null,
                'description' => $entryDescription,
                'status' => 'posted',
                'source_type' => 'bank_txn',
                'source_id' => $tx->id,
                'posted_at' => now(),
                'posted_by' => $user->id,
            ]);

            $amount = round((float) $tx->amount, 2);

            if ($tx->direction === 'credit') {
                $entry->lines()->create([
                    'account_id' => $bankAccount->id,
                    'contact_id' => null,
                    'debit' => $amount,
                    'credit' => 0,
                    'description' => $bankLineDescription,
                    'sort_order' => 0,
                ]);

                $entry->lines()->create([
                    'account_id' => $targetAccount->id,
                    'contact_id' => null,
                    'debit' => 0,
                    'credit' => $amount,
                    'description' => $counterpartLineDescription,
                    'sort_order' => 1,
                ]);
            } elseif ($tx->direction === 'debit') {
                $entry->lines()->create([
                    'account_id' => $targetAccount->id,
                    'contact_id' => null,
                    'debit' => $amount,
                    'credit' => 0,
                    'description' => $counterpartLineDescription,
                    'sort_order' => 0,
                ]);

                $entry->lines()->create([
                    'account_id' => $bankAccount->id,
                    'contact_id' => null,
                    'debit' => 0,
                    'credit' => $amount,
                    'description' => $bankLineDescription,
                    'sort_order' => 1,
                ]);
            } else {
                abort(422, 'Sens de transaction bancaire invalide');
            }

            $entry->load('lines');

            if (! $entry->isBalanced()) {
                abort(422, 'Écriture comptable déséquilibrée');
            }

            $tx->update([
                'reconcile_status' => 'manually_posted',
                'journal_entry_id' => $entry->id,
                'matched_by' => $user->id,
                'matched_at' => now(),
            ]);
        });

        // Manual post creates a brand-new journal entry → dashboard stale.
        DashboardCache::forget($tx->company_id);
    }

    public function exclude(BankTransaction $tx): void
    {
        $tx->update([
            'reconcile_status' => 'excluded',
        ]);
    }

    private function resolveEntryAmount(JournalEntry $entry): float
    {
        $entry->loadMissing('lines');

        $totalDebit = round((float) $entry->lines->sum('debit'), 2);
        $totalCredit = round((float) $entry->lines->sum('credit'), 2);

        return max($totalDebit, $totalCredit);
    }

    private function findBankGlAccount(Company $company): Account
    {
        return Account::query()
            ->where('company_id', $company->id)
            ->where('code', 'LIKE', '512%')
            ->where('is_active', true)
            ->orderBy('code')
            ->firstOrFail();
    }

    private function buildManualEntryDescription(BankTransaction $tx, Account $targetAccount, ?string $description): string
    {
        $provided = trim((string) ($description ?? ''));
        if ($provided !== '') {
            return $provided;
        }

        $direction = $this->resolvePartyDirection($targetAccount, $tx);
        $counterparty = $this->extractCounterparty($tx);

        return trim(sprintf('Règlement %s %s', $direction, $counterparty));
    }

    private function resolveCounterpartLineDescription(Account $targetAccount, BankTransaction $tx): string
    {
        return match ($this->resolvePartyDirection($targetAccount, $tx)) {
            'client' => 'Règlement client',
            'fournisseur' => 'Règlement fournisseur',
            default => 'Mouvement bancaire',
        };
    }

    private function resolvePartyDirection(Account $targetAccount, BankTransaction $tx): string
    {
        $code = (string) $targetAccount->code;
        if (str_starts_with($code, '41')) {
            return 'client';
        }

        if (str_starts_with($code, '40')) {
            return 'fournisseur';
        }

        if ($tx->direction === 'credit') {
            return 'client';
        }

        if ($tx->direction === 'debit') {
            return 'fournisseur';
        }

        return 'banque';
    }

    private function extractCounterparty(BankTransaction $tx): string
    {
        $label = trim((string) $tx->label);
        if ($label === '') {
            return 'banque';
        }

        return mb_substr($label, 0, 80);
    }
}