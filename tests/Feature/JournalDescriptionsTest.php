<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceVatBucket;
use App\Models\User;
use App\Services\JournalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class JournalDescriptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_entry_description_includes_invoice_number_and_contact(): void
    {
        $company = Company::query()->create($this->companyPayload());
        $contact = Contact::query()->create($this->contactPayload($company->id, 'Client Delta'));
        app()->instance('currentCompany', $company);
        $this->seedAccounts($company->id);

        $invoice = Invoice::query()->create([
            'company_id' => $company->id,
            'invoice_number' => 'FAC-2026-0001',
            'document_type' => 'invoice',
            'status' => 'draft',
            'contact_id' => $contact->id,
            'issue_date' => '2026-04-23',
            'due_date' => '2026-05-23',
            'payment_mode' => 'Virement bancaire',
            'currency' => 'DZD',
            'subtotal_ht' => 1000,
            'total_vat' => 190,
            'total_ttc' => 1190,
        ]);

        InvoiceVatBucket::query()->create([
            'invoice_id' => $invoice->id,
            'rate_pct' => 19,
            'base_ht' => 1000,
            'vat_amount' => 190,
        ]);

        $entry = app(JournalService::class)->draftSalesEntry($invoice, $company);
        $this->assertSame('Facture client FAC-2026-0001 — Client Delta', $entry->description);
    }

    public function test_purchase_entry_description_falls_back_to_contact_then_generic(): void
    {
        $company = Company::query()->create($this->companyPayload());
        $contact = Contact::query()->create($this->contactPayload($company->id, 'Fournisseur Atlas', 'supplier'));
        app()->instance('currentCompany', $company);
        $this->seedAccounts($company->id);

        $expenseWithContact = Expense::query()->create([
            'company_id' => $company->id,
            'contact_id' => $contact->id,
            'expense_date' => '2026-04-23',
            'total_ht' => 1000,
            'total_vat' => 190,
            'total_ttc' => 1190,
            'status' => 'draft',
        ]);

        $entryWithContact = app(JournalService::class)->draftPurchaseEntry($expenseWithContact, $company);
        $this->assertSame('Achat Fournisseur Atlas', $entryWithContact->description);

        $expenseWithoutContact = Expense::query()->create([
            'company_id' => $company->id,
            'expense_date' => '2026-04-24',
            'total_ht' => 500,
            'total_vat' => 0,
            'total_ttc' => 500,
            'status' => 'draft',
        ]);

        $entryWithoutContact = app(JournalService::class)->draftPurchaseEntry($expenseWithoutContact, $company);
        $this->assertSame('Charge fournisseur', $entryWithoutContact->description);
    }

    public function test_reversal_description_uses_extourne_pattern_when_reason_missing(): void
    {
        $company = Company::query()->create($this->companyPayload());
        $contact = Contact::query()->create($this->contactPayload($company->id, 'Client Nord'));
        $user = User::factory()->create();
        app()->instance('currentCompany', $company);
        $this->seedAccounts($company->id);

        $invoice = Invoice::query()->create([
            'company_id' => $company->id,
            'invoice_number' => 'FAC-2026-0005',
            'document_type' => 'invoice',
            'status' => 'draft',
            'contact_id' => $contact->id,
            'issue_date' => '2026-04-23',
            'currency' => 'DZD',
            'subtotal_ht' => 1000,
            'total_vat' => 190,
            'total_ttc' => 1190,
        ]);

        InvoiceVatBucket::query()->create([
            'invoice_id' => $invoice->id,
            'rate_pct' => 19,
            'base_ht' => 1000,
            'vat_amount' => 190,
        ]);

        $service = app(JournalService::class);
        $entry = $service->draftSalesEntry($invoice, $company);
        $reversal = $service->reverseEntry($entry, $company, $user);

        $this->assertStringStartsWith('Extourne de ', $reversal->description);
    }

    private function companyPayload(): array
    {
        $suffix = Str::upper(Str::random(10));

        return [
            'raison_sociale' => 'Company '.$suffix,
            'forme_juridique' => 'EURL',
            'nif' => 'NIF-'.$suffix,
            'nis' => 'NIS-'.$suffix,
            'rc' => 'RC-'.$suffix,
            'address_line1' => 'Adresse '.$suffix,
            'address_wilaya' => 'Alger',
            'tax_regime' => 'IBS',
            'vat_registered' => true,
            'fiscal_year_end' => 12,
            'currency' => 'DZD',
            'status' => 'active',
        ];
    }

    private function contactPayload(string $companyId, string $name, string $type = 'client'): array
    {
        return [
            'company_id' => $companyId,
            'type' => $type,
            'entity_type' => 'enterprise',
            'display_name' => $name,
            'is_active' => true,
        ];
    }

    private function seedAccounts(string $companyId): void
    {
        $accounts = [
            ['code' => '411000', 'label' => 'Clients', 'class' => 4, 'type' => 'asset'],
            ['code' => '701000', 'label' => 'Ventes', 'class' => 7, 'type' => 'revenue'],
            ['code' => '445100', 'label' => 'TVA collectée', 'class' => 4, 'type' => 'vat_collected'],
            ['code' => '401000', 'label' => 'Fournisseurs', 'class' => 4, 'type' => 'liability'],
            ['code' => '445600', 'label' => 'TVA déductible', 'class' => 4, 'type' => 'vat_deductible'],
            ['code' => '601000', 'label' => 'Achats', 'class' => 6, 'type' => 'expense'],
        ];

        foreach ($accounts as $account) {
            \App\Models\Account::query()->create([
                'company_id' => $companyId,
                'code' => $account['code'],
                'label' => $account['label'],
                'class' => $account['class'],
                'type' => $account['type'],
                'is_system' => false,
                'is_active' => true,
            ]);
        }
    }
}
