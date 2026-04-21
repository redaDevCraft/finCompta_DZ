<?php

declare(strict_types=1);

namespace App\Support\ListQuery;

use Illuminate\Contracts\Database\Query\Builder as BuilderContract;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;

/**
 * Parse `?sort=-col,other_col` into a validated ordering.
 *
 * Rules:
 *   - Only columns from the whitelist are accepted (drops SQL injection and
 *     non-indexed sort surface).
 *   - Prefix with `-` to sort DESC (leading `-`); otherwise ASC.
 *   - When no sort is provided (or everything gets filtered out), the caller
 *     supplied default ordering is used.
 *   - Always applies a tiebreaker on the caller supplied tiebreaker column
 *     (usually the primary key) so pagination is stable.
 *
 * This intentionally does not expose arbitrary columns. Every endpoint must
 * pass the list of sortable columns it actually has an index for.
 */
final class SortSpec
{
    /**
     * @param  array<int,string>  $allowed  Columns the caller permits sorting on.
     * @param  array<int,array{0:string,1:string}>  $default  Default ordering as [[col, dir], ...].
     * @param  string  $tiebreaker  Final deterministic order column (usually the PK).
     */
    public function __construct(
        public readonly array $allowed,
        public readonly array $default,
        public readonly string $tiebreaker = 'id',
    ) {}

    /**
     * @return array<int,array{0:string,1:string}>  [[column, 'asc'|'desc'], ...]
     */
    public function resolve(Request $request, string $key = 'sort'): array
    {
        $raw = (string) $request->input($key, '');

        if ($raw === '') {
            return $this->withTiebreaker($this->default);
        }

        $parts = array_filter(array_map('trim', explode(',', $raw)));
        $allowedMap = array_flip($this->allowed);
        $out = [];

        foreach ($parts as $part) {
            $dir = 'asc';
            $col = $part;

            if (str_starts_with($col, '-')) {
                $dir = 'desc';
                $col = substr($col, 1);
            } elseif (str_starts_with($col, '+')) {
                $col = substr($col, 1);
            }

            if ($col === '' || ! isset($allowedMap[$col])) {
                continue;
            }

            $out[] = [$col, $dir];
        }

        if (empty($out)) {
            return $this->withTiebreaker($this->default);
        }

        return $this->withTiebreaker($out);
    }

    /**
     * Apply the resolved ordering to a query builder.
     *
     * @param  EloquentBuilder|QueryBuilder  $query
     * @param  array<int,array{0:string,1:string}>  $orders
     */
    public function apply(EloquentBuilder|QueryBuilder|BuilderContract $query, array $orders): EloquentBuilder|QueryBuilder|BuilderContract
    {
        foreach ($orders as [$col, $dir]) {
            $query->orderBy($col, $dir);
        }

        return $query;
    }

    /**
     * @param  array<int,array{0:string,1:string}>  $orders
     * @return array<int,array{0:string,1:string}>
     */
    private function withTiebreaker(array $orders): array
    {
        foreach ($orders as [$col, ]) {
            if ($col === $this->tiebreaker) {
                return $orders;
            }
        }

        $orders[] = [$this->tiebreaker, 'desc'];

        return $orders;
    }
}
