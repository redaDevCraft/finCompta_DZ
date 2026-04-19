<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Company;
use App\Models\Contact;
use App\Models\FiscalPeriod;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Populates the demo company with a realistic 4-month accounting storyline:
 *  - capital injection (Jan)
 *  - immobilisation purchases
 *  - monthly purchases (merchandise, telecoms, rent, insurance)
 *  - salaries (accrual + payment)
 *  - sales invoices to 4 clients (some paid, some partial, some open)
 *  - bank receipts + supplier payments (all lettrable via reference)
 *  - cash sales and petty-cash expenses
 *
 * Every created journal entry gets source_type='demo_seed' so the seeder
 * can be re-run without duplicating data.
 */
class DemoDataSeeder extends Seeder
{
    private string $companyId = '';

    private array $accountMap = [];

    private array $journalMap = [];

    private array $periodMap = [];

    private ?int $postedBy = null;

    private Carbon $postedAt;

    public function __construct(?string $companyId = null)
    {
        if ($companyId) {
            $this->companyId = $companyId;
        }
    }

    public function run(?string $companyId = null): void
    {
        $companyId = $companyId ?: $this->companyId;

        if (! $companyId) {
            // Fallback: first company.
            $companyId = Company::query()->value('id');
        }

        if (! $companyId) {
            throw new InvalidArgumentException('Aucune société disponible pour DemoDataSeeder.');
        }

        $this->companyId = $companyId;
        $this->postedAt = now();

        $this->postedBy = DB::table('company_users')
            ->where('company_id', $companyId)
            ->whereNull('revoked_at')
            ->value('user_id');

        if (! $this->postedBy) {
            $this->postedBy = User::query()->value('id');
        }

        $this->command?->info('Nettoyage des données de démonstration existantes…');
        $this->purgeExistingDemoData();

        $this->command?->info('Chargement des comptes, journaux, périodes…');
        $this->loadReferenceData();

        $this->command?->info('Création des tiers (clients + fournisseurs)…');
        $contacts = $this->seedContacts();

        $this->command?->info('Création du scénario comptable (Jan→Avr 2026)…');
        $this->seedStoryline($contacts);

        $this->command?->info('Terminé : '
            .JournalEntry::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('source_type', 'demo_seed')
                ->count()
            .' écritures démo créées.');
    }

    // ──────────────────────────────────────────────────────────────────

    private function purgeExistingDemoData(): void
    {
        $ids = JournalEntry::withoutGlobalScopes()
            ->where('company_id', $this->companyId)
            ->where('source_type', 'demo_seed')
            ->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        // Break lettering links first (some lines may be lettered).
        JournalLine::query()
            ->whereIn('journal_entry_id', $ids)
            ->update(['lettering_id' => null]);

        DB::table('letterings')
            ->where('company_id', $this->companyId)
            ->delete();

        JournalLine::query()->whereIn('journal_entry_id', $ids)->delete();
        JournalEntry::withoutGlobalScopes()->whereIn('id', $ids)->delete();
    }

    private function loadReferenceData(): void
    {
        $this->accountMap = Account::withoutGlobalScopes()
            ->where('company_id', $this->companyId)
            ->get()
            ->keyBy('code')
            ->map(fn ($a) => $a->id)
            ->all();

        $this->journalMap = Journal::withoutGlobalScopes()
            ->where('company_id', $this->companyId)
            ->get()
            ->keyBy('code')
            ->map(fn ($j) => $j->id)
            ->all();

        $this->periodMap = FiscalPeriod::query()
            ->where('company_id', $this->companyId)
            ->get()
            ->keyBy(fn ($p) => sprintf('%04d-%02d', $p->year, $p->month))
            ->map(fn ($p) => $p->id)
            ->all();

        foreach (['101', '215', '401', '411', '4451', '4456', '512', '531', '601', '606', '613', '616', '626', '641', '421', '701', '706'] as $code) {
            if (! isset($this->accountMap[$code])) {
                // Not critical — just log, the storyline uses codes that exist.
                $this->command?->warn("  · Compte {$code} absent de la CoA, certaines écritures peuvent échouer.");
            }
        }
    }

    private function seedContacts(): array
    {
        $clients = [
            ['display_name' => 'ATM Mobilis SPA',        'rc' => '16B0987654', 'nif' => '099916000987654', 'wilaya' => 'Alger'],
            ['display_name' => 'SARL BatiConstruct',     'rc' => '31B0012345', 'nif' => '099931000012345', 'wilaya' => 'Oran'],
            ['display_name' => 'EURL Dar El Beida',      'rc' => '16B0076543', 'nif' => '099916000076543', 'wilaya' => 'Alger'],
            ['display_name' => 'Sonelgaz DG',            'rc' => '16B0000007', 'nif' => '099916000000007', 'wilaya' => 'Alger'],
        ];

        $suppliers = [
            ['display_name' => 'TechSolutions Algérie',  'rc' => '31B0055555', 'nif' => '099931000055555', 'wilaya' => 'Oran'],
            ['display_name' => 'SARL FournitureNet',     'rc' => '16B0044444', 'nif' => '099916000044444', 'wilaya' => 'Alger'],
            ['display_name' => 'M. Propriétaire (bail)', 'rc' => null,         'nif' => null,              'wilaya' => 'Oran'],
            ['display_name' => 'OrascomTel Pro',         'rc' => '16B0022222', 'nif' => '099916000022222', 'wilaya' => 'Alger'],
            ['display_name' => 'SupplyPro Industrie',    'rc' => '09B0011111', 'nif' => '099909000011111', 'wilaya' => 'Blida'],
            ['display_name' => 'AssuDZ Assurances',      'rc' => '16B0099999', 'nif' => '099916000099999', 'wilaya' => 'Alger'],
        ];

        $map = [];

        foreach ($clients as $c) {
            $contact = Contact::withoutGlobalScopes()->updateOrCreate(
                [
                    'company_id' => $this->companyId,
                    'display_name' => $c['display_name'],
                ],
                [
                    'type' => 'client',
                    'entity_type' => 'enterprise',
                    'raison_sociale' => $c['display_name'],
                    'rc' => $c['rc'],
                    'nif' => $c['nif'],
                    'address_wilaya' => $c['wilaya'],
                    'is_active' => true,
                ]
            );
            $map['client:'.$c['display_name']] = $contact->id;
        }

        foreach ($suppliers as $s) {
            $contact = Contact::withoutGlobalScopes()->updateOrCreate(
                [
                    'company_id' => $this->companyId,
                    'display_name' => $s['display_name'],
                ],
                [
                    'type' => 'supplier',
                    'entity_type' => $s['rc'] ? 'enterprise' : 'individual',
                    'raison_sociale' => $s['display_name'],
                    'rc' => $s['rc'],
                    'nif' => $s['nif'],
                    'address_wilaya' => $s['wilaya'],
                    'is_active' => true,
                ]
            );
            $map['supplier:'.$s['display_name']] = $contact->id;
        }

        return $map;
    }

    // ──────────────────────────────────────────────────────────────────
    // Storyline

    private function seedStoryline(array $contacts): void
    {
        $year = (int) now()->year;

        $mobilis = $contacts['client:ATM Mobilis SPA'];
        $bati = $contacts['client:SARL BatiConstruct'];
        $dar = $contacts['client:EURL Dar El Beida'];
        $sonelgaz = $contacts['client:Sonelgaz DG'];

        $tech = $contacts['supplier:TechSolutions Algérie'];
        $furniture = $contacts['supplier:SARL FournitureNet'];
        $landlord = $contacts['supplier:M. Propriétaire (bail)'];
        $orascom = $contacts['supplier:OrascomTel Pro'];
        $supplypro = $contacts['supplier:SupplyPro Industrie'];
        $assurdz = $contacts['supplier:AssuDZ Assurances'];

        // ── January: opening + immos ────────────────────────────────────
        $this->entry("{$year}-01-05", 'OD', 'APPORT-001', 'Apport initial en capital', [
            ['debit', '512', 2_000_000, null, 'Versement initial à la banque'],
            ['credit', '101', 2_000_000, null, 'Capital souscrit et libéré'],
        ]);

        $this->purchaseInvoice(
            date: "{$year}-01-15",
            reference: 'FA-TECH-2601',
            supplierId: $tech,
            supplierLabel: 'TechSolutions',
            description: 'Achat poste informatique + écran',
            chargeAccount: '215',
            htAmount: 150_000,
            vatPct: 19
        );

        $this->purchaseInvoice(
            date: "{$year}-01-18",
            reference: 'FA-FURN-118',
            supplierId: $furniture,
            supplierLabel: 'FournitureNet',
            description: 'Fournitures de bureau',
            chargeAccount: '606',
            htAmount: 22_000,
            vatPct: 19
        );

        $this->salaryRun("{$year}-01-31", 120_000, 'Janvier');

        // ── February: activité commerciale ──────────────────────────────
        $this->salesInvoice(
            date: "{$year}-02-03",
            reference: 'FAC-2601-001',
            clientId: $mobilis,
            clientLabel: 'ATM Mobilis',
            description: 'Prestations de maintenance janvier',
            revenueAccount: '706',
            htAmount: 200_000,
            vatPct: 19
        );

        $this->salesInvoice(
            date: "{$year}-02-10",
            reference: 'FAC-2601-002',
            clientId: $bati,
            clientLabel: 'BatiConstruct',
            description: 'Vente de matériel et installation',
            revenueAccount: '701',
            htAmount: 350_000,
            vatPct: 19
        );

        $this->purchaseInvoice(
            date: "{$year}-02-05",
            reference: 'BAIL-02',
            supplierId: $landlord,
            supplierLabel: 'Propriétaire',
            description: 'Loyer bureau février',
            chargeAccount: '613',
            htAmount: 50_000,
            vatPct: 19
        );

        $this->purchaseInvoice(
            date: "{$year}-02-12",
            reference: 'TEL-FEB',
            supplierId: $orascom,
            supplierLabel: 'OrascomTel',
            description: 'Téléphonie + internet février',
            chargeAccount: '626',
            htAmount: 8_000,
            vatPct: 19
        );

        // Client Mobilis pays → matching reference makes auto-lettrage work
        $this->bankReceipt(
            date: "{$year}-02-20",
            reference: 'FAC-2601-001',
            clientId: $mobilis,
            clientLabel: 'ATM Mobilis',
            amount: 238_000,
            description: 'Règlement FAC-2601-001 par virement'
        );

        $this->salaryRun("{$year}-02-28", 120_000, 'Février');

        // ── March: expansion + partial payments ─────────────────────────
        $this->purchaseInvoice(
            date: "{$year}-03-01",
            reference: 'SUP-MAR-301',
            supplierId: $supplypro,
            supplierLabel: 'SupplyPro',
            description: 'Achat marchandises pour revente',
            chargeAccount: '601',
            htAmount: 300_000,
            vatPct: 19
        );

        $this->salesInvoice(
            date: "{$year}-03-05",
            reference: 'FAC-2603-003',
            clientId: $dar,
            clientLabel: 'Dar El Beida',
            description: 'Livraison marchandises — lot 1',
            revenueAccount: '701',
            htAmount: 500_000,
            vatPct: 19
        );

        $this->supplierPayment(
            date: "{$year}-03-12",
            reference: 'FA-TECH-2601',
            supplierId: $tech,
            supplierLabel: 'TechSolutions',
            amount: 178_500,
            description: 'Règlement facture matériel informatique'
        );

        $this->cashSale(
            date: "{$year}-03-15",
            reference: 'CPT-2603-01',
            description: 'Vente comptoir — caisse',
            htAmount: 30_000,
            vatPct: 19
        );

        // Partial payment on FAC-2601-002 — client still owes 216,500 → appears in aged + open lettrage
        $this->bankReceipt(
            date: "{$year}-03-20",
            reference: 'FAC-2601-002-ACOMPTE',
            clientId: $bati,
            clientLabel: 'BatiConstruct',
            amount: 200_000,
            description: 'Acompte sur FAC-2601-002'
        );

        $this->supplierPayment(
            date: "{$year}-03-22",
            reference: 'BAIL-02',
            supplierId: $landlord,
            supplierLabel: 'Propriétaire',
            amount: 59_500,
            description: 'Règlement loyer février'
        );

        $this->salaryRun("{$year}-03-31", 130_000, 'Mars (augmentation)');

        // ── April: quelques impayés (aged > 0) ──────────────────────────
        $this->salesInvoice(
            date: "{$year}-04-02",
            reference: 'FAC-2604-004',
            clientId: $sonelgaz,
            clientLabel: 'Sonelgaz',
            description: 'Audit électrique bureaux',
            revenueAccount: '706',
            htAmount: 150_000,
            vatPct: 19
        );

        $this->purchaseInvoice(
            date: "{$year}-04-10",
            reference: 'ASSUR-2604',
            supplierId: $assurdz,
            supplierLabel: 'AssuDZ',
            description: 'Assurance bureau + flotte',
            chargeAccount: '616',
            htAmount: 12_000,
            vatPct: 0 // exonéré
        );

        $this->salesInvoice(
            date: "{$year}-04-15",
            reference: 'FAC-2604-005',
            clientId: $mobilis,
            clientLabel: 'ATM Mobilis',
            description: 'Prestations avril',
            revenueAccount: '706',
            htAmount: 80_000,
            vatPct: 19
        );

        $this->cashExpense(
            date: "{$year}-04-18",
            reference: 'CSH-2604-01',
            description: 'Fournitures de bureau (caisse)',
            amount: 4_500,
            chargeAccount: '606'
        );

        // April rent — still unpaid → aged 0-30
        $this->purchaseInvoice(
            date: "{$year}-04-20",
            reference: 'BAIL-04',
            supplierId: $landlord,
            supplierLabel: 'Propriétaire',
            description: 'Loyer bureau avril',
            chargeAccount: '613',
            htAmount: 50_000,
            vatPct: 19
        );
    }

    // ──────────────────────────────────────────────────────────────────
    // Entry helpers

    private function salesInvoice(
        string $date,
        string $reference,
        string $clientId,
        string $clientLabel,
        string $description,
        string $revenueAccount,
        float $htAmount,
        float $vatPct
    ): void {
        $vat = round($htAmount * $vatPct / 100, 2);
        $ttc = $htAmount + $vat;

        $lines = [
            ['debit',  '411', $ttc,      $clientId, "Client {$clientLabel} — {$reference}"],
            ['credit', $revenueAccount, $htAmount, null, $description],
        ];

        if ($vat > 0) {
            $lines[] = ['credit', '4451', $vat, null, "TVA {$vatPct}% — {$reference}"];
        }

        $this->entry($date, 'VT', $reference, $description, $lines);
    }

    private function purchaseInvoice(
        string $date,
        string $reference,
        string $supplierId,
        string $supplierLabel,
        string $description,
        string $chargeAccount,
        float $htAmount,
        float $vatPct
    ): void {
        $vat = round($htAmount * $vatPct / 100, 2);
        $ttc = $htAmount + $vat;

        $lines = [
            ['debit',  $chargeAccount, $htAmount, null, $description],
        ];

        if ($vat > 0) {
            $lines[] = ['debit', '4456', $vat, null, "TVA déductible — {$reference}"];
        }

        $lines[] = ['credit', '401', $ttc, $supplierId, "Fournisseur {$supplierLabel} — {$reference}"];

        $this->entry($date, 'AC', $reference, $description, $lines);
    }

    private function bankReceipt(
        string $date,
        string $reference,
        string $clientId,
        string $clientLabel,
        float $amount,
        string $description
    ): void {
        $this->entry($date, 'BQ', $reference, $description, [
            ['debit',  '512', $amount, null,       "Virement reçu — {$clientLabel}"],
            ['credit', '411', $amount, $clientId,  $description],
        ]);
    }

    private function supplierPayment(
        string $date,
        string $reference,
        string $supplierId,
        string $supplierLabel,
        float $amount,
        string $description
    ): void {
        $this->entry($date, 'BQ', $reference, $description, [
            ['debit',  '401', $amount, $supplierId, "Règlement {$supplierLabel}"],
            ['credit', '512', $amount, null,        $description],
        ]);
    }

    private function salaryRun(string $date, float $gross, string $monthLabel): void
    {
        $this->entry($date, 'OD', "SAL-{$monthLabel}", "Salaires {$monthLabel}", [
            ['debit',  '641', $gross, null, "Charge salariale {$monthLabel}"],
            ['credit', '421', $gross, null, "Dû au personnel — {$monthLabel}"],
        ]);

        $payDate = Carbon::parse($date)->addDays(2)->toDateString();

        $this->entry($payDate, 'BQ', "SAL-{$monthLabel}", "Règlement salaires {$monthLabel}", [
            ['debit',  '421', $gross, null, "Paiement salaires {$monthLabel}"],
            ['credit', '512', $gross, null, "Virement salaires {$monthLabel}"],
        ]);
    }

    private function cashSale(
        string $date,
        string $reference,
        string $description,
        float $htAmount,
        float $vatPct
    ): void {
        $vat = round($htAmount * $vatPct / 100, 2);
        $ttc = $htAmount + $vat;

        $lines = [
            ['debit',  '531', $ttc,      null, "Encaissement caisse — {$reference}"],
            ['credit', '706', $htAmount, null, $description],
        ];

        if ($vat > 0) {
            $lines[] = ['credit', '4451', $vat, null, "TVA collectée — {$reference}"];
        }

        $this->entry($date, 'CA', $reference, $description, $lines);
    }

    private function cashExpense(
        string $date,
        string $reference,
        string $description,
        float $amount,
        string $chargeAccount
    ): void {
        $this->entry($date, 'CA', $reference, $description, [
            ['debit',  $chargeAccount, $amount, null, $description],
            ['credit', '531',          $amount, null, "Décaissement caisse — {$reference}"],
        ]);
    }

    private function entry(
        string $date,
        string $journalCode,
        string $reference,
        string $description,
        array $lines
    ): void {
        $periodKey = Carbon::parse($date)->format('Y-m');
        $periodId = $this->periodMap[$periodKey] ?? null;

        if (! $periodId) {
            $this->command?->warn("  · Période {$periodKey} absente, écriture {$reference} ignorée.");

            return;
        }

        $journalId = $this->journalMap[$journalCode] ?? null;

        $totalDebit = 0.0;
        $totalCredit = 0.0;
        foreach ($lines as [$side, , $amount]) {
            if ($side === 'debit') {
                $totalDebit += $amount;
            } else {
                $totalCredit += $amount;
            }
        }

        if (abs($totalDebit - $totalCredit) > 0.01) {
            $this->command?->warn(sprintf(
                '  · Écriture %s non équilibrée (D %.2f ≠ C %.2f) — ignorée.',
                $reference,
                $totalDebit,
                $totalCredit
            ));

            return;
        }

        $entry = new JournalEntry;
        $entry->id = (string) Str::uuid();
        $entry->company_id = $this->companyId;
        $entry->period_id = $periodId;
        $entry->journal_id = $journalId;
        $entry->entry_date = $date;
        $entry->journal_code = $journalCode;
        $entry->reference = $reference;
        $entry->description = $description;
        $entry->status = 'posted';
        $entry->source_type = 'demo_seed';
        $entry->posted_at = $this->postedAt;
        $entry->posted_by = $this->postedBy;
        $entry->save();

        foreach ($lines as $i => [$side, $code, $amount, $contactId, $lineDesc]) {
            $accountId = $this->accountMap[$code] ?? null;

            if (! $accountId) {
                $this->command?->warn("  · Compte {$code} introuvable, ligne ignorée (entrée {$reference}).");

                continue;
            }

            JournalLine::query()->create([
                'id' => (string) Str::uuid(),
                'journal_entry_id' => $entry->id,
                'account_id' => $accountId,
                'debit' => $side === 'debit' ? $amount : 0,
                'credit' => $side === 'credit' ? $amount : 0,
                'contact_id' => $contactId,
                'description' => $lineDesc,
                'sort_order' => $i,
            ]);
        }
    }
}
