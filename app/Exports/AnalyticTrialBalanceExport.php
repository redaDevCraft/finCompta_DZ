<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;

class AnalyticTrialBalanceExport implements FromCollection
{
    public function __construct(
        protected Collection $rows,
        protected array $filters = []
    ) {}

    public function collection(): Collection
    {
        $sheet = collect();

        $sheet->push(['Balance analytique']);
        $sheet->push([
            'Du',
            $this->filters['date_from'] ?? '—',
            'Au',
            $this->filters['date_to'] ?? '—',
        ]);
        $sheet->push([]);
        $sheet->push(['Compte', 'Axe', 'Section', 'Débit', 'Crédit', 'Solde']);

        foreach ($this->rows as $row) {
            $sheet->push([
                trim(($row['account_code'] ?? '').' - '.($row['account_label'] ?? '')),
                $row['axis_code'] ? "{$row['axis_code']} - {$row['axis_name']}" : '',
                $row['section_code'] ? "{$row['section_code']} - {$row['section_name']}" : 'Non affecté',
                $row['debit'] ?? 0,
                $row['credit'] ?? 0,
                $row['balance'] ?? 0,
            ]);
        }

        $sheet->push([]);
        $sheet->push([
            'Totaux',
            '',
            '',
            (float) $this->rows->sum('debit'),
            (float) $this->rows->sum('credit'),
            (float) $this->rows->sum('balance'),
        ]);

        return $sheet;
    }
}
