<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Named rate limiters for performance-sensitive endpoints.
 *
 * Design rules followed:
 *
 *   1. Per-user keys, with an IP fallback for the unauthenticated
 *      edge. IP-only keying is deliberately avoided — a single office
 *      NAT would trip the whole company on one hot user.
 *
 *   2. Tenant included in the key (user:company) so the same user on
 *      two companies isn't bucketed across tenants, and a bot on one
 *      tenant never throttles another.
 *
 *   3. Explicit, documented limits. Each bucket has a comment stating
 *      the endpoint, the threat it defends against, and why the number
 *      was chosen. Silent fairness is not fairness.
 *
 *   4. Rate limiter backing store follows CACHE_STORE (database in
 *      dev, Redis recommended in prod). The file driver works but is
 *      not recommended at meaningful concurrency.
 *
 *   5. Response shaping is left to the framework: ThrottleRequests
 *      already returns JSON+Retry-After for expectsJson() callers and
 *      HTML+Retry-After for web callers. Both include a 429 status
 *      and the correct header, which is all the Exports-page poll
 *      loop and the typeahead client need to back off correctly.
 *
 * These buckets complement but do not replace edge-level protections
 * (Cloudflare / nginx limit_req) which address IP-level DDoS.
 */
class RateLimiterServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        /*
         * suggest — /suggest/contacts, /suggest/accounts
         *
         * Typeahead fires on every keystroke (debounced to 250 ms →
         * ~4 rps peak per user while typing). 60/min gives a comfort
         * ceiling for real typing while cutting off scripts that loop
         * `q=a`, `q=b`, ... to scrape the tenant's address book.
         */
        RateLimiter::for('suggest', fn (Request $request) => [
            Limit::perMinute(60)->by($this->userKey($request, 'suggest')),
        ]);

        /*
         * reports-poll — /reports/runs/{id}
         *
         * The exports page polls every 3 s per non-terminal row.
         * Two tabs × 5 pending runs × 3 s ≈ 200/min, so the bucket
         * is sized above that to not punish normal multi-tab users,
         * but firm enough to block a `while true; do curl; done`.
         */
        RateLimiter::for('reports-poll', fn (Request $request) => [
            Limit::perMinute(240)->by($this->userKey($request, 'reports-poll')),
        ]);

        /*
         * reports-queue — /reports/bilan/pdf (and future async exports)
         *
         * Queueing a bilan PDF dispatches a worst-case 600 s Dompdf
         * job. One user enqueueing 100/min can saturate the reports
         * queue for every other tenant sharing the worker pool.
         * 10/hour is aggressive-but-fair: real users click "Exporter
         * PDF" a handful of times per session, not ten times per
         * minute. Burst of 3 allows genuine year-end retries.
         */
        RateLimiter::for('reports-queue', fn (Request $request) => [
            Limit::perHour(10)->by($this->userKey($request, 'reports-queue')),
            Limit::perMinute(3)->by($this->userKey($request, 'reports-queue')),
        ]);

        /*
         * reports-download — /reports/runs/{id}/download
         *
         * Downloads stream through the controller (bounded memory)
         * but still burn IO and bandwidth. 60/min per user stops
         * mirror-scraping while being higher than any plausible human
         * clicking "Télécharger" in a session.
         */
        RateLimiter::for('reports-download', fn (Request $request) => [
            Limit::perMinute(60)->by($this->userKey($request, 'reports-download')),
        ]);

        /*
         * billing-checkout — /billing/chargily
         *
         * Checkout creation is cheap to click but expensive downstream
         * (gateway sessions + webhook noise). 5/min per user+tenant is
         * enough for retries while blocking scripted floods.
         */
        RateLimiter::for('billing-checkout', fn (Request $request) => [
            Limit::perMinute(5)->by($this->userKey($request, 'billing-checkout')),
        ]);

        /*
         * billing-bon — /billing/bon
         *
         * Bon de commande generation can be spammed accidentally or by
         * scripts. 3/hour/user+tenant keeps room for retries while
         * reducing duplicate offline payment intents.
         */
        RateLimiter::for('billing-bon', fn (Request $request) => [
            Limit::perHour(3)->by($this->userKey($request, 'billing-bon')),
        ]);

        /*
         * trial-start — /start-trial
         *
         * Public endpoint, so key by user when available and IP fallback.
         * This limits trial funnel abuse and repeated company creation loops.
         */
        RateLimiter::for('trial-start', fn (Request $request) => [
            Limit::perHour(8)->by($this->userKey($request, 'trial-start')),
        ]);
    }

    /**
     * Composite user/tenant key. Falls back to IP for unauthenticated
     * edge cases so public endpoints still bucket sensibly.
     *
     * The bucket name prefix keeps the four limiters in separate
     * namespaces in the cache store — throttling "suggest" must not
     * consume budget from "reports-download".
     */
    private function userKey(Request $request, string $bucket): string
    {
        $user = $request->user();

        if ($user) {
            $companyId = app()->has('currentCompany')
                ? app('currentCompany')->id
                : 'no-company';

            return "{$bucket}:u:{$user->getAuthIdentifier()}:c:{$companyId}";
        }

        return "{$bucket}:ip:".$request->ip();
    }
}
