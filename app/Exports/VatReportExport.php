<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;

class VatReportExport implements FromCollection
{
    public function __construct(protected array $data)
    {
    }

    public function collection(): Collection
    {
        $rows = collect();

        $period = $this->data['period'];
        $label = $period['month']
            ? sprintf('Période %02d/%s', $period['month'], $period['year'])
            : sprintf('Trimestre %s/%s', $period['quarter'], $period['year']);

        $rows->push([$label]);
        $rows->push([]);
        $rows->push(['TVA Collectée']);
        $rows->push(['Taux', 'Base HT', 'TVA']);

        foreach ($this->data['sales_vat_buckets'] as $row) {
            $rows->push([
                $row['rate_pct'],
                $row['base_ht'],
                $row['vat_amount'],
            ]);
        }

        $rows->push([]);
        $rows->push(['TVA Déductible']);
        $rows->push(['Taux', 'Base HT', 'TVA']);

        foreach ($this->data['purchase_vat'] as $row) {
            $rows->push([
                $row['rate_pct'],
                $row['base_ht'],
                $row['vat_amount'],
            ]);
        }

        $rows->push([]);
        $rows->push(['Total collectée', $this->data['totals']['collected']]);
        $rows->push(['Total déductible', $this->data['totals']['deductible']]);
        $rows->push(['Solde TVA', $this->data['totals']['balance']]);

        return $rows;
    }
}