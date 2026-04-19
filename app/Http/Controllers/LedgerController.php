<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Services\JournalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class LedgerController extends Controller
{
    public function journal(Request $request): Response
    {
        $company = app('currentCompany');

        $entries = JournalEntry::query()
            ->with([
                'period:id,year,month,status',
                'lines' => function ($query) {
                    $query->with([
                        'account:id,code,label',
                        'contact:id,display_name',
                    ])->orderBy('sort_order');
                },
            ])
            ->where('company_id', $company->id)
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->string('status')->toString());
            })
            ->when($request->filled('journal_code'), function ($query) use ($request) {
                $query->where('journal_code', $request->string('journal_code')->toString());
            })
            ->when($request->filled('date_from'), function ($query) use ($request) {
                $query->whereDate('entry_date', '>=', $request->string('date_from')->toString());
            })
            ->when($request->filled('date_to'), function ($query) use ($request) {
                $query->whereDate('entry_date', '<=', $request->string('date_to')->toString());
            })
            ->orderByDesc('entry_date')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString()
            ->through(function (JournalEntry $entry) {
                $totalDebit = (float) $entry->lines->sum('debit');
                $totalCredit = (float) $entry->lines->sum('credit');

                return [
                    'id' => $entry->id,
                    'entry_date' => optional($entry->entry_date)?->toDateString(),
                    'journal_code' => $entry->journal_code,
                    'reference' => $entry->reference,
                    'description' => $entry->description,
                    'status' => $entry->status,
                    'source_type' => $entry->source_type,
                    'source_id' => $entry->source_id,
                    'posted_at' => optional($entry->posted_at)?->toDateTimeString(),
                    'period' => $entry->period ? [
                        'id' => $entry->period->id,
                        'year' => $entry->period->year,
                        'month' => $entry->period->month,
                        'status' => $entry->period->status,
                    ] : null,
                    'lines' => $entry->lines->map(function ($line) {
                        return [
                            'id' => $line->id,
                            'description' => $line->description,
                            'debit' => (float) $line->debit,
                            'credit' => (float) $line->credit,
                            'sort_order' => $line->sort_order,
                            'account' => $line->account ? [
                                'id' => $line->account->id,
                                'code' => $line->account->code,
                                'label' => $line->account->label,
                            ] : null,
                            'contact' => $line->contact ? [
                                'id' => $line->contact->id,
                                'display_name' => $line->contact->display_name,
                            ] : null,
                        ];
                    })->values(),
                    'totals' => [
                        'debit' => $totalDebit,
                        'credit' => $totalCredit,
                        'balanced' => abs($totalDebit - $totalCredit) < 0.0001,
                    ],
                ];
            });

        $journalOptions = Journal::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->orderBy('position')
            ->orderBy('code')
            ->get(['code', 'label'])
            ->map(fn ($j) => [
                'value' => $j->code,
                'label' => $j->code.' - '.$j->label,
            ])
            ->values();

        return Inertia::render('Ledger/Journal', [
            'entries' => $entries,
            'filters' => [
                'status' => $request->input('status', ''),
                'journal_code' => $request->input('journal_code', ''),
                'date_from' => $request->input('date_from', ''),
                'date_to' => $request->input('date_to', ''),
            ],
            'journalOptions' => $journalOptions,
            'statusOptions' => [
                ['value' => 'draft', 'label' => 'Brouillon'],
                ['value' => 'posted', 'label' => 'Validée'],
                ['value' => 'reversed', 'label' => 'Extournée'],
            ],
        ]);
    }

    public function accountLedger(Request $request): Response
    {
        $company = app('currentCompany');

        $accounts = Account::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'label', 'class', 'type']);

        $selectedAccountId = $request->input('account_id');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $includeDraft = $request->boolean('include_draft');

        $account = null;
        $openingBalance = 0.0;
        $rows = collect();
        $totals = ['debit' => 0.0, 'credit' => 0.0, 'balance' => 0.0];

        if ($selectedAccountId) {
            $account = $accounts->firstWhere('id', $selectedAccountId);
        }

        if ($account) {
            $statusFilter = $includeDraft ? ['draft', 'posted'] : ['posted'];

            $openingQuery = JournalLine::query()
                ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
                ->where('journal_entries.company_id', $company->id)
                ->where('journal_lines.account_id', $account->id)
                ->whereIn('journal_entries.status', $statusFilter);

            if ($dateFrom) {
                $openingQuery->whereDate('journal_entries.entry_date', '<', $dateFrom);
            } else {
                $openingQuery->whereRaw('1 = 0');
            }

            $opening = $openingQuery
                ->selectRaw('COALESCE(SUM(journal_lines.debit), 0) as total_debit')
                ->selectRaw('COALESCE(SUM(journal_lines.credit), 0) as total_credit')
                ->first();

            $openingBalance = (float) ($opening->total_debit ?? 0) - (float) ($opening->total_credit ?? 0);

            $linesQuery = JournalLine::query()
                ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
                ->leftJoin('contacts', 'contacts.id', '=', 'journal_lines.contact_id')
                ->where('journal_entries.company_id', $company->id)
                ->where('journal_lines.account_id', $account->id)
                ->whereIn('journal_entries.status', $statusFilter)
                ->when($dateFrom, fn ($q) => $q->whereDate('journal_entries.entry_date', '>=', $dateFrom))
                ->when($dateTo, fn ($q) => $q->whereDate('journal_entries.entry_date', '<=', $dateTo))
                ->orderBy('journal_entries.entry_date')
                ->orderBy('journal_entries.created_at')
                ->orderBy('journal_lines.sort_order')
                ->select([
                    'journal_lines.id',
                    'journal_lines.debit',
                    'journal_lines.credit',
                    'journal_lines.description as line_description',
                    'journal_entries.id as entry_id',
                    'journal_entries.entry_date',
                    'journal_entries.journal_code',
                    'journal_entries.reference',
                    'journal_entries.description as entry_description',
                    'journal_entries.status',
                    'contacts.display_name as contact_name',
                ]);

            $running = $openingBalance;
            $totalDebit = 0.0;
            $totalCredit = 0.0;

            $rows = $linesQuery->get()->map(function ($row) use (&$running, &$totalDebit, &$totalCredit) {
                $debit = (float) $row->debit;
                $credit = (float) $row->credit;
                $running += $debit - $credit;
                $totalDebit += $debit;
                $totalCredit += $credit;

                return [
                    'id' => $row->id,
                    'entry_id' => $row->entry_id,
                    'entry_date' => $row->entry_date,
                    'journal_code' => $row->journal_code,
                    'reference' => $row->reference,
                    'entry_description' => $row->entry_description,
                    'line_description' => $row->line_description,
                    'contact_name' => $row->contact_name,
                    'status' => $row->status,
                    'debit' => $debit,
                    'credit' => $credit,
                    'running_balance' => round($running, 2),
                ];
            });

            $totals = [
                'debit' => round($totalDebit, 2),
                'credit' => round($totalCredit, 2),
                'balance' => round($running, 2),
            ];
        }

        return Inertia::render('Ledger/AccountLedger', [
            'accounts' => $accounts,
            'selectedAccountId' => $selectedAccountId,
            'account' => $account,
            'rows' => $rows,
            'openingBalance' => round($openingBalance, 2),
            'totals' => $totals,
            'filters' => [
                'account_id' => $selectedAccountId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'include_draft' => $includeDraft,
            ],
        ]);
    }

    public function trialBalance(Request $request): Response
    {
        $company = app('currentCompany');

        $query = Account::query()
            ->select([
                'accounts.id',
                'accounts.code',
                'accounts.label',
                'accounts.class',
                'accounts.type',
                DB::raw('COALESCE(SUM(journal_lines.debit), 0) as total_debit'),
                DB::raw('COALESCE(SUM(journal_lines.credit), 0) as total_credit'),
            ])
            ->leftJoin('journal_lines', 'journal_lines.account_id', '=', 'accounts.id')
            ->leftJoin('journal_entries', function ($join) use ($company, $request) {
                $join->on('journal_entries.id', '=', 'journal_lines.journal_entry_id')
                    ->where('journal_entries.company_id', '=', $company->id)
                    ->where('journal_entries.status', '=', 'posted');

                if ($request->filled('date_from')) {
                    $join->whereDate('journal_entries.entry_date', '>=', $request->string('date_from')->toString());
                }

                if ($request->filled('date_to')) {
                    $join->whereDate('journal_entries.entry_date', '<=', $request->string('date_to')->toString());
                }
            })
            ->where('accounts.company_id', $company->id)
            ->groupBy('accounts.id', 'accounts.code', 'accounts.label', 'accounts.class', 'accounts.type')
            ->orderBy('accounts.code');

        $rows = $query->get()->map(function ($row) {
            $debit = (float) $row->total_debit;
            $credit = (float) $row->total_credit;

            return [
                'id' => $row->id,
                'code' => $row->code,
                'label' => $row->label,
                'class' => $row->class,
                'type' => $row->type,
                'total_debit' => $debit,
                'total_credit' => $credit,
                'balance' => $debit - $credit,
            ];
        });

        return Inertia::render('Ledger/TrialBalance', [
            'rows' => $rows->values(),
            'filters' => [
                'date_from' => $request->input('date_from', ''),
                'date_to' => $request->input('date_to', ''),
            ],
            'totals' => [
                'debit' => (float) $rows->sum('total_debit'),
                'credit' => (float) $rows->sum('total_credit'),
                'balance' => (float) $rows->sum('balance'),
            ],
        ]);
    }

    public function post(Request $request, JournalService $journalService): RedirectResponse
    {
        $company = app('currentCompany');

        $validated = $request->validate([
            'journal_entry_id' => ['required', 'uuid', 'exists:journal_entries,id'],
        ]);

        $entry = JournalEntry::query()
            ->with('lines')
            ->where('company_id', $company->id)
            ->findOrFail($validated['journal_entry_id']);

        if ($entry->status !== 'draft') {
            return back()->with('error', 'Seules les écritures en brouillon peuvent être validées.');
        }

        $totalDebit = (float) $entry->lines->sum('debit');
        $totalCredit = (float) $entry->lines->sum('credit');

        if (abs($totalDebit - $totalCredit) >= 0.0001) {
            return back()->with('error', 'L’écriture n’est pas équilibrée.');
        }

        $journalService->post($entry, $request->user());

        return back()->with('success', 'Écriture comptable validée avec succès.');
    }
}
