<?php

namespace App\Providers;

use App\Models\InvoicePayment;
use App\Observers\InvoicePaymentObserver;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        InvoicePayment::observe(InvoicePaymentObserver::class);

        Vite::prefetch(concurrency: 3);
    }
}
