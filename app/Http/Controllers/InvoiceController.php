<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Resources\InvoiceListResource;
use App\Models\Account;
use App\Models\Invoice;
use App\Models\TaxRate;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\SequenceService;
use App\Support\ListQuery\DateRange;
use App\Support\ListQuery\PerPage;
use App\Support\ListQuery\SortSpec;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function index(Request $request): Response
    {
        $company = app('currentCompany');

        $search = trim((string) $request->input('search', ''));
        $status = $request->input('status');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $sort = new SortSpec(
            allowed: ['issue_date', 'due_date', 'total_ttc', 'status', 'created_at'],
            default: [['issue_date', 'desc'], ['created_at', 'desc']],
            tiebreaker: 'id',
        );
        $orders = $sort->resolve($request);

        $query = Invoice::query()
            ->select([
                'id',
                'company_id',
                'contact_id',
                'invoice_number',
                'document_type',
                'status',
                'issue_date',
                'due_date',
                'subtotal_ht',
                'total_ttc',
                'currency',
                'pdf_path',
                'created_at',
            ])
            ->with(['contact:id,display_name'])
            ->when($status, fn ($q) => $q->where('status', $status));

        // Sargable date range — plain comparisons against the DATE column keep
        // the (company_id, issue_date) index usable. whereDate() would wrap the
        // column in a function on some drivers and defeat it.
        DateRange::apply($query, $dateFrom, $dateTo, 'issue_date');

        if ($search !== '') {
            // Anchored prefix search stays on the B-tree index. Full contains
            // search requires pg_trgm and will be introduced in a later phase
            // with an explicit GIN index.
            $needle = $search.'%';
            $query->where(function ($sub) use ($needle, $search) {
                $sub->where('invoice_number', 'ilike', $needle)
                    ->orWhereHas('contact', function ($c) use ($needle) {
                        $c->where('display_name', 'ilike', $needle)
                            ->orWhere('raison_sociale', 'ilike', $needle);
                    });

                // Notes is free-text and not indexed; keep it out of the hot
                // path unless the user explicitly opts in via a longer query.
                if (mb_strlen($search) >= 4) {
                    $sub->orWhere('notes', 'ilike', '%'.$search.'%');
                }
            });
        }

        $sort->apply($query, $orders);

        // Cursor (keyset) pagination instead of offset. The SortSpec already
        // enforces an id tiebreaker so the ordering is strictly monotonic,
        // which is the precondition keyset navigation needs. Advantage vs
        // offset: reads stay O(log n) regardless of page depth; DB does not
        // have to scan and discard N prior rows on deep pages.
        $paginator = $query
            ->cursorPaginate(PerPage::resolve($request))
            ->withQueryString()
            ->through(fn (Invoice $invoice) => (new InvoiceListResource($invoice))->toArray($request));

        return Inertia::render('Invoices/Index', [
            'invoices' => $paginator,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'sort' => $request->input('sort'),
            ],
        ]);
    }

    public function create(): Response
    {
        $company = app('currentCompany');

        // Contacts are no longer eagerly shipped — the frontend consumes
        // /suggest/contacts via AsyncCombobox, so the invoice create screen
        // stays O(1) in payload regardless of how many clients the tenant
        // has. Tax rates stay inlined (bounded, ~5 rows).
        $taxRates = TaxRate::query()
            ->where(function ($query) use ($company) {
                $query->where('company_id', $company->id)
                    ->orWhereNull('company_id');
            })
            ->where('is_active', true)
            ->orderBy('rate_percent')
            ->get();

        // Accounts: invoice lines typically post to class 7 (revenues). Only
        // that narrow subset is shipped; everything else (class 1-6) has no
        // business appearing here anyway.
        $accounts = Account::query()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->where('class', 7)
            ->orderBy('code')
            ->get(['id', 'code', 'label', 'class']);

        return Inertia::render('Invoices/Create', [
            'taxRates' => $taxRates,
            'accounts' => $accounts,
        ]);
    }

    public function store(
        StoreInvoiceRequest $request,
        InvoiceService $service,
        SequenceService $sequences,
    ): RedirectResponse
    {
        $company = app('currentCompany');
        $issueDate = (string) $request->input('issue_date');
        $documentType = (string) $request->input('document_type');

        $allocated = $sequences->nextInvoiceNumber(
            companyId: $company->id,
            documentType: $documentType,
            issueDate: $issueDate,
        );

        $invoice = Invoice::create([
            'company_id' => $company->id,
            'sequence_id' => $allocated['sequence_id'],
            'invoice_number' => $allocated['number'],
            'document_type' => $documentType,
            'status' => 'draft',
            'contact_id' => $request->input('contact_id'),
            'client_snapshot' => null,
            'issue_date' => $request->input('issue_date'),
            'due_date' => $request->input('due_date'),
            'payment_mode' => $request->input('payment_mode'),
            'currency' => $company->currency ?? 'DZD',
            'subtotal_ht' => 0,
            'total_vat' => 0,
            'total_ttc' => 0,
            'notes' => $request->input('notes'),
            'original_invoice_id' => null,
            'issued_at' => null,
            'issued_by' => null,
            'pdf_path' => null,
            'journal_entry_id' => null,
        ]);

        $service->saveLines($invoice, $request->input('lines', []));

        return redirect()->route('invoices.show', $invoice);
    }

    public function show(Invoice $invoice): Response
    {
        $this->authorizeInvoice($invoice);

        $invoice->load([
            'contact',
            'lines',
            'vatBuckets',
            'journalEntry.lines.account',
        ]);

        return Inertia::render('Invoices/Show', [
            'invoice' => $invoice,
        ]);
    }

    public function edit(Invoice $invoice): Response
    {
        $this->authorizeInvoice($invoice);

        abort_if(! $invoice->isEditable(), 422, 'Facture déjà émise, non modifiable');

        $company = app('currentCompany');

        $invoice->load(['contact:id,display_name,type', 'lines', 'vatBuckets']);

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
            ->where('class', 7)
            ->orderBy('code')
            ->get(['id', 'code', 'label', 'class']);

        // Only the currently-selected contact is shipped as an AsyncCombobox
        // prefill, so the picker can display the name without forcing the
        // user to search for what they've already chosen.
        $prefillContact = $invoice->contact
            ? [
                'id' => $invoice->contact->id,
                'display_name' => $invoice->contact->display_name,
                'type' => $invoice->contact->type,
            ]
            : null;

        return Inertia::render('Invoices/Edit', [
            'invoice' => $invoice,
            'taxRates' => $taxRates,
            'accounts' => $accounts,
            'prefillContact' => $prefillContact,
        ]);
    }

    public function update(StoreInvoiceRequest $request, Invoice $invoice, InvoiceService $service): RedirectResponse
    {
        $this->authorizeInvoice($invoice);

        abort_if(! $invoice->isEditable(), 422, 'Facture déjà émise, non modifiable');

        $invoice->update([
            'contact_id' => $request->input('contact_id'),
            'document_type' => $request->input('document_type'),
            'issue_date' => $request->input('issue_date'),
            'due_date' => $request->input('due_date'),
            'payment_mode' => $request->input('payment_mode'),
            'notes' => $request->input('notes'),
        ]);

        $service->saveLines($invoice->fresh(), $request->input('lines', []));

        return back()->with('success', 'Facture mise à jour');
    }

    public function issue(Invoice $invoice, InvoiceService $service): RedirectResponse
    {
        $this->authorizeInvoice($invoice);

        abort_if(! $invoice->isEditable(), 422, 'Facture déjà émise');

        $result = $service->issue($invoice, app('currentCompany'), auth()->user());

        if (! ($result['success'] ?? false)) {
            return back()->withErrors($result['errors'] ?? ['Erreur inconnue']);
        }

        return redirect()
            ->route('invoices.show', $invoice)
            ->with('warnings', $result['warnings'] ?? [])
            ->with('success', 'Facture émise avec succès');
    }

    public function void(Invoice $invoice, InvoiceService $service): RedirectResponse
    {
        $this->authorizeInvoice($invoice);

        /** @var User|null $user */
        $user = request()->user();
        abort_if(! $user, 403, 'Utilisateur non authentifié');

        $service->void($invoice, app('currentCompany'), $user);

        return back()->with('success', 'Facture annulée et écriture extournée');
    }

    public function pdf(Invoice $invoice)
    {
        $this->authorizeInvoice($invoice);

        abort_if($invoice->status === 'draft', 403, 'PDF non disponible pour les brouillons');
        abort_if(empty($invoice->pdf_path), 404, 'PDF introuvable');

        return Storage::download(
            $invoice->pdf_path,
            ($invoice->invoice_number ?? 'facture').'.pdf'
        );
    }

    public function credit(Invoice $invoice, InvoiceService $service): RedirectResponse
    {
        $this->authorizeInvoice($invoice);

        /** @var User|null $user */
        $user = request()->user();

        abort_if(! $user, 403, 'Utilisateur non authentifié');

        $creditNote = $service->createCreditNote(
            $invoice,
            app('currentCompany'),
            $user
        );

        return redirect()->route('invoices.show', $creditNote);
    }

    private function authorizeInvoice(Invoice $invoice): void
    {
        abort_unless($invoice->company_id === app('currentCompany')->id, 403, 'Accès non autorisé à cette facture');
    }
}
