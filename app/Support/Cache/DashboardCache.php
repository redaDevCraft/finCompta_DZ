<?php

declare(strict_types=1);

namespace App\Support\Cache;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Per-company dashboard cache.
 *
 * Strategy: a per-company revision counter is embedded in every cache key.
 * Invalidation is done by bumping the counter (a single INCR), which makes
 * every existing key unreachable in O(1) without iterating the store. This
 * works on any cache driver (file/database/redis) — tags not required.
 *
 *   Read:   dashboard:{company}:r{rev}:{date}
 *   Bust:   increment "dashboard:rev:{company}"
 *
 * The revision counter itself never expires. On cold start it defaults to 0.
 *
 * Consumers:
 *   - DashboardController::index  reads via keyFor()
 *   - Write paths (invoice issue/void, expense confirm, journal post,
 *     bank import confirm, reconcile match, document upload) call forget()
 *
 * With explicit invalidation in place, TTL can safely grow from 60s to
 * CACHE_TTL_SECONDS (5 minutes) — stale data is now bounded by writes, not
 * by clock.
 */
final class DashboardCache
{
    public const CACHE_TTL_SECONDS = 300;

    public static function keyFor(string $companyId, Carbon $today): string
    {
        $rev = self::revision($companyId);

        return "dashboard:{$companyId}:r{$rev}:".$today->toDateString();
    }

    /**
     * Invalidate every cached dashboard payload for the given company.
     *
     * Safe to call inside a DB transaction: it only touches the cache store.
     * Call after any write that would shift KPIs / series / top-N lists.
     */
    public static function forget(string $companyId): void
    {
        Cache::increment(self::revisionKey($companyId));
    }

    private static function revision(string $companyId): int
    {
        return (int) Cache::rememberForever(
            self::revisionKey($companyId),
            static fn () => 0,
        );
    }

    private static function revisionKey(string $companyId): string
    {
        return "dashboard:rev:{$companyId}";
    }
}
