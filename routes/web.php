<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\CompanyController as AdminCompanyController;
use App\Http\Controllers\Admin\PaymentConfirmationController;
use App\Http\Controllers\Admin\PlanFeatureController as AdminPlanFeatureController;
use App\Http\Controllers\Admin\PlanController as AdminPlanController;
use App\Http\Controllers\Admin\RefundRequestAdminController;
use App\Http\Controllers\Admin\SubscriptionController as AdminSubscriptionController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Auth\GoogleOAuthController;
use App\Http\Controllers\BankController;
use App\Http\Controllers\AutoCounterpartRuleController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\SuggestController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\GlobalSearchController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\InvoicePaymentController;
use App\Http\Controllers\JournalEntryController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\LedgerController;
use App\Http\Controllers\LetteringController;
use App\Http\Controllers\ManagementPredictionController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\ReconciliationController;
use App\Http\Controllers\RefundRequestController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReportRunController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SupplierController;
use FontLib\Table\Type\name;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AiChatController;


 // Removed invalid class definition to fix syntax error

/*
|--------------------------------------------------------------------------
| Public (no auth)
|--------------------------------------------------------------------------
*/
Route::get('/', [LandingController::class, 'home'])->name('landing');
Route::get('/pricing', [LandingController::class, 'pricing'])->name('landing.pricing');
Route::get('/start-trial', [LandingController::class, 'startTrial'])
    ->middleware('throttle:trial-start')
    ->name('landing.start-trial');
Route::get('/legal/terms', [LandingController::class, 'terms'])->name('legal.terms');
Route::get('/legal/privacy', [LandingController::class, 'privacy'])->name('legal.privacy');
Route::get('/legal/refund-policy', [LandingController::class, 'refundPolicy'])->name('legal.refund-policy');

/*
| Google OAuth (Socialite) — public, no auth.
| /auth/google/* is the default; /oauth/google/* matches many Google Console examples.
*/
Route::get('/auth/google/redirect', [GoogleOAuthController::class, 'redirect'])->name('auth.google.redirect');
Route::get('/auth/google/callback', [GoogleOAuthController::class, 'callback'])->name('auth.google.callback');
/** @deprecated typo kept for old Google Console redirect URIs */
Route::get('/outh/google/callback', [GoogleOAuthController::class, 'callback'])->name('auth.google.callback.legacy_typo');
Route::get('/oauth/google/redirect', [GoogleOAuthController::class, 'redirect'])->name('oauth.google.redirect');
Route::get('/oauth/google/callback', [GoogleOAuthController::class, 'callback'])->name('oauth.google.callback');

/*
| Chargily webhook — public, HMAC-verified inside controller.
| /chargilypay/webhook matches common ngrok path configs; both hit the same handler.
*/
Route::post('/webhooks/chargily', [BillingController::class, 'webhookChargily'])
    ->name('billing.webhook.chargily'); // CSRF-exempted in bootstrap/app.php
Route::post('/chargilypay/webhook', [BillingController::class, 'webhookChargily'])
    ->name('billing.webhook.chargily.paypath');

/*
|--------------------------------------------------------------------------
| Authenticated (post Google login / classic Breeze)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->group(function () {
    /*
    | Global admin (Spatie `admin` role) — no company context required.
    */
    Route::middleware(['spatie_role:admin'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
        Route::get('/payments', [PaymentConfirmationController::class, 'index'])
            ->middleware('spatie_permission:payments.view')
            ->name('payments.index');
        Route::post('/payments/{payment}/confirm', [PaymentConfirmationController::class, 'confirm'])
            ->middleware('spatie_permission:payments.confirm')
            ->name('payments.confirm');
        Route::post('/payments/{payment}/reject', [PaymentConfirmationController::class, 'reject'])
            ->middleware('spatie_permission:payments.confirm')
            ->name('payments.reject');
        Route::get('/payments/{payment}/proof', [PaymentConfirmationController::class, 'downloadProof'])
            ->middleware('spatie_permission:payments.view')
            ->name('payments.proof');
        Route::get('/refund-requests', [RefundRequestAdminController::class, 'index'])
            ->middleware('spatie_permission:payments.view')
            ->name('refund-requests.index');
        Route::match(['put', 'patch'], '/refund-requests/{refundRequest}', [RefundRequestAdminController::class, 'update'])
            ->middleware('spatie_permission:payments.confirm')
            ->name('refund-requests.update');

        /* ── Plans (CRUD) ──────────────────────────────────────────── */
        Route::middleware('spatie_permission:plans.view')->group(function () {
            Route::get('/plans', [AdminPlanController::class, 'index'])->name('plans.index');
            Route::get('/plan-features', [AdminPlanFeatureController::class, 'index'])->name('plan-features.index');
            Route::match(['put', 'patch'], '/plan-features/{plan}', [AdminPlanFeatureController::class, 'update'])
                ->middleware('spatie_permission:plans.manage')
                ->name('plan-features.update');
            Route::get('/plans/create', [AdminPlanController::class, 'create'])
                ->middleware('spatie_permission:plans.manage')
                ->name('plans.create');
            Route::post('/plans', [AdminPlanController::class, 'store'])
                ->middleware('spatie_permission:plans.manage')
                ->name('plans.store');
            Route::get('/plans/{plan}/edit', [AdminPlanController::class, 'edit'])
                ->middleware('spatie_permission:plans.manage')
                ->name('plans.edit');
            Route::match(['put', 'patch'], '/plans/{plan}', [AdminPlanController::class, 'update'])
                ->middleware('spatie_permission:plans.manage')
                ->name('plans.update');
            Route::post('/plans/{plan}/toggle', [AdminPlanController::class, 'toggle'])
                ->middleware('spatie_permission:plans.manage')
                ->name('plans.toggle');
            Route::delete('/plans/{plan}', [AdminPlanController::class, 'destroy'])
                ->middleware('spatie_permission:plans.manage')
                ->name('plans.destroy');
        });

        /* ── Companies ─────────────────────────────────────────────── */
        Route::middleware('spatie_permission:companies.view')->group(function () {
            Route::get('/companies', [AdminCompanyController::class, 'index'])->name('companies.index');
            Route::get('/companies/{company}', [AdminCompanyController::class, 'show'])->name('companies.show');
        });

        /* ── Users + roles ─────────────────────────────────────────── */
        Route::middleware('spatie_permission:users.view')->group(function () {
            Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
            Route::post('/users/{user}/toggle-admin', [AdminUserController::class, 'toggleAdmin'])
                ->middleware('spatie_permission:users.manage')
                ->name('users.toggle-admin');
        });

        /* ── Subscriptions ─────────────────────────────────────────── */
        Route::middleware('spatie_permission:subscriptions.view')->group(function () {
            Route::get('/subscriptions', [AdminSubscriptionController::class, 'index'])
                ->name('subscriptions.index');
            Route::post('/subscriptions/{subscription}/cancel', [AdminSubscriptionController::class, 'cancel'])
                ->middleware('spatie_permission:subscriptions.manage')
                ->name('subscriptions.cancel');
            Route::post('/subscriptions/{subscription}/reactivate', [AdminSubscriptionController::class, 'reactivate'])
                ->middleware('spatie_permission:subscriptions.manage')
                ->name('subscriptions.reactivate');
            Route::post('/subscriptions/{subscription}/extend', [AdminSubscriptionController::class, 'extend'])
                ->middleware('spatie_permission:subscriptions.manage')
                ->name('subscriptions.extend');
        });
    });

    // Post-login onboarding (before a company exists)
    Route::get('/onboarding/company', [OnboardingController::class, 'showCompany'])->name('onboarding.company');
    Route::post('/onboarding/company', [OnboardingController::class, 'storeCompany'])->name('onboarding.company.store');

    // Company switcher (always available once you have 1+ companies)
    Route::get('/company/select', [CompanyController::class, 'select'])->name('company.select');
    Route::post('/company/select', [CompanyController::class, 'switchCompany'])->name('company.switch');

    // Billing pages — require a company context but NOT an active subscription
    Route::middleware(['company'])->group(function () {
        Route::get('/billing', [BillingController::class, 'index'])->name('billing.index');
        Route::get('/billing/checkout', [BillingController::class, 'checkout'])->name('billing.checkout');
        Route::post('/billing/chargily', [BillingController::class, 'startChargily'])
            ->middleware('throttle:billing-checkout')
            ->name('billing.chargily.start');
        Route::get('/billing/chargily/redirect/{payment}', [BillingController::class, 'redirectToChargily'])
            ->name('billing.chargily.redirect');
        Route::get('/billing/success/{payment}', [BillingController::class, 'success'])->name('billing.success');
        Route::get('/billing/failure/{payment}', [BillingController::class, 'failure'])->name('billing.failure');
        Route::post('/billing/bon', [BillingController::class, 'startBonDeCommande'])
            ->middleware('throttle:billing-bon')
            ->name('billing.bon.start');
        Route::get('/billing/bon/{payment}', [BillingController::class, 'showBonDeCommande'])->name('billing.bon.show');
        Route::get('/billing/bon/{payment}/download', [BillingController::class, 'downloadBonDeCommande'])->name('billing.bon.download');
        Route::post('/billing/bon/{payment}/proof', [BillingController::class, 'uploadBonProof'])->name('billing.bon.proof');
        Route::post('/billing/refund-requests', [RefundRequestController::class, 'store'])->name('billing.refund-requests.store');
    });

    // Profile (no company required)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

/*
|--------------------------------------------------------------------------
| App (auth + company + active subscription)
|--------------------------------------------------------------------------
*/


Route::middleware(['auth', 'verified', 'company', 'subscribed','plan_feature:ai_chat'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    /* ── Clients & Suppliers (server-side paginated) ────────────────── */
    Route::get('/clients', [ClientController::class, 'index'])->name('clients.index');
    Route::get('/clients/{contact}', [ClientController::class, 'show'])->name('clients.show');

    Route::get('/suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
    Route::get('/suppliers/{contact}', [SupplierController::class, 'show'])->name('suppliers.show');

    /* ── Unified Tiers (backwards-compatible) ───────────────────────── */
    Route::get('/contacts', [ContactController::class, 'index'])->name('contacts.index');
    Route::redirect('/settings/contacts', '/clients');
    Route::get('/contacts/{contact}', [ContactController::class, 'show'])->name('contacts.show');

    Route::post('/contacts', [ContactController::class, 'store'])
        
        ->name('contacts.store');

    Route::match(['put', 'patch'], '/contacts/{contact}', [ContactController::class, 'update'])
        ->middleware('role:owner,accountant')
        ->name('contacts.update');

    Route::delete('/contacts/{contact}', [ContactController::class, 'destroy'])
        ->middleware('role:owner')
        ->name('contacts.destroy');

    /* ── Typeahead lookups (infra for async combobox UIs) ───────────── */
    // throttle:suggest — 60/min/user. Debounced UX peaks at ~4 rps; the
    // cap exists to stop scripted address-book scraping.
    Route::middleware('throttle:suggest')->group(function () {
        Route::get('/suggest/contacts', [SuggestController::class, 'contacts'])
            ->name('suggest.contacts');
        Route::get('/suggest/accounts', [SuggestController::class, 'accounts'])
            ->name('suggest.accounts');
        Route::get('/search/global', GlobalSearchController::class)
            ->name('search.global');
    });

    /* ── Invoices ───────────────────────────────────────────────────── */
    Route::resource('invoices', InvoiceController::class)->except(['destroy']);

    /* ── Quotes ─────────────────────────────────────────────────────── */
    Route::resource('quotes', QuoteController::class)->except(['destroy'])
        ->middleware('plan_feature:quotes');
    Route::post('/quotes/{quote}/send', [QuoteController::class, 'send'])
        ->middleware('plan_feature:quotes')
        ->middleware('role:owner,accountant,admin')
        ->name('quotes.send');
    Route::post('/quotes/{quote}/accept', [QuoteController::class, 'accept'])
        ->middleware('plan_feature:quotes')
        ->middleware('role:owner,accountant,admin')
        ->name('quotes.accept');
    Route::post('/quotes/{quote}/reject', [QuoteController::class, 'reject'])
        ->middleware('plan_feature:quotes')
        ->middleware('role:owner,accountant,admin')
        ->name('quotes.reject');
    Route::get('/quotes/{quote}/pdf', [QuoteController::class, 'pdf'])
        ->middleware('plan_feature:quotes')
        ->name('quotes.pdf');
    Route::post('/quotes/{quote}/convert-to-invoice', [QuoteController::class, 'convertToInvoice'])
        ->middleware('plan_feature:quotes')
        ->middleware('role:owner,accountant,admin')
        ->name('quotes.convert-to-invoice');

    Route::post('/invoices/{invoice}/issue', [InvoiceController::class, 'issue'])
        ->middleware('role:owner,accountant,admin')
        ->name('invoices.issue');

    Route::post('/invoices/{invoice}/void', [InvoiceController::class, 'void'])
        ->middleware('role:owner,accountant,admin')
        ->name('invoices.void');

    Route::post('/invoices/{invoice}/credit', [InvoiceController::class, 'credit'])
        ->middleware('role:owner,accountant,admin')
        ->name('invoices.credit');

    Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])
        ->name('invoices.pdf');

    Route::post('/invoices/{invoice}/payments', [InvoicePaymentController::class, 'store'])
        ->middleware('plan_feature:invoice_payments')
        ->middleware('role:owner,accountant,admin')
        ->name('invoices.payments.store');
    Route::match(['put', 'patch'], '/invoices/{invoice}/payments/{payment}', [InvoicePaymentController::class, 'update'])
        ->middleware('plan_feature:invoice_payments')
        ->middleware('role:owner,accountant,admin')
        ->name('invoices.payments.update');
    Route::delete('/invoices/{invoice}/payments/{payment}', [InvoicePaymentController::class, 'destroy'])
        ->middleware('plan_feature:invoice_payments')
        ->middleware('role:owner,admin')
        ->name('invoices.payments.destroy');

    /* ── Expenses ───────────────────────────────────────────────────── */
    Route::post('/expenses/{expense}/confirm', [ExpenseController::class, 'confirm'])
        ->middleware('role:owner,accountant')
        ->name('expenses.confirm');

    Route::resource('expenses', ExpenseController::class)->except(['destroy', 'edit']);

    /* ── Documents (OCR) ────────────────────────────────────────────── */
    Route::get('/documents', [DocumentController::class, 'index'])
        ->middleware('plan_feature:ocr')
        ->name('documents.index');

    Route::post('/documents/upload', [DocumentController::class, 'upload'])
        ->middleware('role:owner,accountant')
        ->name('documents.upload');

    Route::get('/documents/{document}', [DocumentController::class, 'show'])
        ->middleware('plan_feature:ocr')
        ->name('documents.show');

    Route::get('/documents/{document}/status', [DocumentController::class, 'status'])
        ->middleware('plan_feature:ocr')
        ->name('documents.status');

    Route::get('/documents/{document}/download', [DocumentController::class, 'download'])
        ->middleware('plan_feature:ocr')
        ->name('documents.download');

    Route::post('/documents/{document}/retry', [DocumentController::class, 'retry'])
        ->middleware('role:owner,accountant')
        ->name('documents.retry');

    Route::get('/documents/{document}/use-in-expense', [DocumentController::class, 'useInExpense'])
        ->middleware('plan_feature:ocr')
        ->middleware('role:owner,accountant')
        ->name('documents.use-in-expense');

    /* ── Bank ───────────────────────────────────────────────────────── */
    Route::get('/bank', [BankController::class, 'index'])->name('bank.index');

    Route::post('/bank/import', [BankController::class, 'import'])
        ->middleware('role:owner,accountant')
        ->name('bank.import');
    Route::get('/bank/import', [BankController::class, 'showImport'])
        ->middleware('role:owner,accountant')
        ->name('bank.import.show');

    Route::post('/bank/import/confirm', [BankController::class, 'confirmImport'])
        ->middleware('role:owner,accountant')
        ->name('bank.import.confirm');

    Route::get('/bank/reconcile', [ReconciliationController::class, 'index'])
        ->middleware('plan_feature:bank_accounts')
        ->name('bank.reconcile');

    Route::post('/bank/reconcile/match', [ReconciliationController::class, 'match'])
        ->middleware('plan_feature:bank_accounts')
        ->middleware('role:owner,accountant')
        ->name('bank.reconcile.match');

    Route::post('/bank/reconcile/exclude', [ReconciliationController::class, 'exclude'])
        ->middleware('plan_feature:bank_accounts')
        ->middleware('role:owner,accountant')
        ->name('bank.reconcile.exclude');

    Route::post('/bank/reconcile/manual-post', [ReconciliationController::class, 'manualPost'])
        ->middleware('plan_feature:bank_accounts')
        ->middleware('role:owner,accountant')
        ->name('bank.reconcile.manual-post');

    /* ── Ledger ─────────────────────────────────────────────────────── */
    Route::get('/ledger/journal', [LedgerController::class, 'journal'])->name('ledger.journal');
    Route::get('/ledger/trial-balance', [LedgerController::class, 'trialBalance'])->name('ledger.trial-balance');
    Route::get('/ledger/account', [LedgerController::class, 'accountLedger'])->name('ledger.account');

    Route::post('/ledger/entry/post', [LedgerController::class, 'post'])
        ->middleware('role:owner,accountant')
        ->name('ledger.post');
    Route::post('/ledger/entries/{entry}/lock', [LedgerController::class, 'lockEntry'])
        ->middleware('role:owner,accountant')
        ->name('ledger.entries.lock');
    Route::post('/ledger/entries/{entry}/unlock', [LedgerController::class, 'unlockEntry'])
        ->middleware('role:owner')
        ->name('ledger.entries.unlock');

    Route::get('/ledger/entries/create', [JournalEntryController::class, 'create'])
        ->middleware('role:owner,accountant')
        ->name('ledger.entries.create');

    Route::post('/ledger/entries', [JournalEntryController::class, 'store'])
        ->middleware('role:owner,accountant')
        ->name('ledger.entries.store');

    Route::get('/ledger/entries/{entry}/edit', [JournalEntryController::class, 'edit'])
        ->middleware('role:owner,accountant')
        ->name('ledger.entries.edit');

    Route::match(['put', 'patch'], '/ledger/entries/{entry}', [JournalEntryController::class, 'update'])
        ->middleware('role:owner,accountant')
        ->name('ledger.entries.update');

    Route::delete('/ledger/entries/{entry}', [JournalEntryController::class, 'destroy'])
        ->middleware('role:owner,accountant')
        ->name('ledger.entries.destroy');

    Route::get('/ledger/entries/{entry}/lines', [JournalEntryController::class, 'lines'])
        ->name('ledger.entries.lines');

    Route::get('/ledger/lettering', [LetteringController::class, 'index'])
        ->name('ledger.lettering');

    Route::post('/ledger/lettering/manual', [LetteringController::class, 'matchManual'])
        ->middleware('role:owner,accountant')
        ->name('ledger.lettering.manual');

    Route::post('/ledger/lettering/auto', [LetteringController::class, 'auto'])
        ->middleware('role:owner,accountant')
        ->name('ledger.lettering.auto');

    Route::delete('/ledger/lettering/{lettering}', [LetteringController::class, 'destroy'])
        ->middleware('role:owner,accountant')
        ->name('ledger.lettering.destroy');

    /* ── Reports ────────────────────────────────────────────────────── */
    Route::get('/reports/vat', [ReportController::class, 'vat'])
        ->middleware('plan_feature:basic_reports')
        ->name('reports.vat');
    Route::get('/reports/vat/export', [ReportController::class, 'queueVatExport'])
        ->middleware('plan_feature:basic_reports')
        ->middleware('throttle:reports-queue')
        ->name('reports.vat.export');

    Route::get('/reports/bilan', [ReportController::class, 'bilan'])
        ->middleware('plan_feature:advanced_reports')
        ->name('reports.bilan');
    // bilanPdf no longer downloads synchronously — it dispatches a job and
    // redirects to the exports page where the user picks up the artifact
    // once the worker is done. The URL stays a GET so the existing link in
    // Bilan.jsx doesn't need custom CSRF / POST handling.
    //
    // throttle:reports-queue — caps Dompdf dispatches to 10/hour (and
    // burst 3/min) per user+tenant. A flood would otherwise starve the
    // shared `reports` worker for every other tenant.
    Route::get('/reports/bilan/pdf', [ReportController::class, 'queueBilanPdf'])
        ->middleware('plan_feature:advanced_reports')
        ->middleware('throttle:reports-queue')
        ->name('reports.bilan.pdf');

    /* ── Report runs (async exports) ────────────────────────────────── */
    Route::get('/reports/runs', [ReportRunController::class, 'index'])->name('reports.runs.index');
    // /reports/runs/{id} is the status-poll endpoint called every 3 s
    // from Exports.jsx per non-terminal row. throttle:reports-poll sits
    // above the normal multi-tab ceiling but blocks tight loops.
    Route::get('/reports/runs/{reportRun}', [ReportRunController::class, 'show'])
        ->middleware('throttle:reports-poll')
        ->name('reports.runs.show');
    // Downloads are bandwidth-heavy even with streamed output; the
    // cap prevents mirror-scraping without hurting real users.
    Route::get('/reports/runs/{reportRun}/download', [ReportRunController::class, 'download'])
        ->middleware('throttle:reports-download')
        ->name('reports.runs.download');

    Route::get('/reports/aged-receivables', [ReportController::class, 'agedReceivables'])
        ->name('reports.aged-receivables');
    Route::get('/reports/aged-payables', [ReportController::class, 'agedPayables'])
        ->name('reports.aged-payables');
    Route::get('/reports/analytic-trial-balance', [ReportController::class, 'analyticTrialBalance'])
        ->name('reports.analytic-trial-balance');
    Route::get('/reports/analytic-trial-balance/export', [ReportController::class, 'queueAnalyticTrialBalanceExport'])
        ->middleware('throttle:reports-queue')
        ->name('reports.analytic-trial-balance.export');
    Route::get('/reports/predictions', [ManagementPredictionController::class, 'index'])
        ->middleware('plan_feature:management_predictions')
        ->name('reports.predictions');
    Route::post('/reports/predictions/toggle', [ManagementPredictionController::class, 'toggle'])
        ->middleware('plan_feature:management_predictions')
        ->middleware('role:owner,accountant')
        ->name('reports.predictions.toggle');
    Route::post('/reports/predictions', [ManagementPredictionController::class, 'store'])
        ->middleware('plan_feature:management_predictions')
        ->middleware('role:owner,accountant')
        ->name('reports.predictions.store');
    Route::delete('/reports/predictions/{prediction}', [ManagementPredictionController::class, 'destroy'])
        ->middleware('plan_feature:management_predictions')
        ->middleware('role:owner')
        ->name('reports.predictions.destroy');

    /* ── Settings ───────────────────────────────────────────────────── */
    Route::get('/settings/company', [SettingsController::class, 'company'])->name('settings.company');

    Route::put('/settings/company', [SettingsController::class, 'updateCompany'])
        ->middleware('role:owner')
        ->name('settings.company.update');

    Route::get('/settings/performance', [SettingsController::class, 'performance'])
        ->middleware('role:owner,accountant')
        ->name('settings.performance');

    Route::get('/settings/accounts', [SettingsController::class, 'accounts'])->name('settings.accounts');
    Route::match(['put', 'patch'], '/settings/accounts/{account}/analytic-default', [SettingsController::class, 'updateAccountAnalyticDefault'])
        ->middleware('role:owner,accountant')
        ->name('settings.accounts.analytic-default.update');
    Route::get('/settings/analytics', [SettingsController::class, 'analytics'])
        ->middleware('plan_feature:analytic_accounting')
        ->name('settings.analytics');
    Route::get('/settings/currencies', [SettingsController::class, 'currencies'])
        ->middleware('plan_feature:multi_currency')
        ->name('settings.currencies');
    Route::post('/settings/currencies', [SettingsController::class, 'storeCurrency'])
        ->middleware('plan_feature:multi_currency')
        ->middleware('role:owner,accountant')
        ->name('settings.currencies.store');
    Route::match(['put', 'patch'], '/settings/currencies/{currency}', [SettingsController::class, 'updateCurrency'])
        ->middleware('plan_feature:multi_currency')
        ->middleware('role:owner,accountant')
        ->name('settings.currencies.update');
    Route::delete('/settings/currencies/{currency}', [SettingsController::class, 'destroyCurrency'])
        ->middleware('plan_feature:multi_currency')
        ->middleware('role:owner')
        ->name('settings.currencies.destroy');
    Route::post('/settings/exchange-rates', [SettingsController::class, 'storeExchangeRate'])
        ->middleware('plan_feature:multi_currency')
        ->middleware('role:owner,accountant')
        ->name('settings.exchange-rates.store');
    Route::delete('/settings/exchange-rates/{exchangeRate}', [SettingsController::class, 'destroyExchangeRate'])
        ->middleware('plan_feature:multi_currency')
        ->middleware('role:owner')
        ->name('settings.exchange-rates.destroy');
    Route::get('/settings/auto-counterpart-rules', [AutoCounterpartRuleController::class, 'index'])
        ->middleware('plan_feature:auto_counterpart_rules')
        ->name('settings.auto-counterpart-rules');
    Route::post('/settings/auto-counterpart-rules', [AutoCounterpartRuleController::class, 'store'])
        ->middleware('plan_feature:auto_counterpart_rules')
        ->middleware('role:owner,accountant')
        ->name('settings.auto-counterpart-rules.store');
    Route::match(['put', 'patch'], '/settings/auto-counterpart-rules/{rule}', [AutoCounterpartRuleController::class, 'update'])
        ->middleware('plan_feature:auto_counterpart_rules')
        ->middleware('role:owner,accountant')
        ->name('settings.auto-counterpart-rules.update');
    Route::delete('/settings/auto-counterpart-rules/{rule}', [AutoCounterpartRuleController::class, 'destroy'])
        ->middleware('plan_feature:auto_counterpart_rules')
        ->middleware('role:owner')
        ->name('settings.auto-counterpart-rules.destroy');
    Route::post('/settings/analytics/axes', [SettingsController::class, 'storeAnalyticAxis'])
        ->middleware('plan_feature:analytic_accounting')
        ->middleware('role:owner,accountant')
        ->name('settings.analytics.axes.store');
    Route::match(['put', 'patch'], '/settings/analytics/axes/{axis}', [SettingsController::class, 'updateAnalyticAxis'])
        ->middleware('plan_feature:analytic_accounting')
        ->middleware('role:owner,accountant')
        ->name('settings.analytics.axes.update');
    Route::delete('/settings/analytics/axes/{axis}', [SettingsController::class, 'destroyAnalyticAxis'])
        ->middleware('plan_feature:analytic_accounting')
        ->middleware('role:owner')
        ->name('settings.analytics.axes.destroy');
    Route::post('/settings/analytics/sections', [SettingsController::class, 'storeAnalyticSection'])
        ->middleware('plan_feature:analytic_accounting')
        ->middleware('role:owner,accountant')
        ->name('settings.analytics.sections.store');
    Route::match(['put', 'patch'], '/settings/analytics/sections/{section}', [SettingsController::class, 'updateAnalyticSection'])
        ->middleware('plan_feature:analytic_accounting')
        ->middleware('role:owner,accountant')
        ->name('settings.analytics.sections.update');
    Route::delete('/settings/analytics/sections/{section}', [SettingsController::class, 'destroyAnalyticSection'])
        ->middleware('plan_feature:analytic_accounting')
        ->middleware('role:owner')
        ->name('settings.analytics.sections.destroy');

    /* Journals */
    Route::get('/settings/journals', [SettingsController::class, 'journals'])
        ->name('settings.journals');
    Route::post('/settings/journals', [SettingsController::class, 'storeJournal'])
        ->middleware('role:owner,accountant')
        ->name('settings.journals.store');
    Route::match(['put', 'patch'], '/settings/journals/{journal}', [SettingsController::class, 'updateJournal'])
        ->middleware('role:owner,accountant')
        ->name('settings.journals.update');
    Route::post('/settings/journals/{journal}/permissions', [SettingsController::class, 'setJournalPermissions'])
        ->middleware('role:owner')
        ->name('settings.journals.permissions');
    Route::delete('/settings/journals/{journal}', [SettingsController::class, 'destroyJournal'])
        ->middleware('role:owner')
        ->name('settings.journals.destroy');

    /* Fiscal periods */
    Route::get('/settings/periods', [SettingsController::class, 'periods'])
        ->name('settings.periods');
    Route::get('/settings/entry-locks', [SettingsController::class, 'entryLocks'])
        ->middleware('role:owner,accountant')
        ->name('settings.entry-locks');
    Route::post('/settings/entry-locks/password', [SettingsController::class, 'setEntryLockPassword'])
        ->middleware('role:owner')
        ->name('settings.entry-locks.password');
    Route::post('/settings/entry-locks/date', [SettingsController::class, 'setDateEntryLock'])
        ->middleware('role:owner,accountant')
        ->name('settings.entry-locks.date');
    Route::post('/settings/entry-locks/date/clear', [SettingsController::class, 'clearDateEntryLock'])
        ->middleware('role:owner')
        ->name('settings.entry-locks.date.clear');
    Route::post('/settings/periods', [SettingsController::class, 'createPeriod'])
        ->middleware('role:owner,accountant')
        ->name('settings.periods.create');
    Route::post('/settings/periods/{period}/lock', [SettingsController::class, 'lockPeriod'])
        ->middleware('role:owner,accountant')
        ->name('settings.periods.lock');
    Route::post('/settings/periods/{period}/reopen', [SettingsController::class, 'reopenPeriod'])
        ->middleware('role:owner')
        ->name('settings.periods.reopen');

    /* Bank accounts */
    Route::get('/settings/bank-accounts', [SettingsController::class, 'bankAccounts'])
        ->middleware('plan_feature:bank_accounts')
        ->name('settings.bank-accounts');
    Route::post('/settings/bank-accounts', [SettingsController::class, 'storeBankAccount'])
        ->middleware('plan_feature:bank_accounts')
        ->middleware('role:owner,accountant')
        ->name('settings.bank-accounts.store');
    Route::match(['put', 'patch'], '/settings/bank-accounts/{bank_account}', [SettingsController::class, 'updateBankAccount'])
        ->middleware('plan_feature:bank_accounts')
        ->middleware('role:owner,accountant')
        ->name('settings.bank-accounts.update');
    Route::delete('/settings/bank-accounts/{bank_account}', [SettingsController::class, 'destroyBankAccount'])
        ->middleware('plan_feature:bank_accounts')
        ->middleware('role:owner')
        ->name('settings.bank-accounts.destroy');

    Route::post('/ai/chat', [AiChatController::class, 'ask'])
        ->middleware('throttle:ai-chat')
        ->name('ai.chat');

   
    
});

require __DIR__.'/auth.php';
