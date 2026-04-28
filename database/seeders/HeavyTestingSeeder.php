<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\InvoiceSequence;
use App\Models\Quote;
use App\Models\TaxRate;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\QuoteService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class HeavyTestingSeeder extends Seeder
{
    public function run(): void
    {
        $companies = Company::query()->get(['id', 'currency']);

        if ($companies->isEmpty()) {
            $this->command?->warn('No companies found. Seed base data first.');

            return;
        }

        $contactsPerCompany = $this->intEnv('HEAVY_SEED_CONTACTS_PER_COMPANY', 120);
        $quotesPerCompany = $this->intEnv('HEAVY_SEED_QUOTES_PER_COMPANY', 250);
        $invoicesPerCompany = $this->intEnv('HEAVY_SEED_INVOICES_PER_COMPANY', 400);
        $expensesPerCompany = $this->intEnv('HEAVY_SEED_EXPENSES_PER_COMPANY', 350);
        $maxLinesPerDocument = max(2, $this->intEnv('HEAVY_SEED_MAX_LINES', 6));

        $this->command?->info(sprintf(
            'Heavy seed started: %d company(ies), %d contacts, %d quotes, %d invoices, %d expenses each.',
            $companies->count(),
            $contactsPerCompany,
            $quotesPerCompany,
            $invoicesPerCompany,
            $expensesPerCompany
        ));

        /** @var QuoteService $quoteService */
        $quoteService = app(QuoteService::class);
        /** @var InvoiceService $invoiceService */
        $invoiceService = app(InvoiceService::class);

        foreach ($companies as $company) {
            DB::transaction(function () use (
                $company,
                $contactsPerCompany,
                $quotesPerCompany,
                $invoicesPerCompany,
                $expensesPerCompany,
                $maxLinesPerDocument,
                $quoteService,
                $invoiceService
            ) {
                $createdBy = (int) DB::table('company_users')
                    ->where('company_id', $company->id)
                    ->whereNull('revoked_at')
                    ->value('user_id');

                $taxRate = TaxRate::query()
                    ->where(function ($query) use ($company) {
                        $query->where('company_id', $company->id)
                            ->orWhereNull('company_id');
                    })
                    ->where('is_active', true)
                    ->orderByDesc('rate_percent')
                    ->first(['id', 'rate_percent']);

                $vatRate = (float) ($taxRate?->rate_percent ?? 19.0);
                $taxRateId = $taxRate?->id;

                $sequence = $this->ensureInvoiceSequence($company->id, (int) now()->year);
                $runningSeq = (int) $sequence->last_number;

                $contacts = collect();
                for ($i = 0; $i < $contactsPerCompany; $i++) {
                    $contactType = fake()->randomElement(['client', 'supplier', 'both']);
                    $contacts->push(Contact::query()->create([
                        'company_id' => $company->id,
                        'type' => $contactType,
                        'entity_type' => fake()->randomElement(['individual', 'enterprise']),
                        'display_name' => sprintf('HEAVY-%s-%s', strtoupper($contactType), Str::upper(Str::random(10))),
                        'raison_sociale' => fake()->boolean(70) ? fake()->company() : null,
                        'nif' => fake()->boolean(65) ? (string) fake()->numerify('###############') : null,
                        'nis' => fake()->boolean(50) ? (string) fake()->numerify('###############') : null,
                        'rc' => fake()->boolean(70) ? (string) fake()->bothify('##B#######') : null,
                        'address_line1' => fake()->streetAddress(),
                        'address_wilaya' => fake()->city(),
                        'email' => fake()->safeEmail(),
                        'phone' => fake()->phoneNumber(),
                        'is_active' => true,
                    ]));
                }

                $clientContacts = $contacts->filter(fn (Contact $contact) => in_array($contact->type, ['client', 'both'], true))->values();
                if ($clientContacts->isEmpty()) {
                    $clientContacts = $contacts->values();
                }

                $supplierContacts = $contacts->filter(fn (Contact $contact) => in_array($contact->type, ['supplier', 'both'], true))->values();
                if ($supplierContacts->isEmpty()) {
                    $supplierContacts = $contacts->values();
                }

                for ($i = 0; $i < $quotesPerCompany; $i++) {
                    $contact = $clientContacts->random();
                    $issueDate = Carbon::now()->subDays(random_int(1, 360))->toDateString();
                    $number = sprintf('HVY-DEV-%s-%05d-%03d', now()->format('Y'), $i + 1, random_int(100, 999));

                    $quote = Quote::query()->create([
                        'company_id' => $company->id,
                        'contact_id' => $contact->id,
                        'number' => $number,
                        'status' => fake()->randomElement(['draft', 'sent', 'accepted', 'rejected', 'expired']),
                        'issue_date' => $issueDate,
                        'expiry_date' => Carbon::parse($issueDate)->addDays(random_int(10, 45))->toDateString(),
                        'currency_id' => null,
                        'notes' => fake()->sentence(8),
                        'reference' => 'HVY-Q-'.Str::upper(Str::random(8)),
                        'created_by_user_id' => $createdBy ?: null,
                        'updated_by_user_id' => $createdBy ?: null,
                    ]);

                    $lines = [];
                    $lineCount = random_int(1, $maxLinesPerDocument);
                    for ($line = 0; $line < $lineCount; $line++) {
                        $lines[] = [
                            'description' => fake()->words(random_int(2, 6), true),
                            'quantity' => random_int(1, 12),
                            'unit_price' => fake()->randomFloat(2, 300, 90000),
                            'vat_rate' => $vatRate,
                            'sort_order' => $line,
                        ];
                    }

                    $quoteService->saveLines($quote, $lines);
                }

                for ($i = 0; $i < $invoicesPerCompany; $i++) {
                    $contact = $clientContacts->random();
                    $issueDate = Carbon::now()->subDays(random_int(1, 360));
                    $dueDate = (clone $issueDate)->addDays(random_int(10, 60));
                    $runningSeq++;
                    $invoiceNumber = sprintf('FAC-%s-%05d', $issueDate->format('Y'), $runningSeq);
                    while (Invoice::query()
                        ->where('company_id', $company->id)
                        ->where('invoice_number', $invoiceNumber)
                        ->exists()) {
                        $runningSeq++;
                        $invoiceNumber = sprintf('FAC-%s-%05d', $issueDate->format('Y'), $runningSeq);
                    }

                    $invoice = Invoice::query()->create([
                        'company_id' => $company->id,
                        'sequence_id' => $sequence->id,
                        'invoice_number' => $invoiceNumber,
                        'document_type' => 'invoice',
                        'status' => 'draft',
                        'contact_id' => $contact->id,
                        'client_snapshot' => null,
                        'issue_date' => $issueDate->toDateString(),
                        'due_date' => $dueDate->toDateString(),
                        'payment_mode' => fake()->randomElement(['bank_transfer', 'cash', 'card']),
                        'currency' => $company->currency ?: 'DZD',
                        'subtotal_ht' => 0,
                        'total_vat' => 0,
                        'total_ttc' => 0,
                        'notes' => fake()->sentence(10),
                        'original_invoice_id' => null,
                        'issued_at' => null,
                        'issued_by' => null,
                        'pdf_path' => null,
                        'journal_entry_id' => null,
                    ]);

                    $lines = [];
                    $lineCount = random_int(1, $maxLinesPerDocument);
                    for ($line = 0; $line < $lineCount; $line++) {
                        $quantity = random_int(1, 15);
                        $unitPrice = fake()->randomFloat(2, 250, 120000);
                        $discount = fake()->randomElement([0, 0, 0, 5, 10, 15]);
                        $lines[] = [
                            'designation' => fake()->words(random_int(2, 6), true),
                            'quantity' => $quantity,
                            'unit' => fake()->randomElement(['u', 'h', 'kg', 'm2']),
                            'unit_price_ht' => $unitPrice,
                            'discount_pct' => $discount,
                            'tax_rate_id' => $taxRateId,
                            'vat_rate_pct' => $vatRate,
                            'account_id' => null,
                            'sort_order' => $line,
                        ];
                    }

                    $invoiceService->saveLines($invoice, $lines);

                    $invoiceStatus = fake()->randomElement(['draft', 'issued', 'partially_paid', 'paid', 'voided']);

                    if (in_array($invoiceStatus, ['partially_paid', 'paid'], true)) {
                        $total = (float) $invoice->total_ttc;
                        $paidAmount = $invoiceStatus === 'paid'
                            ? $total
                            : round($total * random_int(20, 80) / 100, 2);

                        InvoicePayment::query()->create([
                            'company_id' => $company->id,
                            'invoice_id' => $invoice->id,
                            'contact_id' => $invoice->contact_id,
                            'date' => $issueDate->copy()->addDays(random_int(1, 20))->toDateString(),
                            'amount' => $paidAmount,
                            'method' => fake()->randomElement(['bank_transfer', 'cash', 'card']),
                            'reference' => 'HVY-PAY-'.Str::upper(Str::random(10)),
                            'created_by_user_id' => $createdBy ?: null,
                        ]);
                    }

                    DB::table('invoices')
                        ->where('id', $invoice->id)
                        ->update([
                            'status' => $invoiceStatus,
                            'issued_at' => in_array($invoiceStatus, ['issued', 'partially_paid', 'paid', 'voided'], true)
                                ? $issueDate->copy()->addHours(2)->toDateTimeString()
                                : null,
                            'issued_by' => in_array($invoiceStatus, ['issued', 'partially_paid', 'paid', 'voided'], true)
                                ? ($createdBy ?: null)
                                : null,
                            'updated_at' => now(),
                        ]);
                }

                for ($i = 0; $i < $expensesPerCompany; $i++) {
                    $contact = $supplierContacts->random();
                    $expenseDate = Carbon::now()->subDays(random_int(1, 360));

                    $expense = Expense::query()->create([
                        'company_id' => $company->id,
                        'contact_id' => $contact->id,
                        'supplier_snapshot' => [
                            'id' => $contact->id,
                            'display_name' => $contact->display_name,
                            'type' => $contact->type,
                        ],
                        'reference' => 'HVY-EXP-'.Str::upper(Str::random(10)),
                        'expense_date' => $expenseDate->toDateString(),
                        'due_date' => $expenseDate->copy()->addDays(random_int(7, 45))->toDateString(),
                        'description' => fake()->sentence(8),
                        'total_ht' => 0,
                        'total_vat' => 0,
                        'total_ttc' => 0,
                        'account_id' => null,
                        'status' => fake()->randomElement(['draft', 'confirmed', 'paid', 'cancelled']),
                        'source_document_id' => null,
                        'ai_extracted' => fake()->boolean(10),
                        'journal_entry_id' => null,
                    ]);

                    $totalHt = 0.0;
                    $totalVat = 0.0;
                    $lineCount = random_int(1, $maxLinesPerDocument);
                    for ($line = 0; $line < $lineCount; $line++) {
                        $amountHt = fake()->randomFloat(2, 150, 70000);
                        $amountVat = round($amountHt * $vatRate / 100, 2);
                        $amountTtc = round($amountHt + $amountVat, 2);

                        $expense->lines()->create([
                            'designation' => fake()->words(random_int(2, 6), true),
                            'amount_ht' => $amountHt,
                            'vat_rate_pct' => $vatRate,
                            'amount_vat' => $amountVat,
                            'amount_ttc' => $amountTtc,
                            'tax_rate_id' => $taxRateId,
                            'account_id' => null,
                            'sort_order' => $line,
                        ]);

                        $totalHt += $amountHt;
                        $totalVat += $amountVat;
                    }

                    $expense->update([
                        'total_ht' => round($totalHt, 2),
                        'total_vat' => round($totalVat, 2),
                        'total_ttc' => round($totalHt + $totalVat, 2),
                    ]);
                }

                $creditNoteCount = max(0, (int) env('HEAVY_SEED_CREDIT_NOTES', 40));
                if ($creditNoteCount > 0) {
                    $companyModel = Company::query()->findOrFail($company->id);
                    $creditUser = $createdBy ? User::query()->find($createdBy) : User::query()->first();
                    if ($creditUser) {
                        $originals = Invoice::query()
                            ->where('company_id', $company->id)
                            ->where('document_type', 'invoice')
                            ->whereIn('status', ['issued', 'partially_paid', 'paid'])
                            ->inRandomOrder()
                            ->limit($creditNoteCount)
                            ->get();
                        foreach ($originals as $original) {
                            try {
                                $invoiceService->createCreditNote($original, $companyModel, $creditUser);
                            } catch (Throwable) {
                                // ignore compliance / edge cases for random heavy data
                            }
                        }
                    }
                }

                $sequence->update([
                    'last_number' => $runningSeq,
                    'total_issued' => max((int) $sequence->total_issued, $runningSeq),
                ]);
            });

            $this->command?->info("Heavy seed complete for company {$company->id}");
        }
    }

    private function ensureInvoiceSequence(string $companyId, int $year): InvoiceSequence
    {
        return InvoiceSequence::query()->firstOrCreate(
            [
                'company_id' => $companyId,
                'document_type' => 'invoice',
                'fiscal_year' => $year,
            ],
            [
                'prefix' => 'FAC-'.$year,
                'last_number' => 0,
                'total_issued' => 0,
                'total_voided' => 0,
                'locked' => false,
            ]
        );
    }

    private function intEnv(string $key, int $default): int
    {
        $value = env($key, $default);

        return max(1, (int) $value);
    }
}
