<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SuggestContactsDefaultsTest extends TestCase
{
    use RefreshDatabase;

    public function test_contacts_suggest_includes_default_fields(): void
    {
        $company = Company::query()->create($this->companyPayload());
        $user = User::factory()->create();
        app()->instance('currentCompany', $company);

        Contact::query()->create([
            'company_id' => $company->id,
            'type' => 'supplier',
            'entity_type' => 'enterprise',
            'display_name' => 'Fournisseur Alpha',
            'email' => 'alpha@example.test',
            'default_payment_terms_days' => 45,
            'default_payment_mode' => 'Chèque',
            'default_expense_account_id' => (string) Str::uuid(),
            'default_tax_rate_id' => (string) Str::uuid(),
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)
            ->withoutMiddleware()
            ->getJson('/suggest/contacts?q=Fou&type=supplier')
            ->assertOk();

        $response->assertJsonPath('data.0.default_payment_terms_days', 45);
        $response->assertJsonPath('data.0.default_payment_mode', 'Chèque');
        $response->assertJsonStructure([
            'data' => [[
                'id',
                'display_name',
                'type',
                'email',
                'default_payment_terms_days',
                'default_payment_mode',
                'default_expense_account_id',
                'default_tax_rate_id',
            ]],
        ]);
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
