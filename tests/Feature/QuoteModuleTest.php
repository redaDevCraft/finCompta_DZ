<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class QuoteModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_quote_with_lines(): void
    {
        $company = $this->makeCompany();
        $user = $this->makeUserWithRole('owner');
        $contact = Contact::query()->create([
            'company_id' => $company->id,
            'type' => 'client',
            'entity_type' => 'enterprise',
            'display_name' => 'Client Test',
            'is_active' => true,
        ]);

        app()->instance('currentCompany', $company);

        $payload = [
            'contact_id' => $contact->id,
            'issue_date' => now()->toDateString(),
            'expiry_date' => now()->addDays(15)->toDateString(),
            'reference' => 'DEV-REF',
            'notes' => 'Note test',
            'lines' => [
                [
                    'description' => 'Prestation A',
                    'quantity' => 2,
                    'unit_price' => 1000,
                    'vat_rate' => 19,
                ],
            ],
        ];

        $this->actingAs($user)
            ->withoutMiddleware()
            ->post('/quotes', $payload)
            ->assertRedirect();

        $quote = Quote::query()->first();
        $this->assertNotNull($quote);
        $this->assertSame($company->id, $quote->company_id);
        $this->assertSame('draft', $quote->status);
        $this->assertCount(1, $quote->lines);
    }

    public function test_quote_show_is_tenant_isolated(): void
    {
        $companyA = $this->makeCompany();
        $companyB = $this->makeCompany();
        $user = $this->makeUserWithRole('owner');

        $quote = Quote::query()->create([
            'company_id' => $companyA->id,
            'number' => 'DEV-2026-00001',
            'status' => 'draft',
            'issue_date' => now()->toDateString(),
            'subtotal' => 0,
            'discount_total' => 0,
            'tax_total' => 0,
            'total' => 0,
            'created_by_user_id' => $user->id,
            'updated_by_user_id' => $user->id,
        ]);

        app()->instance('currentCompany', $companyB);

        $this->actingAs($user)
            ->withoutMiddleware()
            ->get("/quotes/{$quote->id}")
            ->assertForbidden();
    }

    public function test_quote_status_transitions_enforce_rules(): void
    {
        $company = $this->makeCompany();
        $user = $this->makeUserWithRole('owner');

        $quote = Quote::query()->create([
            'company_id' => $company->id,
            'number' => 'DEV-2026-00002',
            'status' => 'draft',
            'issue_date' => now()->toDateString(),
            'subtotal' => 0,
            'discount_total' => 0,
            'tax_total' => 0,
            'total' => 0,
            'created_by_user_id' => $user->id,
            'updated_by_user_id' => $user->id,
        ]);

        app()->instance('currentCompany', $company);

        $this->actingAs($user)->withoutMiddleware()->post("/quotes/{$quote->id}/send")->assertRedirect();
        $quote->refresh();
        $this->assertSame('sent', $quote->status);

        $this->actingAs($user)->withoutMiddleware()->post("/quotes/{$quote->id}/accept")->assertRedirect();
        $quote->refresh();
        $this->assertSame('accepted', $quote->status);

        $this->actingAs($user)->withoutMiddleware()->post("/quotes/{$quote->id}/reject")->assertStatus(422);
    }

    public function test_quote_conversion_creates_invoice_and_backlink(): void
    {
        $company = $this->makeCompany();
        $user = $this->makeUserWithRole('owner');
        $contact = Contact::query()->create([
            'company_id' => $company->id,
            'type' => 'client',
            'entity_type' => 'enterprise',
            'display_name' => 'Client Convert',
            'is_active' => true,
        ]);

        $quote = Quote::query()->create([
            'company_id' => $company->id,
            'contact_id' => $contact->id,
            'number' => 'DEV-2026-00003',
            'status' => 'sent',
            'issue_date' => now()->toDateString(),
            'subtotal' => 1000,
            'discount_total' => 0,
            'tax_total' => 190,
            'total' => 1190,
            'created_by_user_id' => $user->id,
            'updated_by_user_id' => $user->id,
        ]);

        $quote->lines()->create([
            'description' => 'Ligne conversion',
            'quantity' => 1,
            'unit_price' => 1000,
            'vat_rate' => 19,
            'line_total' => 1190,
            'sort_order' => 0,
        ]);

        app()->instance('currentCompany', $company);

        $this->actingAs($user)
            ->withoutMiddleware()
            ->post("/quotes/{$quote->id}/convert-to-invoice")
            ->assertRedirect();

        $quote->refresh();
        $this->assertNotNull($quote->invoice_id);

        $invoice = DB::table('invoices')->where('id', $quote->invoice_id)->first();
        $this->assertNotNull($invoice);
        $this->assertSame('draft', $invoice->status);
        $this->assertEquals($contact->id, $invoice->contact_id);
        $this->assertDatabaseHas('invoice_lines', [
            'invoice_id' => $quote->invoice_id,
        ]);
    }

    private function makeCompany(): Company
    {
        $id = (string) Str::uuid();
        $suffix = Str::upper(Str::random(8));

        DB::table('companies')->insert([
            'id' => $id,
            'raison_sociale' => 'Company '.$suffix,
            'forme_juridique' => 'EURL',
            'nif' => 'NIF-'.$suffix,
            'nis' => 'NIS-'.$suffix,
            'rc' => 'RC-'.$suffix,
            'ai' => null,
            'address_line1' => 'Adresse '.$suffix,
            'address_wilaya' => 'Alger',
            'tax_regime' => 'IBS',
            'vat_registered' => true,
            'fiscal_year_end' => 12,
            'currency' => 'DZD',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ]);

        return Company::query()->findOrFail($id);
    }

    private function makeUserWithRole(string $roleName): User
    {
        Role::query()->firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($roleName);

        return $user;
    }
}
