<?php

declare(strict_types=1);

namespace App\Support\ListQuery;

use Illuminate\Http\Request;

/**
 * Resolve a `per_page` query parameter with a strict upper bound.
 *
 * Rule: the server decides the cap. Clients may request less, never more.
 * This prevents a curious or malicious caller from asking for `per_page=1000000`
 * and exhausting memory / payload.
 */
final class PerPage
{
    public static function resolve(
        Request $request,
        int $default = 20,
        int $max = 100,
        string $key = 'per_page',
    ): int {
        $raw = (int) $request->input($key, $default);

        if ($raw <= 0) {
            return $default;
        }

        return min($raw, $max);
    }
}
