<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Document;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Quote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GlobalSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));
        if (mb_strlen($query) < 2) {
            return response()->json([
                'query' => $query,
                'results' => [],
                'meta' => ['total' => 0],
            ]);
        }

        $results = collect()
            ->merge($this->searchActions($query))
            ->merge($this->searchInvoices($query))
            ->merge($this->searchExpenses($query))
            ->merge($this->searchContacts($query))
            ->merge($this->searchQuotes($query))
            ->merge($this->searchDocuments($query))
            ->take(60)
            ->values();

        return response()->json([
            'query' => $query,
            'results' => $results,
            'meta' => ['total' => $results->count()],
        ]);
    }

    private function searchActions(string $query)
    {
        $catalog = collect([
            ['section' => 'Actions', 'title' => 'Créer une facture', 'description' => 'Nouvelle facture de vente', 'href' => '/invoices/create', 'keywords' => 'facture vente create'],
            ['section' => 'Actions', 'title' => 'Créer un devis', 'description' => 'Nouveau devis client', 'href' => '/quotes/create', 'keywords' => 'devis quote create'],
            ['section' => 'Actions', 'title' => 'Ajouter une dépense', 'description' => 'Saisie d achat fournisseur', 'href' => '/expenses/create', 'keywords' => 'depense achat create'],
            ['section' => 'Actions', 'title' => 'Saisir une écriture', 'description' => 'Créer une écriture comptable', 'href' => '/ledger/entries/create', 'keywords' => 'ecriture journal comptable'],
            ['section' => 'Actions', 'title' => 'Rapprocher la banque', 'description' => 'Rapprochement bancaire', 'href' => '/bank/reconcile', 'keywords' => 'banque rapprochement match'],
            ['section' => 'Modules', 'title' => 'Tableau de bord', 'description' => 'Vue globale de la société', 'href' => '/dashboard', 'keywords' => 'dashboard home'],
            ['section' => 'Modules', 'title' => 'Factures', 'description' => 'Liste des factures', 'href' => '/invoices', 'keywords' => 'factures ventes'],
            ['section' => 'Modules', 'title' => 'Dépenses', 'description' => 'Liste des dépenses', 'href' => '/expenses', 'keywords' => 'achats depenses'],
            ['section' => 'Modules', 'title' => 'Clients', 'description' => 'Carnet de clients', 'href' => '/clients', 'keywords' => 'clients contacts'],
            ['section' => 'Modules', 'title' => 'Fournisseurs', 'description' => 'Carnet de fournisseurs', 'href' => '/suppliers', 'keywords' => 'fournisseurs contacts'],
            ['section' => 'Modules', 'title' => 'Documents OCR', 'description' => 'Documents scannés et OCR', 'href' => '/documents', 'keywords' => 'documents ocr scans'],
            ['section' => 'Modules', 'title' => 'Journal des opérations', 'description' => 'Journal général', 'href' => '/ledger/journal', 'keywords' => 'journal operations ecritures'],
            ['section' => 'Modules', 'title' => 'Grand Livre', 'description' => 'Mouvements par compte', 'href' => '/ledger/account', 'keywords' => 'grand livre comptes'],
            ['section' => 'Modules', 'title' => 'Balance des comptes', 'description' => 'Balance générale', 'href' => '/ledger/trial-balance', 'keywords' => 'balance comptes'],
            ['section' => 'Modules', 'title' => 'Lettrage', 'description' => 'Lettrage manuel/auto', 'href' => '/ledger/lettering', 'keywords' => 'lettrage clients fournisseurs'],
            ['section' => 'Rapports', 'title' => 'Rapport TVA (G50/G11)', 'description' => 'Déclaration TVA', 'href' => '/reports/vat', 'keywords' => 'tva g50 g11'],
            ['section' => 'Rapports', 'title' => 'Bilan / CPC / TFT', 'description' => 'États financiers', 'href' => '/reports/bilan', 'keywords' => 'bilan cpc tft'],
            ['section' => 'Rapports', 'title' => 'Balance âgée clients', 'description' => 'Encours clients', 'href' => '/reports/aged-receivables', 'keywords' => 'aged receivables clients'],
            ['section' => 'Rapports', 'title' => 'Balance âgée fournisseurs', 'description' => 'Dettes fournisseurs', 'href' => '/reports/aged-payables', 'keywords' => 'aged payables fournisseurs'],
            ['section' => 'Rapports', 'title' => 'Balance analytique', 'description' => 'Analyse par axes', 'href' => '/reports/analytic-trial-balance', 'keywords' => 'analytique balance'],
            ['section' => 'Rapports', 'title' => 'Prévisions de gestion', 'description' => 'Prévisions et scénarios', 'href' => '/reports/predictions', 'keywords' => 'previsions gestion'],
            ['section' => 'Settings', 'title' => 'Plan comptable', 'description' => 'Comptes et référentiels', 'href' => '/settings/accounts', 'keywords' => 'settings comptes referentiels'],
            ['section' => 'Settings', 'title' => 'Journaux', 'description' => 'Paramétrage des journaux', 'href' => '/settings/journals', 'keywords' => 'settings journaux'],
            ['section' => 'Settings', 'title' => 'Périodes fiscales', 'description' => 'Exercices et périodes', 'href' => '/settings/periods', 'keywords' => 'settings periode fiscale'],
            ['section' => 'Settings', 'title' => 'Verrouillage écritures', 'description' => 'Sécurité de clôture', 'href' => '/settings/entry-locks', 'keywords' => 'settings verrouillage'],
            ['section' => 'Settings', 'title' => 'Comptes bancaires', 'description' => 'Banques et IBAN', 'href' => '/settings/bank-accounts', 'keywords' => 'settings banque'],
            ['section' => 'Settings', 'title' => 'Automatisation', 'description' => 'Règles de contrepartie', 'href' => '/settings/auto-counterpart-rules', 'keywords' => 'settings automatisation regles'],
            ['section' => 'Settings', 'title' => 'Comptabilité analytique', 'description' => 'Axes et sections', 'href' => '/settings/analytics', 'keywords' => 'settings analytique'],
            ['section' => 'Account', 'title' => 'Billing', 'description' => 'Abonnement et paiements', 'href' => '/billing', 'keywords' => 'billing subscription payments'],
            ['section' => 'Account', 'title' => 'Profil utilisateur', 'description' => 'Informations du compte', 'href' => '/profile', 'keywords' => 'profil compte utilisateur'],
        ]);

        $needle = mb_strtolower($query);

        return $catalog
            ->filter(function (array $item) use ($needle) {
                $haystack = mb_strtolower(
                    $item['title'].' '.$item['description'].' '.($item['keywords'] ?? '').' '.$item['href']
                );

                return str_contains($haystack, $needle);
            })
            ->take(25)
            ->map(fn (array $item) => [
                'type' => 'action',
                'section' => $item['section'],
                'title' => $item['title'],
                'description' => $item['description'],
                'href' => $item['href'],
            ])
            ->values();
    }

    private function searchInvoices(string $query)
    {
        return Invoice::query()
            ->with('contact:id,display_name,raison_sociale')
            ->where(function ($q) use ($query) {
                $q->where('invoice_number', 'ilike', '%'.$query.'%')
                    ->orWhere('status', 'ilike', '%'.$query.'%')
                    ->orWhere('notes', 'ilike', '%'.$query.'%')
                    ->orWhereHas('contact', function ($contact) use ($query) {
                        $contact->where('display_name', 'ilike', '%'.$query.'%')
                            ->orWhere('raison_sociale', 'ilike', '%'.$query.'%')
                            ->orWhere('nif', 'ilike', '%'.$query.'%');
                    });
            })
            ->orderByDesc('created_at')
            ->limit(10)
            ->get(['id', 'invoice_number', 'status', 'issue_date', 'total_ttc', 'contact_id'])
            ->map(fn (Invoice $invoice) => [
                'type' => 'invoice',
                'section' => 'Factures',
                'title' => $invoice->invoice_number ?: 'Facture sans numéro',
                'description' => trim(($invoice->contact?->display_name ?? 'Sans client').' · '.$invoice->status.' · '.$invoice->total_ttc.' DZD'),
                'href' => '/invoices/'.$invoice->id,
            ]);
    }

    private function searchExpenses(string $query)
    {
        return Expense::query()
            ->with('contact:id,display_name,raison_sociale')
            ->where(function ($q) use ($query) {
                $q->where('reference', 'ilike', '%'.$query.'%')
                    ->orWhere('description', 'ilike', '%'.$query.'%')
                    ->orWhere('status', 'ilike', '%'.$query.'%')
                    ->orWhereHas('contact', function ($contact) use ($query) {
                        $contact->where('display_name', 'ilike', '%'.$query.'%')
                            ->orWhere('raison_sociale', 'ilike', '%'.$query.'%')
                            ->orWhere('nif', 'ilike', '%'.$query.'%');
                    });
            })
            ->orderByDesc('created_at')
            ->limit(10)
            ->get(['id', 'reference', 'status', 'expense_date', 'total_ttc', 'contact_id'])
            ->map(fn (Expense $expense) => [
                'type' => 'expense',
                'section' => 'Dépenses',
                'title' => $expense->reference ?: 'Dépense sans référence',
                'description' => trim(($expense->contact?->display_name ?? 'Sans fournisseur').' · '.$expense->status.' · '.$expense->total_ttc.' DZD'),
                'href' => '/expenses/'.$expense->id,
            ]);
    }

    private function searchContacts(string $query)
    {
        return Contact::query()
            ->where(function ($q) use ($query) {
                $q->where('display_name', 'ilike', '%'.$query.'%')
                    ->orWhere('raison_sociale', 'ilike', '%'.$query.'%')
                    ->orWhere('nif', 'ilike', '%'.$query.'%')
                    ->orWhere('nis', 'ilike', '%'.$query.'%')
                    ->orWhere('rc', 'ilike', '%'.$query.'%')
                    ->orWhere('email', 'ilike', '%'.$query.'%')
                    ->orWhere('phone', 'ilike', '%'.$query.'%');
            })
            ->orderBy('display_name')
            ->limit(10)
            ->get(['id', 'type', 'display_name', 'raison_sociale', 'email', 'phone'])
            ->map(function (Contact $contact) {
                $href = in_array($contact->type, ['client', 'both'], true)
                    ? '/clients/'.$contact->id
                    : '/suppliers/'.$contact->id;

                return [
                    'type' => 'contact',
                    'section' => 'Contacts',
                    'title' => $contact->display_name ?: ($contact->raison_sociale ?: 'Contact'),
                    'description' => trim(($contact->type ?: 'contact').' · '.($contact->email ?: $contact->phone ?: '')),
                    'href' => $href,
                ];
            });
    }

    private function searchQuotes(string $query)
    {
        return Quote::query()
            ->with('contact:id,display_name,raison_sociale')
            ->where(function ($q) use ($query) {
                $q->where('number', 'ilike', '%'.$query.'%')
                    ->orWhere('status', 'ilike', '%'.$query.'%')
                    ->orWhere('reference', 'ilike', '%'.$query.'%')
                    ->orWhereHas('contact', function ($contact) use ($query) {
                        $contact->where('display_name', 'ilike', '%'.$query.'%')
                            ->orWhere('raison_sociale', 'ilike', '%'.$query.'%');
                    });
            })
            ->orderByDesc('created_at')
            ->limit(10)
            ->get(['id', 'number', 'status', 'total', 'contact_id'])
            ->map(fn (Quote $quote) => [
                'type' => 'quote',
                'section' => 'Devis',
                'title' => $quote->number ?: 'Devis sans numéro',
                'description' => trim(($quote->contact?->display_name ?? 'Sans client').' · '.$quote->status.' · '.$quote->total.' DZD'),
                'href' => '/quotes/'.$quote->id,
            ]);
    }

    private function searchDocuments(string $query)
    {
        return Document::query()
            ->where(function ($q) use ($query) {
                $q->where('file_name', 'ilike', '%'.$query.'%')
                    ->orWhere('document_type', 'ilike', '%'.$query.'%')
                    ->orWhere('ocr_status', 'ilike', '%'.$query.'%')
                    ->orWhere('ocr_raw_text', 'ilike', '%'.$query.'%');
            })
            ->orderByDesc('created_at')
            ->limit(10)
            ->get(['id', 'file_name', 'document_type', 'ocr_status'])
            ->map(fn (Document $document) => [
                'type' => 'document',
                'section' => 'Documents',
                'title' => $document->file_name,
                'description' => trim($document->document_type.' · OCR '.$document->ocr_status),
                'href' => '/documents/'.$document->id,
            ]);
    }
}
