<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\FiscalPeriod;
use App\Models\InvoiceSequence;
use App\Models\TaxRate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;


class CompanyBootstrapSeeder extends Seeder
{
    public function __construct(protected ?string $companyId = null)
    {
    }

    public function run(): void
    {
        if (! $this->companyId) {
            return;
        }

        $company = Company::query()->findOrFail($this->companyId);
        $currentYear = (int) now()->format('Y');

        TaxRate::query()
            ->whereNull('company_id')
            ->get()
            ->each(function (TaxRate $rate) use ($company) {
                TaxRate::query()->updateOrCreate(
                    [
                        'company_id' => $company->id,
                        'label' => $rate->label,
                        'effective_from' => $rate->effective_from,
                    ],
                    [
                        'rate_percent' => $rate->rate_percent,
                        'tax_type' => $rate->tax_type,
                        'is_recoverable' => $rate->is_recoverable,
                        'reporting_code' => $rate->reporting_code,
                        'effective_to' => $rate->effective_to,
                        'is_active' => $rate->is_active,
                    ]
                );
            });

        (new ScfAccountsSeeder($company->id))->run();

        foreach (range(1, 12) as $month) {
            $existing = FiscalPeriod::query()
                ->where('company_id', $company->id)
                ->where('year', $currentYear)
                ->where('month', $month)
                ->first();
        
            if ($existing) {
                $existing->update([
                    'status' => 'open',
                    'locked_at' => null,
                    'locked_by' => null,
                ]);
            } else {
                FiscalPeriod::query()->create([
                    'id' => (string) Str::uuid(),
                    'company_id' => $company->id,
                    'year' => $currentYear,
                    'month' => $month,
                    'status' => 'open',
                    'locked_at' => null,
                    'locked_by' => null,
                ]);
            }
        }
        

        $sequences = [
            ['document_type' => 'invoice', 'prefix' => 'FAC'],
            ['document_type' => 'credit_note', 'prefix' => 'AV'],
            ['document_type' => 'quote', 'prefix' => 'DEV'],
            ['document_type' => 'delivery_note', 'prefix' => 'BL'],
        ];

        foreach ($sequences as $sequence) {
            InvoiceSequence::query()->updateOrCreate(
                [
                    'company_id' => $company->id,
                    'document_type' => $sequence['document_type'],
                    'fiscal_year' => $currentYear,
                ],
                [
                    'prefix' => $sequence['prefix'],
                    'last_number' => 0,
                    'total_issued' => 0,
                    'total_voided' => 0,
                    'locked' => false,
                ]
            );
        }
    }
}