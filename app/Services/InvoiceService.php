<?php

namespace App\Services;

use App\Jobs\GenerateInvoicePdf;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceSequence;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\UniqueConstraintViolationException;


class InvoiceService
{
    public function __construct(
        protected TaxComputationService $tax,
        protected ComplianceEngine $compliance,
        protected JournalService $journal,
        protected PdfService $pdf,
    ) {
    }

    public function saveLines(Invoice $invoice, array $linesData): void
    {
        DB::transaction(function () use ($invoice, $linesData) {
            $invoice->load('lines', 'vatBuckets');

            $invoice->lines()->delete();

            $computedLines = collect($linesData)
                ->values()
                ->map(function (array $line, int $index) {
                    $normalized = [
                        'designation' => $line['designation'] ?? null,
                        'quantity' => (float) ($line['quantity'] ?? 0),
                        'unit' => $line['unit'] ?? null,
                        'unit_price_ht' => (float) ($line['unit_price_ht'] ?? 0),
                        'discount_pct' => (float) ($line['discount_pct'] ?? 0),
                        'tax_rate_id' => $line['tax_rate_id'] ?? null,
                        'vat_rate_pct' => (float) ($line['vat_rate_pct'] ?? 0),
                        'account_id' => $line['account_id'] ?? null,
                        'sort_order' => $line['sort_order'] ?? $index,
                    ];

                    $computed = $this->tax->computeLine($normalized);

                    return array_merge($normalized, $computed);
                });

            $invoice->lines()->createMany($computedLines->toArray());

            $totals = $this->tax->computeTotals($computedLines);

            $invoice->fill([
                'subtotal_ht' => $totals['subtotal_ht'],
                'total_vat' => $totals['total_vat'],
                'total_ttc' => $totals['total_ttc'],
            ]);

            $invoice->save();

            $invoice->vatBuckets()->delete();

            $vatBuckets = $this->tax->computeVatBuckets($computedLines);

            foreach ($vatBuckets as $bucket) {
                $invoice->vatBuckets()->create([
                    'tax_rate_id' => $this->resolveBucketTaxRateId($computedLines, (float) $bucket['rate_pct']),
                    'rate_pct' => $bucket['rate_pct'],
                    'base_ht' => $bucket['base_ht'],
                    'vat_amount' => $bucket['vat_amount'],
                ]);
            }
        });
    }

    public function issue(Invoice $invoice, Company $company, User $user): array
    {
        $invoice->load('lines', 'contact');

        $errors = $this->compliance->validateInvoiceForIssuance($invoice, $company);

        if (!empty($errors)) {
            return [
                'success' => false,
                'errors' => $errors,
            ];
        }

        DB::transaction(function () use ($invoice, $company, $user) {
            $number = $this->assignSequenceNumber($invoice, $company);

            $invoice->client_snapshot = $invoice->contact?->toArray();

            $invoice->update([
                'status' => 'issued',
                'invoice_number' => $number,
                'issued_at' => now(),
                'issued_by' => $user->id,
            ]);

            $invoice->refresh();
            $invoice->load('lines', 'contact', 'vatBuckets');

            $this->journal->draftSalesEntry($invoice, $company);

            GenerateInvoicePdf::dispatch($invoice->id)->onQueue('pdf');
        });

        $invoice->refresh();
        $invoice->load('contact');

        return [
            'success' => true,
            'warnings' => $this->compliance->warnInvoiceForIssuance($invoice),
        ];
    }

    public function void(Invoice $invoice, User $user): void
    {
        if ($invoice->status === 'draft') {
            abort(422, 'Un brouillon ne peut être annulé, supprimez-le');
        }

        $invoice->update([
            'status' => 'voided',
        ]);
    }

    public function createCreditNote(Invoice $originalInvoice, Company $company, User $user): Invoice
    {
        $originalInvoice->load('lines');

        return DB::transaction(function () use ($originalInvoice, $company) {
            $creditNote = Invoice::create([
                'company_id' => $company->id,
                'sequence_id' => $originalInvoice->sequence_id,
                'invoice_number' => null,
                'document_type' => 'credit_note',
                'status' => 'draft',
                'contact_id' => $originalInvoice->contact_id,
                'client_snapshot' => null,
                'issue_date' => now()->toDateString(),
                'due_date' => null,
                'payment_mode' => $originalInvoice->payment_mode,
                'currency' => $originalInvoice->currency,
                'subtotal_ht' => 0,
                'total_vat' => 0,
                'total_ttc' => 0,
                'notes' => $originalInvoice->notes,
                'original_invoice_id' => $originalInvoice->id,
                'journal_entry_id' => null,
                'pdf_path' => null,
                'issued_at' => null,
                'issued_by' => null,
            ]);

            $linesData = $originalInvoice->lines
                ->map(function ($line, int $index) {
                    return [
                        'designation' => $line->designation,
                        'quantity' => (float) $line->quantity,
                        'unit' => $line->unit,
                        'unit_price_ht' => -1 * abs((float) $line->unit_price_ht),
                        'discount_pct' => (float) $line->discount_pct,
                        'tax_rate_id' => $line->tax_rate_id,
                        'vat_rate_pct' => (float) $line->vat_rate_pct,
                        'account_id' => $line->account_id,
                        'sort_order' => $index,
                    ];
                })
                ->toArray();

            $this->saveLines($creditNote, $linesData);

            return $creditNote->fresh(['lines', 'vatBuckets', 'contact']);
        });
    }
     public function assignSequenceNumber(Invoice $invoice): string
    {
        try {
            return $this->assignSequenceNumberOnce($invoice);
        } catch (UniqueConstraintViolationException $e) {
            return $this->assignSequenceNumberOnce($invoice);
        }
    }

    protected function assignSequenceNumberOnce(Invoice $invoice): string
    {
        return DB::transaction(function () use ($invoice) {
            $year = (int) $invoice->issue_date->format('Y');

            $sequence = InvoiceSequence::query()
                ->where('company_id', $invoice->company_id)
                ->where('document_type', $invoice->document_type)
                ->where('fiscal_year', $year)
                ->lockForUpdate()
                ->firstOrFail();

            $nextNumber = $sequence->last_number + 1;
            $formattedNumber = sprintf(
                '%s%05d',
                $sequence->prefix ? $sequence->prefix . '-' : '',
                $nextNumber
            );

            $sequence->update([
                'last_number' => $nextNumber,
                'total_issued' => $sequence->total_issued + 1,
            ]);

            $invoice->forceFill([
                'invoice_number' => $formattedNumber,
            ])->save();

            return $formattedNumber;
        });
    }



    private function resolveBucketTaxRateId($computedLines, float $ratePct): ?string
    {
        $match = $computedLines->first(function ($line) use ($ratePct) {
            return (float) ($line['vat_rate_pct'] ?? 0) === $ratePct;
        });

        return $match['tax_rate_id'] ?? null;
    }
}