<?php

namespace App\Services\Ai;

use App\Enums\AiIntent;

class AiIntentClassifier
{
    /**
     * @var array<string, string[]>
     *
     * Keys are AiIntent::value strings, NOT enums.
     */
    private array $map = [
        'invoices'  => ['facture', 'invoice', 'vente', 'chiffre', 'ca'],
        'expenses'  => ['dépense', 'expense', 'fournisseur', 'achat'],
        'cash_flow' => ['trésorerie', 'banque', 'solde', 'cash'],
        'vat'       => ['tva', 'taxe', 'déclaration', 'g50'],
        'ledger'    => ['journal', 'écriture', 'débit', 'crédit'],
        'clients'   => ['client', 'créance', 'impayé', 'relance'],
        'overview'  => ['résumé', 'bilan', 'situation', 'vue'],
    ];

    public function classify(string $message): AiIntent
    {
        $lower = mb_strtolower($message);

        foreach ($this->map as $intentValue => $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($lower, $kw)) {
                    // Convert string ('invoices', 'expenses', …) back to enum
                    return AiIntent::from($intentValue);
                }
            }
        }

        return AiIntent::UNKNOWN;
    }
}