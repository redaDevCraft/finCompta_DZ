<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Invoice;
use Carbon\Carbon;

class ComplianceEngine
{
    public function validateInvoice(Invoice $invoice): array
    {
        $warnings = [];
        $errors = [];

        $company = $invoice->company;
        $issueDate = Carbon::parse($invoice->issue_date);

        // Algerian VAT rules
        if ($company->vat_registered) {
            if ($invoice->total_vat === 0) {
                $warnings[] = 'Facture HT 0% TVA - vérifier régime fiscal client';
            }

            if ($invoice->contact && ! $invoice->contact->nif) {
                $warnings[] = 'NIF client manquant pour facturation TVA';
            }

            $sequence = $company->invoiceSequences()->forType($invoice->document_type)->forYear($issueDate->year)->first();
            if (! $sequence || $sequence->locked) {
                $errors[] = 'Séquence numérotation verrouillée pour ce type';
            }
        }

        // SCF posting rules
        if ($invoice->isIssued() && ! $invoice->journal_entry_id) {
            $errors[] = 'Facture émise sans écriture comptable associée';
        }

        // Late issuance
        if ($issueDate->diffInDays(now()) > 7) {
            $warnings[] = 'Facture émise avec retard (>7 jours)';
        }

        return [
            'errors' => $errors,
            'warnings' => $warnings,
            'compliance_score' => $this->calculateScore($errors, $warnings),
        ];
    }

    public function validateVatDeclaration(Company $company, Carbon $from, Carbon $to): array
    {
        $period = "{$from->year}-{$from->month}";

        // Check 100% declarations filed
        $invoicesCount = Invoice::companyId($company->id)->issued()->whereBetween('issue_date', [$from, $to])->count();
        $declarationExists = $company->vatDeclarations()->where('period', $period)->exists();

        if ($invoicesCount > 0 && ! $declarationExists) {
            return ['status' => 'required', 'message' => 'Déclaration TVA CA3/CA4 manquante'];
        }

        return ['status' => 'compliant'];
    }

    private function calculateScore(array $errors, array $warnings): float
    {
        $maxScore = 100;
        $score = $maxScore;
        $score -= count($errors) * 25;
        $score -= count($warnings) * 5;
        return max(0, $score);
    }
      /**
     * Hard validation errors — any non-empty return blocks issuance entirely.
     *
     * @return string[]
     */
    public function validateInvoiceForIssuance(Invoice $invoice, Company $company): array
    {
        $errors = [];

        if ($invoice->lines->isEmpty()) {
            $errors[] = 'La facture doit contenir au moins une ligne.';
        }

        foreach ($invoice->lines as $index => $line) {
            if (empty($line->designation)) {
                $errors[] = sprintf('La ligne %d n\'a pas de désignation.', $index + 1);
            }

            if ((float) $line->quantity <= 0) {
                $errors[] = sprintf('La ligne %d a une quantité invalide.', $index + 1);
            }

            if ((float) $line->unit_price_ht < 0) {
                $errors[] = sprintf('La ligne %d a un prix unitaire négatif.', $index + 1);
            }
        }

        if ($invoice->contact_id === null) {
            $errors[] = 'La facture doit être associée à un client.';
        }

        if (empty($invoice->issue_date)) {
            $errors[] = 'La date d\'émission est obligatoire.';
        }

        if ((float) $invoice->total_ttc < 0) {
            $errors[] = 'Le montant total TTC ne peut pas être négatif.';
        }

        // SCF compliance: company must have NIF for VAT invoices
        if ($company->vat_registered && empty($company->nif)) {
            $errors[] = 'Le NIF de l\'entreprise est requis pour émettre une facture TVA.';
        }

        return $errors;
    }

    /**
     * Soft warnings — issuance proceeds but warnings are shown to the user.
     *
     * @return string[]
     */
    public function warnInvoiceForIssuance(Invoice $invoice): array
    {
        $warnings = [];

        if ($invoice->due_date === null) {
            $warnings[] = 'Aucune date d\'échéance définie.';
        }

        if ($invoice->due_date && $invoice->due_date->lt($invoice->issue_date)) {
            $warnings[] = 'La date d\'échéance est antérieure à la date d\'émission.';
        }

        if (empty($invoice->payment_mode)) {
            $warnings[] = 'Le mode de paiement n\'est pas renseigné.';
        }

        if ($invoice->lines->every(fn($l) => (float) $l->vat_rate_pct === 0.0)) {
            $warnings[] = 'Aucune ligne n\'est soumise à la TVA. Vérifiez l\'exonération.';
        }

        return $warnings;
    }
}

