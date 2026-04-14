<?php

namespace App\Services;

use App\Jobs\GenerateInvoicePdf;
use App\Models\Account;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\InvoiceSequence;
use App\Models\InvoiceVatBucket;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\TaxRate;
use App\Services\JournalService;
use App\Services\TaxComputationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class InvoiceService
{
    public function __construct(
        protected JournalService $journalService,
        protected TaxComputationService $taxService
    ) {}

    public function saveLines(Invoice $invoice, array $linesData): void
    {
        DB::transaction(function () use ($invoice, $linesData) {
            // Delete existing lines/buckets
            $invoice->lines()->delete();
            $invoice->vatBuckets()->delete();

            $subtotalHt = 0;
            $totalVat = 0;

            foreach ($linesData as $lineData) {
                $line = $invoice->lines()->create([
                    'account_id' => $lineData['account_id'],
                    'description' => $lineData['description'],
                    'quantity' => $lineData['quantity'],
                    'unit_price' => $lineData['unit_price'],
                    'total_ht' => $lineData['total_ht'],
                ]);

                $subtotalHt += $line->total_ht;

                if ($lineData['tax_rate_id']) {
                    $taxRate = TaxRate::find($lineData['tax_rate_id']);
                    $vatAmount = $line->total_ht * ($taxRate->rate_percent / 100);
                    $totalVat += $vatAmount;

                    $invoice->vatBuckets()->create([
                        'line_id' => $line->id,
                        'tax_rate_id' => $taxRate->id,
                        'base_amount' => $line->total_ht,
                        'vat_amount' => $vatAmount,
                        'recoverable_pct' => $taxRate->recoverable_pct,
                        'recoverable_amount' => $vatAmount * ($taxRate->recoverable_pct / 100),
                    ]);
                }
            }

            $invoice->update([
                'subtotal_ht' => $subtotalHt,
                'total_vat' => $totalVat,
                'total_ttc' => $subtotalHt + $totalVat,
            ]);
        });
    }

    public function issue(Invoice $invoice, $company, $user): array
    {
        try {
            return DB::transaction(function () use ($invoice, $company, $user) {
                $sequence = InvoiceSequence::firstOrCreate(
                    ['company_id' => $company->id, 'document_type' => $invoice->document_type],
                    ['next_number' => 1]
                );

                $invoiceNumber = $company->invoice_prefix . str_pad($sequence->next_number, 6, '0', STR_PAD_LEFT);

                $invoice->update([
                    'invoice_number' => $invoiceNumber,
                    'status' => 'issued',
                    'client_snapshot' => $invoice->contact ? $invoice->contact->replicate()->timestamp(null) : null,
                    'issued_at' => now(),
                    'issued_by' => $user->id,
                ]);

                $sequence->increment('next_number');

                // Generate PDF async
                GenerateInvoicePdf::dispatch($invoice);

                // Post journal entry
                $this->postJournalEntry($invoice);

                return [
                    'success' => true,
                    'invoice_number' => $invoiceNumber,
                ];
            });
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'errors' => ['Erreur lors de l\'émission: ' . $e->getMessage()],
            ];
        }
    }

    public function createCreditNote(Invoice $original, $company, $user): Invoice
    {
        $creditNote = $original->replicate([
            'status',
            'invoice_number',
            'issued_at',
            'issued_by',
            'journal_entry_id',
            'pdf_path',
        ]);
        $creditNote->document_type = 'credit_note';
        $creditNote->contact_id = $original->contact_id;
        $creditNote->issue_date = now();
        $creditNote->due_date = now()->addDays(30);
        $creditNote->status = 'draft';
        $creditNote->push();

        $this->saveLines($creditNote, $original->lines->map(fn($line) => [
            'account_id' => $line->account_id,
            'description' => 'Avoir: ' . $line->description,
            'quantity' => $line->quantity,
            'unit_price' => -$line->unit_price,
            'total_ht' => -$line->total_ht,
            'tax_rate_id' => $line->vatBuckets->first()?->tax_rate_id,
        ])->toArray());

        return $creditNote;
    }

    protected function postJournalEntry(Invoice $invoice): void
    {
        $journalEntry = $this->journalService->createEntry(
            $invoice->company_id,
            'Invoice ' . $invoice->invoice_number,
            $invoice->issue_date
        );

        // Client receivable (411)
        $receivableAccount = Account::where('code', '411')->firstOrFail();
        $journalEntry->lines()->create([
            'account_id' => $receivableAccount->id,
            'debit' => $invoice->total_ttc,
            'credit' => 0,
            'description' => 'Facture ' . $invoice->invoice_number,
        ]);

        // Sales accounts + VAT
        foreach ($invoice->lines as $line) {
            $salesAccount = $line->account; // 7xx sales
            $journalEntry->lines()->create([
                'account_id' => $salesAccount->id,
                'debit' => 0,
                'credit' => $line->total_ht,
                'description' => $line->description,
            ]);
        }

        foreach ($invoice->vatBuckets as $bucket) {
            $vatAccount = Account::where('code', '44566')->firstOrFail(); // VAT collected
            $journalEntry->lines()->create([
                'account_id' => $vatAccount->id,
                'debit' => 0,
                'credit' => $bucket->vat_amount,
                'description' => 'TVA facturée',
            ]);
        }

        $journalEntry->refresh();
        $invoice->update(['journal_entry_id' => $journalEntry->id]);
    }
}

