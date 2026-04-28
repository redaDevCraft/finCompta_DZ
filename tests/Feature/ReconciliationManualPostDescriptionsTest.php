<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Company;
use App\Models\User;
use App\Services\ReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReconciliationManualPostDescriptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_post_generates_contextual_entry_and_line_descriptions(): void
    {
        $company = Company::query()->create($this->companyPayload());
        $user = User::factory()->create();
        app()->instance('currentCompany', $company);

        $bankGl = \App\Models\Account::query()->create([
            'company_id' => $company->id,
            'code' => '512000',
            'label' => 'Banque',
            'class' => 5,
            'type' => 'asset',
            'is_system' => false,
            'is_active' => true,
        ]);

        $receivable = \App\Models\Account::query()->create([
            'company_id' => $company->id,
            'code' => '411100',
            'label' => 'Clients divers',
            'class' => 4,
            'type' => 'asset',
            'is_system' => false,
            'is_active' => true,
        ]);

        $bankAccount = BankAccount::query()->create([
            'id' => (string) Str::uuid(),
            'company_id' => $company->id,
            'bank_name' => 'Banque Test',
            'account_number' => '000111',
            'currency' => 'DZD',
            'gl_account_id' => $bankGl->id,
            'is_active' => true,
        ]);

        $tx = BankTransaction::query()->create([
            'id' => (string) Str::uuid(),
            'company_id' => $company->id,
            'bank_account_id' => $bankAccount->id,
            'transaction_date' => '2026-04-23',
            'label' => 'CLIENT OUEST REGLEMENT',
            'amount' => 2500,
            'direction' => 'credit',
            'reconcile_status' => 'unmatched',
        ]);

        app(ReconciliationService::class)->manualPost($tx, $receivable->id, null, $user);

        $tx->refresh();
        $entry = $tx->journalEntry()->with('lines')->firstOrFail();

        $this->assertNotEmpty($entry->description);
        $this->assertStringContainsString('Règlement client', $entry->description);
        $this->assertTrue($entry->lines->pluck('description')->contains('Règlement client'));
        $this->assertTrue($entry->lines->pluck('description')->contains('Mouvement bancaire'));
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
}
