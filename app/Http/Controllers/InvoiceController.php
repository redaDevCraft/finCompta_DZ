<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInvoiceRequest;
use App\Models\Account;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\TaxRate;
use App\Services\InvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function index(): Response
    {
        $company = app('currentCompany');

        $invoices = Invoice::query()
            ->where('company_id', $company->id)
            ->with(['contact', 'lines'])
            ->orderByDesc('issue_date')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Invoices/Index', [
            'invoices' => $invoices,
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

    public function void(Invoice $invoice): RedirectResponse
    {
        $this->authorizeInvoice($invoice);

        abort_if($invoice->status === 'draft', 422, 'Un brouillon ne peut être annulé, supprimez-le');

        $invoice->update([
            'status' => 'voided',
        ]);

        return back()->with('success', 'Facture annulée');
    }

    public function pdf(Invoice $invoice)
    {
        $this->authorizeInvoice($invoice);

        abort_if($invoice->status === 'draft', 403, 'PDF non disponible pour les brouillons');
        abort_if(empty($invoice->pdf_path), 404, 'PDF introuvable');

        return Storage::download(
            $invoice->pdf_path,
            ($invoice->invoice_number ?? 'facture') . '.pdf'
        );
    }

    public function credit(Invoice $invoice, InvoiceService $service): RedirectResponse
    {
        $this->authorizeInvoice($invoice);
    
        /** @var \App\Models\User|null $user */
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