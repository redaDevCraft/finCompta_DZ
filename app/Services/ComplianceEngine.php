<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Invoice;

class ComplianceEngine
{
    public function validateInvoiceForIssuance(Invoice $invoice, Company $company): array
    {
        $errors = [];

        if (empty($company->nif)) {
            $errors[] = 'Vendeur: NIF manquant';
        }

        if (empty($company->nis)) {
            $errors[] = 'Vendeur: NIS manquant';
        }

        if (empty($company->rc)) {
            $errors[] = 'Vendeur: RC manquant';
        }

        if (empty($invoice->issue_date)) {
            $errors[] = 'Date de facture manquante';
        }

        if ($invoice->lines->isEmpty()) {
            $errors[] = 'La facture ne contient aucune ligne';
        }

        foreach ($invoice->lines as $i => $line) {
            if (empty($line->designation)) {
                $lineNumber = $i + 1;
                $errors[] = "Ligne {$lineNumber}: désignation vide";
            }
        }

        if ((float) $invoice->total_ttc <= 0 && $invoice->document_type !== 'credit_note') {
            $errors[] = 'Total TTC doit être positif';
        }

        return $errors;
    }

    public function warnInvoiceForIssuance(Invoice $invoice): array
    {
        $warnings = [];
        $snap = $invoice->client_snapshot ?? [];
        $contact = $invoice->contact;

        if ($contact && $contact->entity_type === 'enterprise' && empty($snap['nif'])) {
            $warnings[] = 'Client professionnel: NIF non renseigné';
        }

        if (empty($invoice->due_date)) {
            $warnings[] = "Aucune date d'échéance définie";
        }

        return $warnings;
    }

    public function verifyTaxConsistency(array $data): array
    {
        $warnings = [];

        $totalHt = isset($data['total_ht']) ? (float) $data['total_ht'] : null;
        $totalVat = isset($data['total_vat']) ? (float) $data['total_vat'] : null;
        $totalTtc = isset($data['total_ttc']) ? (float) $data['total_ttc'] : null;

        if ($totalHt !== null && $totalVat !== null && $totalTtc !== null) {
            $expectedTtc = round($totalHt + $totalVat, 2);

            if (abs($expectedTtc - $totalTtc) > 0.05) {
                $warnings[] = 'Incohérence HT+TVA≠TTC — vérifiez les montants extraits';
            }

            if ($totalHt > 0) {
                $implied = round($totalVat / $totalHt * 100, 1);

                if (
                    !in_array($implied, [0.0, 9.0, 19.0], true)
                    && abs($implied - 9) > 1
                    && abs($implied - 19) > 1
                ) {
                    $warnings[] = "Taux TVA implicite ({$implied}%) inhabituel — vérifiez";
                }
            }
        }

        return $warnings;
    }
}