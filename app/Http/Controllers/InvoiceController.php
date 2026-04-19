<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInvoiceRequest;
use App\Models\Account;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\TaxRate;
use App\Models\User;
use App\Services\InvoiceService;
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

        $invoices = Invoice::query()
            ->where('company_id', $company->id)
            ->with(['contact', 'lines'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($dateFrom, fn ($q) => $q->whereDate('issue_date', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('issue_date', '<=', $dateTo))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('invoice_number', 'ilike', "%{$search}%")
                        ->orWhere('notes', 'ilike', "%{$search}%")
                        ->orWhereHas('contact', function ($c) use ($search) {
                            $c->where('display_name', 'ilike', "%{$search}%")
                                ->orWhere('raison_sociale', 'ilike', "%{$search}%");
                        });
                });
            })
            ->orderByDesc('issue_date')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Invoices/Index', [
            'invoices' => $invoices,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }

    public function create(): Response
    {
        $company = app('currentCompany');

        $contacts = Contact::query()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->orderBy('display_name')
            ->get();

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
            ->orderBy('code')
            ->get();

        return Inertia::render('Invoices/Create', [
            'contacts' => $contacts,
            'taxRates' => $taxRates,
            'accounts' => $accounts,
        ]);
    }

    public function store(StoreInvoiceRequest $request, InvoiceService $service): RedirectResponse
    {
        $company = app('currentCompany');

        $invoice = Invoice::create([
            'company_id' => $company->id,
            'sequence_id' => $request->input('sequence_id'),
            'invoice_number' => null,
            'document_type' => $request->input('document_type'),
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

        $invoice->load(['contact', 'lines', 'vatBuckets']);

        $contacts = Contact::query()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->orderBy('display_name')
            ->get();

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
            ->orderBy('code')
            ->get();

        return Inertia::render('Invoices/Edit', [
            'invoice' => $invoice,
            'contacts' => $contacts,
            'taxRates' => $taxRates,
            'accounts' => $accounts,
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
