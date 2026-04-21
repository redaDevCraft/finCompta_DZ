<?php

declare(strict_types=1);

namespace App\Support\ListQuery;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Apply a date range filter that stays index-friendly.
 *
 * We deliberately do not use `whereDate(col, '>=', $x)`: on PostgreSQL that
 * wraps the column in a cast, which defeats B-tree index usage on pure `date`
 * columns under some locales/drivers. Using `where(col, '>=', $startDate)` with
 * a properly bound date is sargable and hits the composite index on
 * `(company_id, <date_col>)`.
 *
 * Callers provide the column that must be prefixed with the table if the query
 * has joins (e.g. 'journal_entries.entry_date').
 */
final class DateRange
{
    /**
     * @param  EloquentBuilder|QueryBuilder  $query
     */
    public static function apply(
        EloquentBuilder|QueryBuilder $query,
        ?string $from,
        ?string $to,
        string $column,
    ): EloquentBuilder|QueryBuilder {
        $fromDate = self::toDate($from);
        $toDate = self::toDate($to);

        if ($fromDate !== null) {
            $query->where($column, '>=', $fromDate);
        }

        if ($toDate !== null) {
            $query->where($column, '<=', $toDate);
        }

        return $query;
    }

    private static function toDate(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
