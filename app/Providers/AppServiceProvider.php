<?php

namespace App\Providers;

use App\Contracts\PaymentGatewayInterface;
use App\Models\InvoicePayment;
use App\Observers\InvoicePaymentObserver;
use App\Services\ChargilyService;
use Chargily\ChargilyPay\Auth\Credentials;
use Chargily\ChargilyPay\ChargilyPay;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use App\Actions\Ai\AskAiAction;
use App\Services\Ai\AiContextExtractor;
use App\Services\Ai\AiIntentClassifier;
use App\Services\Ai\AiPromptBuilder;
use App\Services\Ai\AiResponseSanitizer;
use App\Services\Ai\GroqService;


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

    $this->app->singleton(GroqService::class, function () {
        return new GroqService(
            apiKey: config('services.groq.api_key'),
            model:  config('services.groq.model'),
        );
    });

    $this->app->singleton(AiIntentClassifier::class);
    $this->app->singleton(AiContextExtractor::class);
    $this->app->singleton(AiPromptBuilder::class);
    $this->app->singleton(AiResponseSanitizer::class);

    $this->app->bind(AskAiAction::class, function ($app) {
        return new AskAiAction(
            $app->make(AiIntentClassifier::class),
            $app->make(AiContextExtractor::class),
            $app->make(AiPromptBuilder::class),
            $app->make(GroqService::class),
            $app->make(AiResponseSanitizer::class),
        );
    });


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
