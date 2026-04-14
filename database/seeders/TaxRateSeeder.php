<?php

namespace Database\Seeders;

use App\Models\TaxRate;
use Illuminate\Database\Seeder;

class TaxRateSeeder extends Seeder
{
    public function run(): void
    {
        $rates = [
            [
                'label' => 'TVA 19%',
                'rate_percent' => 19.00,
                'tax_type' => 'TVA',
                'is_recoverable' => true,
                'reporting_code' => 'G50-L1',
                'effective_from' => '2024-01-01',
            ],
            [
                'label' => 'TVA 9%',
                'rate_percent' => 9.00,
                'tax_type' => 'TVA',
                'is_recoverable' => true,
                'reporting_code' => 'G50-L2',
                'effective_from' => '2024-01-01',
            ],
            [
                'label' => 'Exonéré (0%)',
                'rate_percent' => 0.00,
                'tax_type' => 'TVA',
                'is_recoverable' => false,
                'reporting_code' => 'G50-L3',
                'effective_from' => '2024-01-01',
            ],
        ];

        foreach ($rates as $rate) {
            TaxRate::query()->updateOrCreate(
                [
                    'company_id' => null,
                    'label' => $rate['label'],
                    'effective_from' => $rate['effective_from'],
                ],
                [
                    'rate_percent' => $rate['rate_percent'],
                    'tax_type' => $rate['tax_type'],
                    'is_recoverable' => $rate['is_recoverable'],
                    'reporting_code' => $rate['reporting_code'],
                    'is_active' => true,
                ]
            );
        }
    }
}