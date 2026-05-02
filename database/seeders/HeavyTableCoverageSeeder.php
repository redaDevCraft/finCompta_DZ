<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\AiSuggestion;
use App\Models\AnalyticAxis;
use App\Models\AnalyticSection;
use App\Models\AutoCounterpartRule;
use App\Models\BankAccount;
use App\Models\BankStatementImport;
use App\Models\BankTransaction;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Currency;
use App\Models\Document;
use App\Models\ExchangeRate;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\JournalEntryLock;
use App\Models\JournalUserPermission;
use App\Models\ManagementPrediction;
use App\Models\Payment;
use App\Models\PaymentWebhookLog;
use App\Models\Plan;
use App\Models\RefundRequest;
use App\Models\ReportRun;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Faker\Factory as Faker;


/**
 * Fills tables not covered by {@see HeavyTestingSeeder} so local QA can hit
 * bank feeds, multi-currency, analytics, billing admin, exports, OCR queue, etc.
 * Runs only when {@see DatabaseSeeder} sets HEAVY_SEED=true.
 */
class HeavyTableCoverageSeeder extends Seeder
{
    public function run(): void
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, Company> $companies */
        $companies = Company::query()->get();
        if ($companies->isEmpty()) {
            $this->command?->warn('HeavyTableCoverageSeeder: no companies, skip.');

            return;
        }

        $plan = Plan::query()->where('is_active', true)->orderBy('sort_order')->first();

        foreach ($companies as $company) {
            assert($company instanceof Company);
            $this->seedCompany($company, $plan);
        }

        $this->command?->info('HeavyTableCoverageSeeder: done for '.$companies->count().' company(ies).');
    }

    private function seedCompany(Company $company, ?Plan $plan): void
    {
        $userId = (int) (DB::table('company_users')
            ->where('company_id', $company->id)
            ->whereNull('revoked_at')
            ->value('user_id') ?? User::query()->value('id'));
        if (! $userId) {
            $this->command?->warn("HeavyTableCoverage: no user for company {$company->id}, skip company.");

            return;
        }

        $accountsByCode = Account::query()
            ->where('company_id', $company->id)
            ->get()
            ->keyBy('code');

        $gl512 = $accountsByCode->get('512')?->id;
        $gl411 = $accountsByCode->get('411')?->id;
        $gl401 = $accountsByCode->get('401')?->id;
        $gl701 = $accountsByCode->get('701')?->id;

        DB::transaction(function () use (
            $company,
            $plan,
            $userId,
            $gl512,
            $gl411,
            $gl701,
            $accountsByCode
        ) {
            Company::query()->whereKey($company->id)->update(['management_predictions_enabled' => true]);

            $this->seedAnalytic($company, $gl701);
            $this->seedCurrenciesAndRates($company);
            if ($gl512) {
                $this->seedBankData($company, $userId, $gl512);
            }
            $this->seedDocumentsAndAi($company, $userId);
            $this->seedReportRuns($company, $userId);
            $this->seedManagementPredictions($company, $accountsByCode);
            if ($gl512 && $gl411) {
                $this->seedAutoCounterpartRules($company, $gl512, $gl411);
            }
            $this->seedJournalUserPermissions($company, $userId);
            $this->seedJournalEntryLocks($company, $userId);
            $this->seedBillingData($company, $plan, $userId);
        });
    }

    private function seedAnalytic(Company $company, ?string $account701Id): void
    {
        $axisDefs = [
            ['code' => 'HVY-PRJ', 'name' => 'Projets lourds', 'sections' => [
                ['code' => 'P-ALPHA', 'name' => 'Projet Alpha'],
                ['code' => 'P-BETA', 'name' => 'Projet Beta'],
                ['code' => 'P-MAINT', 'name' => 'Maintenance'],
            ]],
            ['code' => 'HVY-CTR', 'name' => 'Centres de coûts', 'sections' => [
                ['code' => 'C-ADM', 'name' => 'Administration'],
                ['code' => 'C-OPS', 'name' => 'Opérations'],
                ['code' => 'C-IT', 'name' => 'IT / Digital'],
            ]],
            ['code' => 'HVY-ACT', 'name' => "Lignes d'activité", 'sections' => [
                ['code' => 'A-VENTE', 'name' => 'Vente B2B'],
                ['code' => 'A-SERV', 'name' => 'Prestations'],
            ]],
        ];

        foreach ($axisDefs as $axisDef) {
            $axis = AnalyticAxis::query()->updateOrCreate(
                ['company_id' => $company->id, 'code' => $axisDef['code']],
                ['name' => $axisDef['name'], 'is_active' => true]
            );
            foreach ($axisDef['sections'] as $sec) {
                AnalyticSection::query()->updateOrCreate(
                    [
                        'company_id' => $company->id,
                        'analytic_axis_id' => $axis->id,
                        'code' => $sec['code'],
                    ],
                    ['name' => $sec['name'], 'is_active' => true]
                );
            }
        }

        if ($account701Id) {
            $sectionId = AnalyticSection::query()
                ->where('company_id', $company->id)
                ->where('code', 'A-VENTE')
                ->value('id');
            if ($sectionId) {
                Account::query()
                    ->where('company_id', $company->id)
                    ->where('id', $account701Id)
                    ->update(['default_analytic_section_id' => $sectionId]);
            }
        }
    }

    private function seedCurrenciesAndRates(Company $company): void
    {
        Currency::query()->updateOrCreate(
            [
                'company_id' => $company->id,
                'code' => 'DZD',
            ],
            [
                'name' => 'Dinar algérien',
                'decimals' => 2,
                'is_base' => true,
                'is_active' => true,
            ]
        );

        $eur = Currency::query()->updateOrCreate(
            [
                'company_id' => $company->id,
                'code' => 'EUR',
            ],
            [
                'name' => 'Euro',
                'decimals' => 2,
                'is_base' => false,
                'is_active' => true,
            ]
        );
        $usd = Currency::query()->updateOrCreate(
            [
                'company_id' => $company->id,
                'code' => 'USD',
            ],
            [
                'name' => 'Dollar US',
                'decimals' => 2,
                'is_base' => false,
                'is_active' => true,
            ]
        );

        $days = $this->intEnv('HEAVY_SEED_FX_DAYS', 45);
        $faker = Faker::create('fr_FR');
        for ($d = 0; $d < $days; $d++) {
            $date = Carbon::now()->subDays($d)->toDateString();
            ExchangeRate::query()->updateOrCreate(
                [
                    'company_id' => $company->id,
                    'currency_id' => $eur->id,
                    'rate_date' => $date,
                ],
                ['rate' => $faker->randomFloat(8, 140, 150)]
            );
            ExchangeRate::query()->updateOrCreate(
                [
                    'company_id' => $company->id,
                    'currency_id' => $usd->id,
                    'rate_date' => $date,
                ],
                ['rate' => $faker->randomFloat(8, 128, 138)]
            );
        }
    }

    private function seedBankData(Company $company, int $userId, string $gl512Id): void
    {
        $bank = BankAccount::query()->firstOrCreate(
            [
                'company_id' => $company->id,
                'account_number' => 'HVY-512-001',
            ],
            [
                'bank_name' => 'Banque de test lourd',
                'currency' => $company->currency ?: 'DZD',
                'gl_account_id' => $gl512Id,
                'is_active' => true,
            ]
        );

        $nTx = $this->intEnv('HEAVY_SEED_BANK_TXNS', 200);
        $nImports = $this->intEnv('HEAVY_SEED_BANK_IMPORTS', 3);

        for ($b = 0; $b < $nImports; $b++) {
            $end = Carbon::now()->subDays(30 * $b)->toDateString();
            $start = Carbon::now()->subDays(30 * $b + 28)->toDateString();
            $import = BankStatementImport::query()->create([
                'id' => (string) Str::uuid(),
                'company_id' => $company->id,
                'bank_account_id' => $bank->id,
                'period_start' => $start,
                'period_end' => $end,
                'import_type' => $faker->randomElement(['pdf_ocr', 'csv', 'excel', 'manual']),
                'source_document_path' => "heavy/bank-{$b}.csv",
                'opening_balance' => $faker->randomFloat(2, 100_000, 5_000_000),
                'closing_balance' => $faker->randomFloat(2, 100_000, 5_000_000),
                'row_count' => 0,
                'imported_by' => $userId,
            ]);

            for ($i = 0; $i < (int) ceil($nTx / $nImports); $i++) {
                $dir = $faker->randomElement(['debit', 'credit']);
                $amount = $faker->randomFloat(2, 500, 250_000);
                $status = $faker->randomElement(['unmatched', 'unmatched', 'unmatched', 'matched', 'manually_posted', 'excluded']);
                $label = 'HVY-RIB '.strtoupper(Str::random(6)).' '.$faker->words(3, true);
                if ($b === 0 && $i < 8) {
                    $status = 'unmatched';
                }
                $import->increment('row_count');
                BankTransaction::query()->create([
                    'id' => (string) Str::uuid(),
                    'company_id' => $company->id,
                    'bank_account_id' => $bank->id,
                    'import_id' => $import->id,
                    'transaction_date' => Carbon::parse($start)->addDays(random_int(0, 25))->toDateString(),
                    'value_date' => null,
                    'label' => $label,
                    'amount' => $amount,
                    'direction' => $dir,
                    'balance_after' => $faker->optional(0.4)->randomFloat(2, 0, 8_000_000),
                    'reconcile_status' => $status,
                    'journal_entry_id' => null,
                    'matched_by' => $status === 'matched' ? $userId : null,
                    'matched_at' => $status === 'matched' ? now() : null,
                ]);
            }
        }
    }

    private function seedDocumentsAndAi(Company $company, int $userId): void
    {
        $n = $this->intEnv('HEAVY_SEED_DOCUMENTS', 60);
        $statuses = ['pending', 'processing', 'done', 'done', 'done', 'failed'];
        for ($i = 0; $i < $n; $i++) {
            $id = (string) Str::uuid();
            $ocr = $faker->randomElement($statuses);
            $doc = Document::query()->create([
                'id' => $id,
                'company_id' => $company->id,
                'file_name' => "facture-{$i}.pdf",
                'mime_type' => 'application/pdf',
                'file_size_bytes' => random_int(8_000, 900_000),
                'storage_key' => "heavy-seed/{$company->id}/{$id}.pdf",
                'document_type' => $faker->randomElement(['invoice_pdf', 'supplier_bill', 'bank_statement']),
                'source' => 'upload',
                'ocr_status' => $ocr,
                'ocr_raw_text' => $ocr === 'done' ? "FACTURE FOURNISSEUR\nTTC ".$faker->randomFloat(2, 100, 50_000) : null,
                'ocr_parsed_hints' => $ocr === 'done' ? ['total_ttc' => (string) $faker->randomFloat(2, 100, 50_000)] : null,
                'ocr_error' => $ocr === 'failed' ? 'OCR simulé: page illisible' : null,
                'retention_until' => $ocr === 'done' ? Carbon::now()->addYears(10) : null,
                'uploaded_by' => $userId,
            ]);
            if ($faker->boolean(40)) {
                AiSuggestion::query()->create([
                    'id' => (string) Str::uuid(),
                    'company_id' => $company->id,
                    'user_id' => $userId,
                    'source_type' => 'document',
                    'source_id' => $doc->id,
                    'field_name' => $faker->randomElement(['vendor_name', 'total_ht', 'total_ttc', 'nif', 'date']),
                    'suggested_value' => $faker->company(),
                    'confidence' => $faker->randomFloat(3, 0.4, 0.99),
                    'accepted' => $faker->optional(0.3)->boolean(),
                    'final_value' => null,
                ]);
            }
        }
    }

    private function seedReportRuns(Company $company, int $userId): void
    {
        $types = [ReportRun::TYPE_BILAN_PDF, ReportRun::TYPE_VAT_XLSX, ReportRun::TYPE_ANALYTIC_TRIAL_BALANCE_XLSX];
        $n = $this->intEnv('HEAVY_SEED_REPORT_RUNS', 25);
        for ($i = 0; $i < $n; $i++) {
            $status = $faker->randomElement([
                ReportRun::STATUS_QUEUED,
                ReportRun::STATUS_RUNNING,
                ReportRun::STATUS_READY,
                ReportRun::STATUS_FAILED,
                ReportRun::STATUS_READY,
            ]);
            $attrs = [
                'id' => (string) Str::uuid(),
                'company_id' => $company->id,
                'user_id' => $userId,
                'type' => $faker->randomElement($types),
                'params' => ['from' => '2026-01-01', 'to' => '2026-12-31', 'i' => $i],
                'status' => $status,
            ];
            if ($status === ReportRun::STATUS_READY) {
                $attrs['storage_disk'] = 'local';
                $attrs['storage_path'] = "reports/{$company->id}/run-".Str::lower(Str::random(12)).'.pdf';
                $attrs['original_filename'] = 'export-'.$i.'.pdf';
                $attrs['artifact_bytes'] = random_int(4_000, 800_000);
                $attrs['completed_at'] = now()->subHours(random_int(1, 200));
                $attrs['started_at'] = now()->subHours(random_int(2, 201));
            } elseif ($status === ReportRun::STATUS_FAILED) {
                $attrs['error_message'] = 'Demande lourde interrompue (seed)';
                $attrs['completed_at'] = now();
                $attrs['started_at'] = now()->subMinute();
            } elseif ($status === ReportRun::STATUS_RUNNING) {
                $attrs['started_at'] = now()->subSeconds(30);
            }
            ReportRun::query()->create($attrs);
        }
    }

    private function seedManagementPredictions(Company $company, $accountsByCode): void
    {
        $sectionId = AnalyticSection::query()
            ->where('company_id', $company->id)
            ->where('code', 'A-VENTE')
            ->value('id');
        $account701 = $accountsByCode->get('701')?->id;
        $contact = Contact::query()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->inRandomOrder()
            ->first();
        $n = $this->intEnv('HEAVY_SEED_MGMT_PREDS', 30);
        for ($i = 0; $i < $n; $i++) {
            $start = Carbon::now()->subMonth()->startOfMonth();
            $end = Carbon::now()->endOfMonth();
            ManagementPrediction::query()->create([
                'company_id' => $company->id,
                'account_id' => $account701,
                'contact_id' => $contact?->id,
                'analytic_section_id' => $sectionId,
                'period_type' => 'month',
                'period_start_date' => $start->toDateString(),
                'period_end_date' => $end->toDateString(),
                'amount' => $faker->randomFloat(2, 10_000, 500_000),
                'comment' => 'Objectif lourd (seed) '.$i,
            ]);
        }
    }

    private function seedAutoCounterpartRules(Company $company, string $gl512, string $gl411): void
    {
        $exists = AutoCounterpartRule::query()
            ->where('company_id', $company->id)
            ->where('name', 'HVY — Encaissement bancaire')
            ->exists();
        if (! $exists) {
            AutoCounterpartRule::query()->create([
                'company_id' => $company->id,
                'name' => 'HVY — Encaissement bancaire',
                'trigger_account_id' => $gl512,
                'trigger_direction' => 'debit',
                'counterpart_account_id' => $gl411,
                'counterpart_direction' => 'credit',
                'priority' => 10,
                'is_active' => true,
            ]);
        }
    }

    private function seedJournalUserPermissions(Company $company, int $userId): void
    {
        $journals = Journal::query()->where('company_id', $company->id)->limit(4)->pluck('id');
        foreach ($journals as $journalId) {
            JournalUserPermission::query()->updateOrCreate(
                [
                    'journal_id' => $journalId,
                    'user_id' => $userId,
                ],
                [
                    'can_view' => true,
                    'can_post' => $faker->boolean(70),
                ]
            );
        }
    }

    private function seedJournalEntryLocks(Company $company, int $userId): void
    {
        $until = Carbon::now()->subMonthNoOverflow()->endOfMonth()->toDateString();
        JournalEntryLock::query()->firstOrCreate(
            [
                'company_id' => $company->id,
                'lock_type' => 'date',
                'locked_until_date' => $until,
            ],
            [
                'journal_entry_id' => null,
                'locked_by_user_id' => $userId,
            ]
        );

        $entry = JournalEntry::query()
            ->where('company_id', $company->id)
            ->orderBy('entry_date')
            ->first();
        if ($entry) {
            JournalEntryLock::query()->firstOrCreate(
                [
                    'company_id' => $company->id,
                    'lock_type' => 'entry',
                    'journal_entry_id' => $entry->id,
                ],
                [
                    'locked_until_date' => null,
                    'locked_by_user_id' => $userId,
                ]
            );
        }
    }

    private function seedBillingData(Company $company, ?Plan $plan, int $userId): void
    {
        if (! $plan) {
            return;
        }

        $sub = Subscription::query()->where('company_id', $company->id)->latest()->first();
        if (! $sub) {
            $sub = Subscription::query()->create([
                'company_id' => $company->id,
                'plan_id' => $plan->id,
                'status' => 'active',
                'billing_cycle' => 'monthly',
                'current_period_started_at' => now()->subDays(5),
                'current_period_ends_at' => now()->addMonth(),
                'last_payment_method' => 'cib',
            ]);
        }

        $price = $plan->priceForCycle('monthly') ?: 2490;
        $gateways = ['chargily', 'bon_de_commande', 'manual'];
        $nPay = $this->intEnv('HEAVY_SEED_PAYMENTS', 12);
        $createdPayments = [];
        for ($i = 0; $i < $nPay; $i++) {
            $g = $gateways[array_rand($gateways)];
            $status = $faker->randomElement(['pending', 'processing', 'paid', 'failed', 'canceled', 'refunded', 'expired', 'paid', 'pending']);
            $ref = 'HVY-PMT-'.strtoupper((string) Str::ulid());
            $approval = 'none';
            if ($g === 'bon_de_commande') {
                $approval = $faker->randomElement(['none', 'proof_missing', 'proof_uploaded', 'approved', 'rejected', 'awaiting_second_approval']);
            }
            $paidAt = in_array($status, ['refunded', 'paid'], true) ? now()->subDays(random_int(1, 40)) : null;
            $payment = Payment::query()->create([
                'company_id' => $company->id,
                'subscription_id' => $sub->id,
                'plan_id' => $plan->id,
                'reference' => $ref,
                'gateway' => $g,
                'method' => $faker->boolean(80) ? $faker->randomElement(['edahabia', 'cib', 'bank_transfer']) : null,
                'billing_cycle' => 'monthly',
                'amount_dzd' => $price,
                'currency' => 'DZD',
                'status' => $status,
                'approval_status' => $approval,
                'meta' => ['source' => 'heavy_seed', 'i' => $i],
                'paid_at' => $paidAt,
            ]);
            if ($status === 'paid' || $status === 'refunded') {
                $createdPayments[] = $payment->id;
            }
        }

        foreach (array_slice($createdPayments, 0, 3) as $idx => $pid) {
            $ev = 'charge.'.$this->ulid12();
            PaymentWebhookLog::query()->create([
                'id' => (string) Str::uuid(),
                'gateway' => 'chargily',
                'event_id' => 'evt_'.$ev,
                'event_name' => 'charge.paid',
                'signature_header' => 'hmac=seed',
                'payment_id' => $pid,
                'signature_valid' => true,
                'is_duplicate' => false,
                'payload' => ['reference' => $ev, 'amount' => $price],
                'received_at' => now()->subHours($idx + 1),
            ]);
        }

        if ($createdPayments !== []) {
            $p = $createdPayments[0];
            RefundRequest::query()->create([
                'company_id' => $company->id,
                'payment_id' => $p,
                'requested_by' => $userId,
                'status' => 'submitted',
                'reason' => 'Test remboursement (seed lourd) — vérifier workflow admin',
            ]);
        }
    }

    private function ulid12(): string
    {
        return strtolower(Str::random(12));
    }

    private function intEnv(string $key, int $default): int
    {
        $value = env($key, $default);

        return max(0, (int) $value);
    }
}
