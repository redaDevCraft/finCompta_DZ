<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            TaxRateSeeder::class,
        ]);

        if (app()->environment('local')) {
            $user = User::query()->firstOrCreate(
                ['email' => 'demo@fincompta.dz'],
                [
                    'name' => 'Demo User',
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ]
            );

            $company = Company::query()->firstOrCreate(
                ['nif' => '000000000000000'],
                [
                    'raison_sociale' => 'FinCompta DZ Demo',
                    'forme_juridique' => 'SARL',
                    'nis' => '000000000000000',
                    'rc' => '00B0000000',
                    'ai' => null,
                    'address_line1' => 'Oran',
                    'address_wilaya' => 'Oran',
                    'tax_regime' => 'IBS',
                    'vat_registered' => true,
                    'fiscal_year_end' => 12,
                    'currency' => 'DZD',
                    'status' => 'active',
                ]
            );

            $existingPivot = DB::table('company_users')
                ->where('company_id', $company->id)
                ->where('user_id', $user->id)
                ->first();

            if (! $existingPivot) {
                DB::table('company_users')->insert([
                    'id' => (string) Str::uuid(),
                    'company_id' => $company->id,
                    'user_id' => $user->id,
                    'role' => 'owner',
                    'granted_at' => now(),
                    'revoked_at' => null,
                ]);
            }

            $this->callWith(CompanyBootstrapSeeder::class, [
                'companyId' => $company->id,
            ]);
        }
    }
}
