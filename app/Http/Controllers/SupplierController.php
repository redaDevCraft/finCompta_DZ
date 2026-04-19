<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Expense;
use App\Models\JournalLine;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Suppliers = Contact where type in (supplier, both).
 * Shares the `contacts` table but exposes dedicated routes + pages.
 */
class SupplierController extends Controller
{
    public function index(Request $request): Response
    {
        $companyId = app('currentCompany')->id;

        $search = trim((string) $request->input('search', ''));
        $status = $request->input('status');
        $sort = $request->input('sort', 'display_name');
        $dir = $request->input('dir', 'asc') === 'desc' ? 'desc' : 'asc';
        $perPage = min(100, max(10, (int) $request->input('per_page', 25)));

        $sortable = ['display_name', 'created_at', 'nif'];
        if (! in_array($sort, $sortable, true)) {
            $sort = 'display_name';
        }

        $query = Contact::query()
            ->where('company_id', $companyId)
            ->whereIn('type', ['supplier', 'both']);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('display_name', 'ilike', "%{$search}%")
                    ->orWhere('raison_sociale', 'ilike', "%{$search}%")
                    ->orWhere('nif', 'ilike', "%{$search}%")
                    ->orWhere('rc', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%")
                    ->orWhere('phone', 'ilike', "%{$search}%");
            });
        }

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $suppliers = $query
            ->withCount(['expenses as expenses_count'])
            ->withSum(['expenses as expenses_total' => function ($q) {
                $q->whereIn('status', ['confirmed', 'paid']);
            }], 'total_ttc')
            ->orderBy($sort, $dir)
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Suppliers/Index', [
            'suppliers' => $suppliers,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'sort' => $sort,
                'dir' => $dir,
            ],
            'kpis' => $this->partyKpis($companyId, '401'),
        ]);
    }

    public function show(Request $request, Contact $contact): Response
    {
        abort_unless($contact->company_id === app('currentCompany')->id, 404);
        abort_unless(in_array($contact->type, ['supplier', 'both'], true), 404);

        $expenses = Expense::query()
            ->where('company_id', $contact->company_id)
            ->where('contact_id', $contact->id)
            ->orderByDesc('expense_date')
            ->limit(50)
            ->get([
                'id', 'reference', 'expense_date', 'due_date',
                'total_ttc', 'status',
            ]);

        $ledger = $this->partyLedger($contact, '401');

        return Inertia::render('Suppliers/Show', [
            'contact' => $contact,
            'expenses' => $expenses,
            'ledger' => $ledger,
            'aging' => $this->aging($ledger['lines']),
        ]);
    }

    protected function partyKpis(string $companyId, string $prefix): array
    {
        $row = JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('journal_entries.company_id', $companyId)
            ->where('journal_entries.status', 'posted')
            ->where('accounts.code', 'like', $prefix.'%')
            ->whereNull('journal_lines.lettering_id')
            ->selectRaw('COALESCE(SUM(journal_lines.debit),0) as debit, COALESCE(SUM(journal_lines.credit),0) as credit')
            ->first();

        return [
            'open_payable' => round((float) ($row->credit ?? 0) - (float) ($row->debit ?? 0), 2),
            'prefix' => $prefix,
        ];
    }

    protected function partyLedger(Contact $contact, string $accountPrefix): array
    {
        $lines = JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('journal_entries.company_id', $contact->company_id)
            ->where('journal_entries.status', 'posted')
            ->where('journal_lines.contact_id', $contact->id)
            ->where('accounts.code', 'like', $accountPrefix.'%')
            ->orderBy('journal_entries.entry_date')
            ->orderBy('journal_lines.sort_order')
            ->get([
                'journal_lines.id',
                'journal_lines.debit',
                'journal_lines.credit',
                'journal_lines.description',
                'journal_lines.lettering_id',
                'journal_entries.entry_date',
                'journal_entries.reference',
                'accounts.code as account_code',
                'accounts.label as account_label',
            ])
            ->map(function ($line) {
                return [
                    'id' => $line->id,
                    'entry_date' => optional($line->entry_date)->toDateString(),
                    'reference' => $line->reference,
                    'description' => $line->description,
                    'account_code' => $line->account_code,
                    'account_label' => $line->account_label,
                    'debit' => (float) $line->debit,
                    'credit' => (float) $line->credit,
                    'lettering_id' => $line->lettering_id,
                ];
            });

        $debit = $lines->sum('debit');
        $credit = $lines->sum('credit');

        return [
            'lines' => $lines,
            'totals' => [
                'debit' => round($debit, 2),
                'credit' => round($credit, 2),
                'balance' => round($credit - $debit, 2),
            ],
        ];
    }

    protected function aging($lines): array
    {
        $buckets = [
            '0_30' => 0.0,
            '31_60' => 0.0,
            '61_90' => 0.0,
            'over90' => 0.0,
        ];

        $today = now();
        foreach ($lines as $l) {
            if ($l['lettering_id']) {
                continue;
            }
            $net = (float) $l['credit'] - (float) $l['debit']; // payable positive
            if (abs($net) < 0.01) {
                continue;
            }
            $days = $l['entry_date']
                ? (int) $today->diffInDays($l['entry_date'], false) * -1
                : 0;

            if ($days <= 30) {
                $buckets['0_30'] += $net;
            } elseif ($days <= 60) {
                $buckets['31_60'] += $net;
            } elseif ($days <= 90) {
                $buckets['61_90'] += $net;
            } else {
                $buckets['over90'] += $net;
            }
        }

        return array_map(fn ($v) => round($v, 2), $buckets);
    }
}
