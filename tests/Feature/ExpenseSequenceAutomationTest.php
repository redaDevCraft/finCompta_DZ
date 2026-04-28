<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Expense;
use App\Models\InvoiceSequence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExpenseSequenceAutomationTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_auto_generates_expense_reference_when_missing(): void
    {
        $company = Company::query()->create($this->companyPayload());
        $user = User::factory()->create();
        app()->instance('currentCompany', $company);

        $this->actingAs($user)
            ->withoutMiddleware()
            ->post('/expenses', [
                'expense_date' => '2026-04-23',
                'total_ht' => 1000,
                'total_vat' => 190,
                'total_ttc' => 1190,
            ])
            ->assertRedirect();

        $expense = Expense::query()->firstOrFail();
        $this->assertMatchesRegularExpression('/^DEP-2026-\d{4}$/', (string) $expense->reference);
        $this->assertNotNull($expense->sequence_id);

        $sequence = InvoiceSequence::query()->firstOrFail();
        $this->assertSame('expense', $sequence->document_type);
        $this->assertSame('DEP', $sequence->prefix);
    }

    public function test_store_keeps_manual_reference_without_allocating_sequence(): void
    {
        $company = Company::query()->create($this->companyPayload());
        $user = User::factory()->create();
        app()->instance('currentCompany', $company);

        $this->actingAs($user)
            ->withoutMiddleware()
            ->post('/expenses', [
                'reference' => 'REF-MANUELLE-001',
                'expense_date' => '2026-04-23',
                'total_ht' => 1000,
                'total_vat' => 190,
                'total_ttc' => 1190,
            ])
            ->assertRedirect();

        $expense = Expense::query()->firstOrFail();
        $this->assertSame('REF-MANUELLE-001', $expense->reference);
        $this->assertNull($expense->sequence_id);
        $this->assertSame(0, InvoiceSequence::query()->count());
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
