<?php

use App\Http\Controllers\BankController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\LedgerController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReconciliationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

// Public auth routes handled by Breeze
Route::middleware(['auth'])->group(function () {
    Route::get('/company/select', [CompanyController::class, 'select'])->name('company.select');
    Route::post('/company/select', [CompanyController::class, 'switchCompany'])->name('company.switch');
});

Route::middleware(['auth', 'verified', 'company'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('invoices', InvoiceController::class)->except(['destroy']);

    Route::post('/invoices/{invoice}/issue', [InvoiceController::class, 'issue'])
        ->middleware('role:owner,accountant')
        ->name('invoices.issue');

    Route::post('/invoices/{invoice}/void', [InvoiceController::class, 'void'])
        ->middleware('role:owner,accountant')
        ->name('invoices.void');

    Route::post('/invoices/{invoice}/credit', [InvoiceController::class, 'credit'])
        ->middleware('role:owner,accountant')
        ->name('invoices.credit');

    Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])
        ->name('invoices.pdf');

    Route::post('/expenses/{expense}/confirm', [ExpenseController::class, 'confirm'])
        ->middleware('role:owner,accountant')
        ->name('expenses.confirm');

    Route::resource('expenses', ExpenseController::class)->except(['destroy']);

    // Documents
    Route::get('/documents', [DocumentController::class, 'index'])
        ->name('documents.index');

    Route::post('/documents/upload', [DocumentController::class, 'upload'])
        ->middleware('role:owner,accountant')
        ->name('documents.upload');

    Route::get('/documents/{document}/status', [DocumentController::class, 'status'])
        ->name('documents.status');

    Route::post('/documents/{document}/apply-suggestion', [DocumentController::class, 'applySuggestion'])
        ->middleware('role:owner,accountant')
        ->name('documents.apply-suggestion');

    Route::get('/bank', [BankController::class, 'index'])->name('bank.index');

    Route::post('/bank/import', [BankController::class, 'import'])
        ->middleware('role:owner,accountant')
        ->name('bank.import');

    Route::post('/bank/import/confirm', [BankController::class, 'confirmImport'])
        ->middleware('role:owner,accountant')
        ->name('bank.import.confirm');

    Route::get('/bank/reconcile', [ReconciliationController::class, 'index'])->name('bank.reconcile');

    Route::post('/bank/reconcile/match', [ReconciliationController::class, 'match'])
        ->middleware('role:owner,accountant')
        ->name('bank.reconcile.match');

    Route::post('/bank/reconcile/exclude', [ReconciliationController::class, 'exclude'])
        ->middleware('role:owner,accountant')
        ->name('bank.reconcile.exclude');

    Route::post('/bank/reconcile/manual-post', [ReconciliationController::class, 'manualPost'])
        ->middleware('role:owner,accountant')
        ->name('bank.reconcile.manual-post');

    Route::get('/ledger/journal', [LedgerController::class, 'journal'])->name('ledger.journal');
    Route::get('/ledger/trial-balance', [LedgerController::class, 'trialBalance'])->name('ledger.trial-balance');

    Route::post('/ledger/entry/post', [LedgerController::class, 'post'])
        ->middleware('role:owner,accountant')
        ->name('ledger.post');

    Route::get('/reports/vat', [ReportController::class, 'vat'])->name('reports.vat');
    Route::get('/reports/vat/export', [ReportController::class, 'vatExport'])->name('reports.vat.export');
    Route::get('/reports/income', [ReportController::class, 'incomeStatement'])->name('reports.income');

    Route::get('/settings/company', [SettingsController::class, 'company'])->name('settings.company');

    Route::put('/settings/company', [SettingsController::class, 'updateCompany'])
        ->middleware('role:owner')
        ->name('settings.company.update');

    Route::get('/settings/accounts', [SettingsController::class, 'accounts'])->name('settings.accounts');

    Route::get('/contacts', [ContactController::class, 'index'])->name('contacts.index');

    Route::post('/contacts', [ContactController::class, 'store'])
        ->middleware('role:owner,accountant')
        ->name('contacts.store');

    Route::match(['put', 'patch'], '/contacts/{contact}', [ContactController::class, 'update'])
        ->middleware('role:owner,accountant')
        ->name('contacts.update');

    Route::delete('/contacts/{contact}', [ContactController::class, 'destroy'])
        ->middleware('role:owner')
        ->name('contacts.destroy');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
