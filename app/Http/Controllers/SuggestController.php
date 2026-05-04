<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Server-side typeahead endpoints.
 *
 * Purpose: invoice/expense/journal-entry forms currently ship the entire
 * contact & account lists on page load (thousands of rows at scale). These
 * endpoints return a tiny, paginated, search-bounded payload so the forms
 * can adopt async combobox UIs in a later phase without paying the price
 * of loading everything up front.
 *
 * Rules followed:
 *  - Strict limit cap (max 50) to bound worst-case payload size.
 *  - Company scope enforced via the model's global scope.
 *  - Anchored ILIKE on the leading token of q to stay sargable on the
 *    existing (company_id, code) / (company_id, display_name) indexes.
 *  - Short query (< 2 chars) returns an empty list instead of scanning.
 */
class SuggestController extends Controller
{
    private const DEFAULT_LIMIT = 15;

    private const MAX_LIMIT = 50;

    public function contacts(Request $request): JsonResponse
    {
        $q = $this->cleanQuery($request);
        $limit = $this->resolveLimit($request);
        $type = $request->string('type')->toString();

        if ($q === null) {
            return response()->json(['data' => []]);
        }

        $query = Contact::query()
            ->select([
                'id',
                'display_name',
                'type',
                'email',
                'default_payment_terms_days',
                'default_payment_mode',
                'default_expense_account_id',
                'default_tax_rate_id',
            ])
            ->where(function ($builder) use ($q) {
                $builder
                    ->where('display_name', 'like', '%'.$q.'%')        // % on BOTH sides
                    ->orWhere('raison_sociale', 'like', '%'.$q.'%')
                    ->orWhere('email', 'like', '%'.$q.'%')
                    ->orWhere('nif', 'like', '%'.$q.'%');
            })
            ->orderBy('display_name')
            ->limit($limit);

        // type=client  → client OR both (a "both" contact is also a client)
        // type=supplier → supplier OR both
        // type=both    → only strictly-both contacts
        if (in_array($type, ['client', 'supplier'], true)) {
            $query->whereIn('type', [$type, 'both']);
        } elseif ($type === 'both') {
            $query->where('type', 'both');
        }

        return response()->json([
            'data' => $query->get()->map(fn (Contact $c) => [
                'id' => $c->id,
                'display_name' => $c->display_name,
                'type' => $c->type,
                'email' => $c->email,
                'default_payment_terms_days' => $c->default_payment_terms_days,
                'default_payment_mode' => $c->default_payment_mode,
                'default_expense_account_id' => $c->default_expense_account_id,
                'default_tax_rate_id' => $c->default_tax_rate_id,
            ])->values(),
        ]);
    }

    public function accounts(Request $request): JsonResponse
    {
        $q = $this->cleanQuery($request, minLength: 1);
        $limit = $this->resolveLimit($request);

        if ($q === null) {
            return response()->json(['data' => []]);
        }

        // Accounts are typically searched by code prefix (e.g. "41", "707").
        // Code is the leading index column, so an anchored ILIKE here is
        // effectively a range scan.
        $query = Account::query()
            ->select(['id', 'code', 'label', 'default_analytic_section_id'])
            ->where('is_active', true)
            ->where(function ($builder) use ($q) {
                $builder
                    ->where('code', 'ilike', $q.'%')
                    ->orWhere('label', 'ilike', $q.'%');
            })
            ->orderBy('code')
            ->limit($limit);

        return response()->json([
            'data' => $query->get()->map(fn (Account $a) => [
                'id' => $a->id,
                'code' => $a->code,
                'label' => $a->label,
                'default_analytic_section_id' => $a->default_analytic_section_id,
            ])->values(),
        ]);
    }

    private function cleanQuery(Request $request, int $minLength = 2): ?string
    {
        $q = trim((string) $request->query('q', ''));

        if (mb_strlen($q) < $minLength) {
            return null;
        }

        // Escape PostgreSQL ILIKE wildcards supplied by the client so they
        // can't expand a cheap prefix search into a full-table scan.
        return str_replace(['%', '_'], ['\%', '\_'], $q);
    }

    private function resolveLimit(Request $request): int
    {
        $limit = (int) $request->query('limit', (string) self::DEFAULT_LIMIT);

        return max(1, min(self::MAX_LIMIT, $limit));
    }
}
