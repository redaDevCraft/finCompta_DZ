<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreQuoteRequest;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\TaxRate;
use App\Services\InvoiceService;
use App\Services\QuoteService;
use App\Services\SequenceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class QuoteController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Quote::query()
            ->with(['contact:id,display_name'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('contact_id'), fn ($q) => $q->where('contact_id', $request->string('contact_id')))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('issue_date', '>=', $request->string('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('issue_date', '<=', $request->string('date_to')))
            ->orderByDesc('issue_date')
            ->orderByDesc('created_at');

        $quotes = $query->paginate(15)->withQueryString()->through(function (Quote $quote) {
            return [
                'id' => $quote->id,
                'number' => $quote->number,
                'status' => $quote->status,
                'issue_date' => optional($quote->issue_date)->toDateString(),
                'expiry_date' => optional($quote->expiry_date)->toDateString(),
                'total' => (float) $quote->total,
                'invoice_id' => $quote->invoice_id,
                'contact' => $quote->contact
                    ? [
                        'id' => $quote->contact->id,
                        'display_name' => $quote->contact->display_name,
                    ]
                    : null,
            ];
        });

        return Inertia::render('Quotes/Index', [
            'quotes' => $quotes,
            'filters' => [
                'status' => $request->input('status', ''),
                'contact_id' => $request->input('contact_id', ''),
                'date_from' => $request->input('date_from', ''),
                'date_to' => $request->input('date_to', ''),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Quotes/Form', $this->formPayload());
    }

    public function store(StoreQuoteRequest $request, QuoteService $quoteService): RedirectResponse
    {
        $this->authorizeQuoteMutation($request);

        $company = app('currentCompany');

        $quote = Quote::create([
            'company_id' => $company->id,
            'contact_id' => $request->input('contact_id'),
            'number' => $this->nextQuoteNumber($company->id, (string) $request->input('issue_date')),
            'status' => 'draft',
            'issue_date' => $request->input('issue_date'),
            'expiry_date' => $request->input('expiry_date'),
            'currency_id' => $request->input('currency_id'),
            'notes' => $request->input('notes'),
            'reference' => $request->input('reference'),
            'created_by_user_id' => $request->user()?->id,
            'updated_by_user_id' => $request->user()?->id,
        ]);

        $quoteService->saveLines($quote, $request->input('lines', []));

        return redirect()
            ->route('quotes.show', $quote)
            ->with('success', 'Devis créé avec succès.');
    }

    public function show(Quote $quote): Response
    {
        $this->authorizeQuote($quote);

        $quote->load(['contact', 'lines', 'currency', 'invoice']);

        return Inertia::render('Quotes/Show', [
            'quote' => $quote,
        ]);
    }

    public function edit(Quote $quote): Response
    {
        $this->authorizeQuote($quote);
        abort_if($quote->status !== 'draft', 422, 'Seuls les devis brouillons sont modifiables.');

        $quote->load(['contact:id,display_name,type', 'lines']);

        return Inertia::render('Quotes/Form', array_merge(
            $this->formPayload(),
            [
                'quote' => $quote,
                'prefillContact' => $quote->contact
                    ? [
                        'id' => $quote->contact->id,
                        'display_name' => $quote->contact->display_name,
                        'type' => $quote->contact->type,
                    ]
                    : null,
            ],
        ));
    }

    public function update(StoreQuoteRequest $request, Quote $quote, QuoteService $quoteService): RedirectResponse
    {
        $this->authorizeQuoteMutation($request);
        $this->authorizeQuote($quote);
        abort_if($quote->status !== 'draft', 422, 'Seuls les devis brouillons sont modifiables.');

        $quote->update([
            'contact_id' => $request->input('contact_id'),
            'issue_date' => $request->input('issue_date'),
            'expiry_date' => $request->input('expiry_date'),
            'currency_id' => $request->input('currency_id'),
            'notes' => $request->input('notes'),
            'reference' => $request->input('reference'),
            'updated_by_user_id' => $request->user()?->id,
        ]);

        $quoteService->saveLines($quote->fresh(), $request->input('lines', []));

        return redirect()
            ->route('quotes.show', $quote)
            ->with('success', 'Devis mis à jour.');
    }

    public function send(Quote $quote): RedirectResponse
    {
        $this->authorizeQuoteMutation(request());
        return $this->transition($quote, from: ['draft'], to: 'sent', message: 'Devis envoyé.');
    }

    public function accept(Quote $quote): RedirectResponse
    {
        $this->authorizeQuoteMutation(request());
        return $this->transition($quote, from: ['sent'], to: 'accepted', message: 'Devis accepté.');
    }

    public function reject(Quote $quote): RedirectResponse
    {
        $this->authorizeQuoteMutation(request());
        return $this->transition($quote, from: ['draft', 'sent'], to: 'rejected', message: 'Devis rejeté.');
    }

    public function pdf(Quote $quote): BinaryFileResponse
    {
        $this->authorizeQuote($quote);

        $quote->load(['company', 'contact', 'lines', 'currency']);

        $pdf = Pdf::loadView('pdf.quote', [
            'quote' => $quote,
        ])->setPaper('a4');

        $tmpPath = tempnam(sys_get_temp_dir(), 'quote_');
        file_put_contents($tmpPath, $pdf->output());

        $name = sprintf('%s.pdf', $quote->number ?: 'devis');

        return response()->download($tmpPath, $name)->deleteFileAfterSend(true);
    }

    public function convertToInvoice(
        Quote $quote,
        InvoiceService $invoiceService,
        SequenceService $sequences,
    ): RedirectResponse {
        $this->authorizeQuoteMutation(request());
        $this->authorizeQuote($quote);

        abort_if(
            in_array($quote->status, ['rejected', 'expired'], true),
            422,
            'Ce devis ne peut pas être converti en facture.'
        );

        abort_if($quote->invoice_id !== null, 422, 'Ce devis est déjà lié à une facture.');

        $quote->load(['lines', 'currency']);
        $company = app('currentCompany');
        $issueDate = now()->toDateString();

        $allocated = $sequences->nextInvoiceNumber(
            companyId: $company->id,
            documentType: 'invoice',
            issueDate: $issueDate,
        );

        $invoice = DB::transaction(function () use ($quote, $invoiceService, $company, $allocated, $issueDate) {
            $invoice = Invoice::create([
                'company_id' => $company->id,
                'sequence_id' => $allocated['sequence_id'],
                'invoice_number' => $allocated['number'],
                'document_type' => 'invoice',
                'status' => 'draft',
                'contact_id' => $quote->contact_id,
                'client_snapshot' => null,
                'issue_date' => $issueDate,
                'due_date' => Carbon::parse($issueDate)->addDays(30)->toDateString(),
                'payment_mode' => null,
                'currency' => $quote->currency?->code ?? ($company->currency ?? 'DZD'),
                'subtotal_ht' => 0,
                'total_vat' => 0,
                'total_ttc' => 0,
                'notes' => $quote->notes,
                'original_invoice_id' => null,
                'issued_at' => null,
                'issued_by' => null,
                'pdf_path' => null,
                'journal_entry_id' => null,
            ]);

            $lines = $quote->lines->map(function ($line, int $index) {
                return [
                    'designation' => $line->description,
                    'quantity' => (float) $line->quantity,
                    'unit' => null,
                    'unit_price_ht' => (float) $line->unit_price,
                    'discount_pct' => 0,
                    'tax_rate_id' => null,
                    'vat_rate_pct' => (float) $line->vat_rate,
                    'account_id' => null,
                    'sort_order' => $index,
                ];
            })->toArray();

            $invoiceService->saveLines($invoice, $lines);

            $quote->update([
                'invoice_id' => $invoice->id,
                'updated_by_user_id' => request()->user()?->id,
                'status' => $quote->status === 'draft' ? 'accepted' : $quote->status,
            ]);

            return $invoice;
        });

        return redirect()
            ->route('invoices.edit', $invoice)
            ->with('success', sprintf('Invoice created from quote %s.', $quote->number));
    }

    private function transition(Quote $quote, array $from, string $to, string $message): RedirectResponse
    {
        $this->authorizeQuote($quote);
        abort_unless(in_array($quote->status, $from, true), 422, 'Transition de statut invalide.');

        $quote->update([
            'status' => $to,
            'updated_by_user_id' => request()->user()?->id,
        ]);

        return back()->with('success', $message);
    }

    private function authorizeQuote(Quote $quote): void
    {
        abort_unless($quote->company_id === app('currentCompany')->id, 403, 'Accès non autorisé à ce devis');
    }

    private function authorizeQuoteMutation(Request $request): void
    {
        $user = $request->user();
        abort_if(! $user, 403, 'Utilisateur non authentifié');
        abort_unless($user->hasAnyRole(['owner', 'accountant', 'admin']), 403, 'Permission insuffisante.');
    }

    private function formPayload(): array
    {
        $company = app('currentCompany');

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

        $currencies = Currency::query()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'is_base']);

        return [
            'quote' => null,
            'taxRates' => $taxRates,
            'accounts' => $accounts,
            'currencies' => $currencies,
            'prefillContact' => null,
        ];
    }

    private function nextQuoteNumber(string $companyId, string $issueDate): string
    {
        $year = Carbon::parse($issueDate)->format('Y');

        return DB::transaction(function () use ($companyId, $year) {
            $numbers = Quote::query()
                ->withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('number', 'like', sprintf('DEV-%s-%%', $year))
                ->lockForUpdate()
                ->pluck('number');

            $max = 0;
            foreach ($numbers as $number) {
                if (preg_match('/^DEV-\d{4}-(\d{5})$/', (string) $number, $matches)) {
                    $max = max($max, (int) $matches[1]);
                }
            }

            return sprintf('DEV-%s-%05d', $year, $max + 1);
        });
    }
}
