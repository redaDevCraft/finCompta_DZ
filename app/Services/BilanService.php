<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Company;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Computes a Bilan (balance sheet) aligned on the Algerian SCF / PCN chart
 * of accounts, using only posted journal entries up to a given date.
 *
 * Classification rules (primarily by class, refined by account.type):
 *   1 → Capitaux propres (10-13) or Passifs non-courants (15-19)
 *   2 → Actif non-courant (immobilisations)
 *   3 → Actif courant (stocks)
 *   4 → Tiers → split actif/passif by account.type
 *   5 → Trésorerie → split actif/passif by account.type
 *   6 → Charges (contributes to résultat)
 *   7 → Produits (contributes to résultat)
 */
class BilanService
{
    public function compute(Company $company, string|Carbon $asOfDate): array
    {
        $asOf = Carbon::parse($asOfDate)->toDateString();

        // Aggregate posted movements per account up to $asOf.
        $rows = DB::table('accounts')
            ->leftJoin('journal_lines', 'journal_lines.account_id', '=', 'accounts.id')
            ->leftJoin('journal_entries', function ($join) use ($company, $asOf) {
                $join->on('journal_entries.id', '=', 'journal_lines.journal_entry_id')
                    ->where('journal_entries.company_id', '=', $company->id)
                    ->where('journal_entries.status', '=', 'posted')
                    ->whereDate('journal_entries.entry_date', '<=', $asOf);
            })
            ->where('accounts.company_id', $company->id)
            ->groupBy('accounts.id', 'accounts.code', 'accounts.label', 'accounts.class', 'accounts.type')
            ->orderBy('accounts.code')
            ->selectRaw('
                accounts.id, accounts.code, accounts.label, accounts.class, accounts.type,
                COALESCE(SUM(journal_lines.debit), 0)  AS total_debit,
                COALESCE(SUM(journal_lines.credit), 0) AS total_credit
            ')
            ->get();

        $accounts = $rows->map(function ($r) {
            $debit = (float) $r->total_debit;
            $credit = (float) $r->total_credit;

            return [
                'id' => $r->id,
                'code' => $r->code,
                'label' => $r->label,
                'class' => (int) $r->class,
                'type' => $r->type,
                'debit' => round($debit, 2),
                'credit' => round($credit, 2),
                'balance' => round($debit - $credit, 2), // raw D-C
            ];
        });

        $resultatNet = $this->computeResultatNet($accounts);

        $actif = $this->buildActif($accounts);
        $passif = $this->buildPassif($accounts, $resultatNet);

        $totalActif = collect($actif)->sum(fn ($section) => $section['total']);
        $totalPassif = collect($passif)->sum(fn ($section) => $section['total']);

        return [
            'as_of_date' => $asOf,
            'company' => [
                'id' => $company->id,
                'raison_sociale' => $company->raison_sociale ?? null,
                'nif' => $company->nif ?? null,
                'rc' => $company->rc ?? null,
                'currency' => $company->currency ?? 'DZD',
            ],
            'actif' => $actif,
            'passif' => $passif,
            'totals' => [
                'actif' => round($totalActif, 2),
                'passif' => round($totalPassif, 2),
                'difference' => round($totalActif - $totalPassif, 2),
                'resultat_net' => $resultatNet,
            ],
        ];
    }

    private function computeResultatNet(Collection $accounts): float
    {
        // Résultat = produits (class 7: credit balance) - charges (class 6: debit balance)
        $charges = $accounts
            ->where('class', 6)
            ->sum(fn ($a) => max($a['balance'], 0)); // debit-side

        $produits = $accounts
            ->where('class', 7)
            ->sum(fn ($a) => max(-$a['balance'], 0)); // credit-side (balance is D-C, flip sign)

        return round($produits - $charges, 2);
    }

    /**
     * Assets side — only positive contributions appear (a liability with a
     * debit balance does not cross over; treated as anomaly in `autres`).
     */
    private function buildActif(Collection $accounts): array
    {
        $immoIncorp = $this->filterRubrique(
            $accounts,
            fn ($a) => str_starts_with($a['code'], '20'),
            'asset_debit'
        );

        $immoCorp = $this->filterRubrique(
            $accounts,
            fn ($a) => str_starts_with($a['code'], '21') || str_starts_with($a['code'], '22')
                   || str_starts_with($a['code'], '23') || str_starts_with($a['code'], '24')
                   || str_starts_with($a['code'], '25'),
            'asset_debit'
        );

        $immoFin = $this->filterRubrique(
            $accounts,
            fn ($a) => str_starts_with($a['code'], '26') || str_starts_with($a['code'], '27'),
            'asset_debit'
        );

        $stocks = $this->filterRubrique(
            $accounts,
            fn ($a) => $a['class'] === 3,
            'asset_debit'
        );

        $creances = $this->filterRubrique(
            $accounts,
            fn ($a) => $a['class'] === 4
                && in_array($a['type'], ['asset', 'vat_deductible'], true),
            'asset_debit'
        );

        $tresorerie = $this->filterRubrique(
            $accounts,
            fn ($a) => $a['class'] === 5 && $a['type'] === 'asset',
            'asset_debit'
        );

        return [
            [
                'key' => 'actif_non_courant',
                'label' => 'Actif non-courant',
                'rubriques' => array_values(array_filter([
                    $this->rubrique('Immobilisations incorporelles', $immoIncorp),
                    $this->rubrique('Immobilisations corporelles', $immoCorp),
                    $this->rubrique('Immobilisations financières', $immoFin),
                ], fn ($r) => count($r['lines']) > 0 || $r['total'] != 0)),
                'total' => round(
                    $this->sumBalance($immoIncorp) +
                    $this->sumBalance($immoCorp) +
                    $this->sumBalance($immoFin),
                    2
                ),
            ],
            [
                'key' => 'actif_courant',
                'label' => 'Actif courant',
                'rubriques' => array_values(array_filter([
                    $this->rubrique('Stocks et en-cours', $stocks),
                    $this->rubrique('Clients et comptes rattachés', $creances),
                    $this->rubrique('Trésorerie — Actif', $tresorerie),
                ], fn ($r) => count($r['lines']) > 0 || $r['total'] != 0)),
                'total' => round(
                    $this->sumBalance($stocks) +
                    $this->sumBalance($creances) +
                    $this->sumBalance($tresorerie),
                    2
                ),
            ],
        ];
    }

    /**
     * Liabilities + equity side.
     */
    private function buildPassif(Collection $accounts, float $resultatNet): array
    {
        $capitaux = $this->filterRubrique(
            $accounts,
            fn ($a) => $a['class'] === 1 && $a['type'] === 'equity',
            'liability_credit'
        );

        $dettesLt = $this->filterRubrique(
            $accounts,
            fn ($a) => $a['class'] === 1 && $a['type'] === 'liability',
            'liability_credit'
        );

        $fournisseurs = $this->filterRubrique(
            $accounts,
            fn ($a) => str_starts_with($a['code'], '40')
                && in_array($a['type'], ['liability'], true),
            'liability_credit'
        );

        $fiscalSocial = $this->filterRubrique(
            $accounts,
            fn ($a) => ($a['class'] === 4)
                && (
                    str_starts_with($a['code'], '42') ||
                    str_starts_with($a['code'], '43') ||
                    (str_starts_with($a['code'], '44') && $a['type'] !== 'vat_deductible')
                ),
            'liability_credit'
        );

        $autresDettes = $this->filterRubrique(
            $accounts,
            fn ($a) => $a['class'] === 4
                && in_array($a['type'], ['liability', 'vat_collected'], true)
                && ! str_starts_with($a['code'], '40')
                && ! str_starts_with($a['code'], '42')
                && ! str_starts_with($a['code'], '43')
                && ! str_starts_with($a['code'], '44'),
            'liability_credit'
        );

        $tresoreriePassif = $this->filterRubrique(
            $accounts,
            fn ($a) => $a['class'] === 5 && $a['type'] === 'liability',
            'liability_credit'
        );

        $capitauxTotal = $this->sumBalance($capitaux);

        return [
            [
                'key' => 'capitaux_propres',
                'label' => 'Capitaux propres',
                'rubriques' => array_values(array_filter([
                    $this->rubrique('Capital et réserves', $capitaux),
                    [
                        'label' => 'Résultat net de l’exercice',
                        'lines' => [],
                        'total' => $resultatNet,
                    ],
                ], fn ($r) => count($r['lines']) > 0 || $r['total'] != 0)),
                'total' => round($capitauxTotal + $resultatNet, 2),
            ],
            [
                'key' => 'passif_non_courant',
                'label' => 'Passif non-courant',
                'rubriques' => array_values(array_filter([
                    $this->rubrique('Emprunts et dettes à long terme', $dettesLt),
                ], fn ($r) => count($r['lines']) > 0 || $r['total'] != 0)),
                'total' => round($this->sumBalance($dettesLt), 2),
            ],
            [
                'key' => 'passif_courant',
                'label' => 'Passif courant',
                'rubriques' => array_values(array_filter([
                    $this->rubrique('Fournisseurs et comptes rattachés', $fournisseurs),
                    $this->rubrique('Dettes fiscales et sociales', $fiscalSocial),
                    $this->rubrique('Autres dettes', $autresDettes),
                    $this->rubrique('Trésorerie — Passif (découvert)', $tresoreriePassif),
                ], fn ($r) => count($r['lines']) > 0 || $r['total'] != 0)),
                'total' => round(
                    $this->sumBalance($fournisseurs) +
                    $this->sumBalance($fiscalSocial) +
                    $this->sumBalance($autresDettes) +
                    $this->sumBalance($tresoreriePassif),
                    2
                ),
            ],
        ];
    }

    private function filterRubrique(Collection $accounts, callable $filter, string $side): Collection
    {
        return $accounts
            ->filter($filter)
            ->map(function ($a) use ($side) {
                // Normalize signed balance for display:
                //   asset_debit     → debit balance is positive
                //   liability_credit → credit balance is positive
                $amount = $side === 'asset_debit' ? $a['balance'] : -$a['balance'];

                return [
                    'id' => $a['id'],
                    'code' => $a['code'],
                    'label' => $a['label'],
                    'amount' => round($amount, 2),
                ];
            })
            // Keep only lines with a non-zero balance (or always keep, then filter in UI?)
            ->filter(fn ($a) => abs($a['amount']) > 0.01)
            ->values();
    }

    private function rubrique(string $label, Collection $lines): array
    {
        return [
            'label' => $label,
            'lines' => $lines->values()->all(),
            'total' => round($lines->sum('amount'), 2),
        ];
    }

    private function sumBalance(Collection $lines): float
    {
        return (float) $lines->sum('amount');
    }
}
