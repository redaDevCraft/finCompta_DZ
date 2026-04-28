<?php

namespace App\Http\Controllers;

use App\Http\Resources\ExpenseListResource;
use App\Models\Account;
use App\Models\Contact;
use App\Models\Expense;
use App\Models\TaxRate;
use App\Services\ExpenseService;
use App\Services\SequenceService;
use App\Support\ListQuery\DateRange;
use App\Support\ListQuery\PerPage;
use App\Support\ListQuery\SortSpec;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('search', ''));
        $status = $request->input('status');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $sort = new SortSpec(
            allowed: ['expense_date', 'due_date', 'total_ttc', 'status', 'created_at'],
            default: [['expense_date', 'desc'], ['created_at', 'desc']],
            tiebreaker: 'id',
        );
        $orders = $sort->resolve($request);

        $query = Expense::query()
            ->select([
                'id',
                'company_id',
                'contact_id',
                'account_id',
                'reference',
                'description',
                'status',
                'expense_date',
                'due_date',
                'total_ht',
                'total_vat',
                'total_ttc',
                'created_at',
            ])
            ->with([
                'contact:id,display_name',
                'account:id,code,label',
            ])
            ->when($status, fn ($q) => $q->where('status', $status));

        DateRange::apply($query, $dateFrom, $dateTo, 'expense_date');

        if ($search !== '') {
            $needle = $search.'%';
            $query->where(function ($sub) use ($needle, $search) {
                $sub->where('reference', 'ilike', $needle)
                    ->orWhereHas('contact', function ($c) use ($needle) {
                        $c->where('display_name', 'ilike', $needle)
                            ->orWhere('raison_sociale', 'ilike', $needle);
                    });

                if (mb_strlen($search) >= 4) {
                    $sub->orWhere('description', 'ilike', '%'.$search.'%');
                }
            });
        }

        $sort->apply($query, $orders);

        // Cursor pagination — see InvoiceController::index for the rationale.
        $paginator = $query
            ->cursorPaginate(PerPage::resolve($request))
            ->withQueryString()
            ->through(fn (Expense $expense) => (new ExpenseListResource($expense))->toArray($request));

        return Inertia::render('Expenses/Index', [
            'expenses' => $paginator,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'sort' => $request->input('sort'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $company = app('currentCompany');

        // Contacts are fetched on demand via /suggest/contacts
        $taxRates = TaxRate::query()
            ->where(function ($query) use ($company) {
                $query->where('company_id', $company->id)
                    ->orWhereNull('company_id');
            })
            ->where('is_active', true)
            ->orderBy('rate_percent')
            ->get();

        $accounts = Account::query()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->where('class', 6)
            ->orderBy('code')
            ->get(['id', 'code', 'label', 'class']);

        $prefill = [
            'source_document_id' => $request->query('from_document'),
            'vendor_name' => $request->query('vendor_name'),
            'reference' => $request->query('reference'),
            'expense_date' => $request->query('expense_date'),
            'total_ht' => $request->query('total_ht'),
            'total_vat' => $request->query('total_vat'),
            'currency' => $request->query('currency'),
            'payment_method' => $request->query('payment_method'),
            'tax_rate_id' => $request->query('tax_rate_id'),
            'expense_account_id' => $request->query('expense_account_id'),
            'contact_id' => $request->query('contact_id'),
        ];

        // Compute TTC on the backend for initial render, read-only in frontend
        $total_ht = $prefill['total_ht'] !== null ? (float)$prefill['total_ht'] : 0.0;
        $total_vat = $prefill['total_vat'] !== null ? (float)$prefill['total_vat'] : 0.0;
        $prefill['total_ttc'] = (string)(round($total_ht + $total_vat, 2));

        $prefillContact = null;
        if (! empty($prefill['contact_id'])) {
            $prefillContact = Contact::query()
                ->where('company_id', $company->id)
                ->where('id', $prefill['contact_id'])
                ->first(['id', 'display_name', 'type']);
        }

        return Inertia::render('Expenses/Create', [
            'taxRates' => $taxRates,
            'accounts' => $accounts,
            'prefill' => $prefill,
            'prefillContact' => $prefillContact,
        ]);
    }

    public function store(Request $request, SequenceService $sequences): RedirectResponse
    {
        $company = app('currentCompany');

        $validated = $this->validateExpense($request);
        $reference = trim((string) ($validated['reference'] ?? ''));
        $allocated = null;

        if ($reference === '') {
            $allocated = $sequences->nextNumber(
                companyId: $company->id,
                documentType: 'expense',
                issueDate: (string) $request->input('expense_date'),
            );
        }

        $expense = DB::transaction(function () use ($company, $validated, $reference, $allocated) {
            // Always ignore the passed total_ttc and recalculate (guard backend!)
            $total_ht = isset($validated['total_ht']) ? (float) $validated['total_ht'] : 0.0;
            $total_vat = isset($validated['total_vat']) ? (float) $validated['total_vat'] : 0.0;
            $calculated_ttc = round($total_ht + $total_vat, 2);

            $expense = Expense::create([
                'company_id' => $company->id,
                'contact_id' => $validated['contact_id'] ?? null,
                'supplier_snapshot' => null,
                'sequence_id' => $allocated['sequence_id'] ?? null,
                'reference' => $reference !== '' ? $reference : ($allocated['number'] ?? null),
                'expense_date' => $validated['expense_date'],
                'due_date' => $validated['due_date'] ?? null,
                'description' => $validated['description'] ?? null,
                'total_ht' => $total_ht,
                'total_vat' => $total_vat,
                'total_ttc' => $calculated_ttc,
                'account_id' => $validated['expense_account_id'] ?? ($validated['account_id'] ?? null),
                'status' => 'draft',
                'source_document_id' => $validated['source_document_id'] ?? null,
                'ai_extracted' => false,
                'journal_entry_id' => null,
            ]);

            // Replace all lines and recalc totals
            $this->syncLinesAndTotals($expense, $validated['lines'] ?? []);

            return $expense;
        });

        return redirect()->route('expenses.show', $expense);
    }

    public function show(Expense $expense): Response
    {
        $this->authorizeExpense($expense);

        $expense->load([
            'contact',
            'account',
            'document',
            'lines.account:id,code,label',
            'lines.taxRate:id,label,rate_percent',
            'journalEntry.lines.account',
        ]);

        return Inertia::render('Expenses/Show', [
            'expense' => $expense,
        ]);
    }

    public function update(Request $request, Expense $expense): RedirectResponse
    {
        $this->authorizeExpense($expense);

        abort_if(! $expense->isEditable(), 422, 'Dépense déjà confirmée, non modifiable');

        $validated = $this->validateExpense($request);

        DB::transaction(function () use ($expense, $validated) {
            $total_ht = isset($validated['total_ht']) ? (float) $validated['total_ht'] : 0.0;
            $total_vat = isset($validated['total_vat']) ? (float) $validated['total_vat'] : 0.0;
            $calculated_ttc = round($total_ht + $total_vat, 2);
            $expense->update([
                'contact_id' => $validated['contact_id'] ?? null,
                'reference' => $validated['reference'] ?? null,
                'expense_date' => $validated['expense_date'],
                'due_date' => $validated['due_date'] ?? null,
                'description' => $validated['description'] ?? null,
                'total_ht' => $total_ht,
                'total_vat' => $total_vat,
                'total_ttc' => $calculated_ttc,
                'account_id' => $validated['expense_account_id'] ?? ($validated['account_id'] ?? null),
            ]);

            $this->syncLinesAndTotals($expense, $validated['lines'] ?? []);
        });

        return back()->with('success', 'Dépense mise à jour');
    }

    public function confirm(Expense $expense, ExpenseService $service): RedirectResponse
    {
        $this->authorizeExpense($expense);

        $user = request()->user();

        abort_if(! $user, 403, 'Utilisateur non authentifié');

        $result = $service->confirm($expense, app('currentCompany'), $user);

        return redirect()
            ->route('expenses.show', $expense)
            ->with('warnings', $result['warnings'] ?? [])
            ->with('success', 'Dépense confirmée');
    }

    private function authorizeExpense(Expense $expense): void
    {
        abort_unless($expense->company_id === app('currentCompany')->id, 403, 'Accès non autorisé à cette dépense');
    }

    private function validateExpense(Request $request): array
    {
        $company = app('currentCompany');

        // total_ttc not trusted from frontend; only for initial payload (readonly input)
        return $request->validate([
            'contact_id' => 'nullable|uuid|exists:contacts,id',
            'reference' => 'nullable|string|max:100',
            'expense_date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:expense_date',
            'description' => 'nullable|string|max:2000',
            'total_ht' => 'required|numeric|min:0',
            'total_vat' => 'nullable|numeric|min:0',
            // no 'required' on total_ttc: always auto-calculated backend (and read-only in UI)
            'total_ttc' => 'nullable|numeric|min:0',
            'expense_account_id' => 'nullable|uuid|exists:accounts,id',
            'account_id' => 'nullable|uuid|exists:accounts,id',
            'source_document_id' => 'nullable|uuid',
            'lines' => 'nullable|array',
            'lines.*.designation' => 'required_with:lines|string|max:500',
            'lines.*.amount_ht' => 'required_with:lines|numeric|min:0',
            'lines.*.vat_rate_pct' => 'nullable|numeric|min:0|max:100',
            'lines.*.amount_vat' => 'nullable|numeric|min:0',
            'lines.*.amount_ttc' => 'required_with:lines|numeric|min:0',
            'lines.*.tax_rate_id' => 'nullable|uuid',
            'lines.*.account_id' => 'nullable|uuid',
        ]);
        $expectedTtc = round((float)($data['total_ht']) + (float)($data['total_vat'] ?? 0), 2);
        $actualTtc   = round((float)$data['total_ttc'], 2);
    
        if (abs($expectedTtc - $actualTtc) > 0.01) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'total_ttc' => "Déséquilibre : TTC ({$actualTtc}) ≠ HT + TVA ({$expectedTtc})",
            ]);
        }
    
       
    }

    /**
     * Replace all lines for an expense. When lines are provided, derived totals
     * (HT / VAT / TTC) are recomputed from the lines and override any header
     * totals the client sent, guaranteeing consistency with the VAT purchase
     * report and the purchase journal entry.
     * 
     * Always recalculate the header totals from the lines!
     */
    private function syncLinesAndTotals(Expense $expense, array $lines): void
    {
        $expense->lines()->delete();

        $totalHt = 0.0;
        $totalVat = 0.0;
        $totalTtc = 0.0;
        $sort = 0;

        if (!empty($lines)) {
            foreach ($lines as $line) {
                $ht = round((float) ($line['amount_ht'] ?? 0), 2);
                $vatRate = (float) ($line['vat_rate_pct'] ?? 0);
                $vatAmount = isset($line['amount_vat'])
                    ? round((float) $line['amount_vat'], 2)
                    : round($ht * $vatRate / 100, 2);
                $ttc = isset($line['amount_ttc']) && (float) $line['amount_ttc'] > 0
                    ? round((float) $line['amount_ttc'], 2)
                    : round($ht + $vatAmount, 2);

                $expense->lines()->create([
                    'designation' => $line['designation'],
                    'amount_ht' => $ht,
                    'vat_rate_pct' => $vatRate,
                    'amount_vat' => $vatAmount,
                    'amount_ttc' => $ttc,
                    'tax_rate_id' => $line['tax_rate_id'] ?? null,
                    'account_id' => $line['account_id'] ?? $expense->account_id,
                    'sort_order' => $sort++,
                ]);

                $totalHt += $ht;
                $totalVat += $vatAmount;
                $totalTtc += $ttc;
            }

            // Always sync aggregate totals to match sum of lines
            $expense->update([
                'total_ht' => round($totalHt, 2),
                'total_vat' => round($totalVat, 2),
                'total_ttc' => round($totalTtc, 2),
            ]);
        } else {
            // Only update the header with basic HT + VAT arithmetic if there are no lines
            $expense->update([
                'total_ht' => round($expense->total_ht ?? 0.0, 2),
                'total_vat' => round($expense->total_vat ?? 0.0, 2),
                'total_ttc' => round(($expense->total_ht ?? 0.0) + ($expense->total_vat ?? 0.0), 2),
            ]);
        }
    }
}
