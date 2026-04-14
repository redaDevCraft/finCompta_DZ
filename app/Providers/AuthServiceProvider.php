<?php

namespace App\Providers;

use App\Models\Document;
use App\Models\Expense;
use App\Models\Invoice;
use App\Policies\DocumentPolicy;
use App\Policies\ExpensePolicy;
use App\Policies\InvoicePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Invoice::class => InvoicePolicy::class,
        Expense::class => ExpensePolicy::class,
        Document::class => DocumentPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}