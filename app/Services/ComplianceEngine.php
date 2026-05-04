<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceSequence;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class ComplianceEngine
{
    public function validateInvoice(Invoice $invoice): array
    {
        $warnings = [];
        $errors = [];

        $company = $invoice->company;
        $issueDate = Carbon::parse($invoice->issue_date);

        // Algerian VAT rules
        if ($company && $company->vat_registered) {
            if ((float) $invoice->total_vat === 0.0) {
                $warnings[] = 'Facture HT 0% TVA - vérifier régime fiscal client';
            }

            if ($invoice->contact && ! $invoice->contact->nif) {
                $warnings[] = 'NIF client manquant pour facturation TVA';
            }

            $sequence = InvoiceSequence::query()
                ->where('company_id', $company->id)
                ->where('document_type', $invoice->document_type)
                ->where('fiscal_year', $issueDate->year)
                ->first();

            if (! $sequence) {
                $errors[] = 'Séquence de numérotation introuvable pour ce type de document.';
            } elseif ($sequence->locked) {
                $errors[] = 'Séquence de numérotation verrouillée pour ce type.';
            }
        }

        // SCF posting rules
        if ($invoice->isIssued() && ! $invoice->journal_entry_id) {
            $errors[] = 'Facture émise sans écriture comptable associée.';
        }

        // Late issuance
        if ($issueDate->diffInDays(now()) > 7) {
            $warnings[] = 'Facture émise avec retard (>7 jours).';
        }

        return [
            'errors' => $errors,
            'warnings' => $warnings,
            'compliance_score' => $this->calculateScore($errors, $warnings),
        ];
    }

    /**
     * Return the VAT-declaration compliance status for the given period.
     *
     * This function is defensive: the `vat_declarations` table is optional in
     * the MVP, and may not exist on all deployments. When it's absent we fall
     * back to a "not_implemented" result so callers (dashboard cards, admin
     * reports) can decide how to render a neutral state instead of crashing.
     */
    public function validateVatDeclaration(Company $company, Carbon $from, Carbon $to): array
    {
        $period = sprintf('%04d-%02d', $from->year, $from->month);

        $invoicesCount = Invoice::query()
            ->forCompany($company->id)
            ->issued()
            ->whereBetween('issue_date', [$from->toDateString(), $to->toDateString()])
            ->count();

        if ($invoicesCount === 0) {
            return [
                'status' => 'not_applicable',
                'period' => $period,
                'invoices_count' => 0,
                'message' => 'Aucune facture émise sur la période — déclaration non requise.',
            ];
        }

        if (! Schema::hasTable('vat_declarations')) {
            return [
                'status' => 'not_implemented',
                'period' => $period,
                'invoices_count' => $invoicesCount,
                'message' => 'Le suivi des déclarations TVA n\'est pas encore activé.',
            ];
        }

        $declarationExists = $company->vatDeclarations()
            ->where('period', $period)
            ->exists();

        if (! $declarationExists) {
            return [
                'status' => 'required',
                'period' => $period,
                'invoices_count' => $invoicesCount,
                'message' => 'Déclaration TVA CA3/CA4 manquante.',
            ];
        }

        return [
            'status' => 'compliant',
            'period' => $period,
            'invoices_count' => $invoicesCount,
            'message' => 'Déclaration déposée.',
        ];
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
    $isCreditNote = $invoice->document_type === 'credit_note';

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

        // Credit notes legitimately have negative unit prices — skip this check
        if (! $isCreditNote && (float) $line->unit_price_ht < 0) {
            $errors[] = sprintf('La ligne %d a un prix unitaire négatif.', $index + 1);
        }
    }

    if ($invoice->contact_id === null) {
        $errors[] = 'La facture doit être associée à un client.';
    }

    if (empty($invoice->issue_date)) {
        $errors[] = 'La date d\'émission est obligatoire.';
    }

    // Credit notes legitimately have a negative total_ttc — skip this check
    if (! $isCreditNote && (float) $invoice->total_ttc < 0) {
        $errors[] = 'Le montant total TTC ne peut pas être négatif.';
    }

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
    $isCreditNote = $invoice->document_type === 'credit_note';

    if (! $isCreditNote && $invoice->due_date === null) {
        $warnings[] = 'Aucune date d\'échéance définie.';
    }

    if ($invoice->due_date && $invoice->due_date->lt($invoice->issue_date)) {
        $warnings[] = 'La date d\'échéance est antérieure à la date d\'émission.';
    }

    if (empty($invoice->payment_mode)) {
        $warnings[] = 'Le mode de paiement n\'est pas renseigné.';
    }

    if (! $isCreditNote && $invoice->lines->every(fn($l) => (float) $l->vat_rate_pct === 0.0)) {
        $warnings[] = 'Aucune ligne n\'est soumise à la TVA. Vérifiez l\'exonération.';
    }

    return $warnings;
}
}

