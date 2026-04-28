<?php

namespace App\Providers;

use App\Contracts\PaymentGatewayInterface;
use App\Models\InvoicePayment;
use App\Observers\InvoicePaymentObserver;
use App\Services\ChargilyService;
use AreportController;
use Chargily\ChargilyPay\Auth\Credentials;
use Chargily\ChargilyPay\ChargilyPay;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use localStorage;
use S3Storage;
use StorageInterface;
use TestController;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
{
    $this->app->singleton(\App\DTOs\ChargilyConfig::class, function () {
        $mode = strtolower((string) config('services.chargily.mode', 'test'));

        if (! in_array($mode, ['test', 'live'], true)) {
            $mode = 'test';
        }

        return new \App\DTOs\ChargilyConfig(
            mode: $mode,
            apiKey: trim((string) config('services.chargily.api_key', '')),
            secretKey: trim((string) config('services.chargily.secret_key', '')),
            webhookSecret: trim((string) config('services.chargily.webhook_secret', '')),
            locale: substr((string) config('services.chargily.locale', 'fr'), 0, 2),
            baseUrl: $mode === 'live'
                ? (string) config('services.chargily.base_url_live')
                : (string) config('services.chargily.base_url_test'),
        );
    });

    $this->app->singleton(Credentials::class, function ($app) {
        $cfg = $app->make(\App\DTOs\ChargilyConfig::class);

        return new Credentials([
            'mode' => $cfg->mode,
            'public' => $cfg->apiKey,
            'secret' => $cfg->secretKey,
        ]);
    });

    $this->app->singleton(ChargilyPay::class, function ($app) {
        return new ChargilyPay(
            $app->make(Credentials::class)
        );
    });

    $this->app->singleton(
        PaymentGatewayInterface::class,
        ChargilyService::class
    );
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
