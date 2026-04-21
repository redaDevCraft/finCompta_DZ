import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';

/**
 * Prev/Next paginator for Laravel CursorPaginator responses.
 *
 * Why cursor and not numbered pages:
 *   - Offset pagination (LIMIT N OFFSET M) has to scan M rows before
 *     starting to emit, which degrades linearly with page depth.
 *   - Cursor/keyset pagination reads a B-tree range slice in O(log n)
 *     regardless of how deep the user navigates.
 *
 * Contract:
 *   - `paginator` is the raw Laravel CursorPaginator JSON shape:
 *       { data: [...], path, per_page, next_cursor, prev_cursor,
 *         next_page_url, prev_page_url, prev, next }
 *   - `only` lets the caller pin the Inertia partial reload keys so
 *     clicking prev/next doesn't refetch the whole page.
 *
 * Rules followed:
 *   - No total count. CursorPaginator doesn't compute one (that's the
 *     whole point of the migration). We surface "N résultats chargés"
 *     and an indicator when more exist.
 *   - `preserveScroll` so the user's vertical position survives a page
 *     change.
 */
export default function CursorPager({
    paginator,
    only = [],
    labelMore = 'Plus de résultats disponibles',
    labelEnd = 'Fin de la liste',
    className = '',
}) {
    if (!paginator) return null;

    const count = paginator?.data?.length ?? 0;
    const prevUrl = paginator.prev_page_url ?? null;
    const nextUrl = paginator.next_page_url ?? null;
    const hasMore = Boolean(nextUrl);

    const linkProps = {
        preserveScroll: true,
        preserveState: true,
        replace: true,
        only,
    };

    return (
        <div
            className={`flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-4 py-3 ${className}`}
        >
            <span className="text-xs text-slate-500">
                {count} résultat{count > 1 ? 's' : ''} chargé{count > 1 ? 's' : ''}
                {hasMore ? ` — ${labelMore.toLowerCase()}` : count > 0 ? ` — ${labelEnd.toLowerCase()}` : ''}
            </span>

            <div className="flex items-center gap-2">
                <CursorLink url={prevUrl} linkProps={linkProps} direction="prev" />
                <CursorLink url={nextUrl} linkProps={linkProps} direction="next" />
            </div>
        </div>
    );
}

function CursorLink({ url, linkProps, direction }) {
    const isPrev = direction === 'prev';
    const label = isPrev ? 'Précédent' : 'Suivant';
    const Icon = isPrev ? ChevronLeft : ChevronRight;

    const baseClasses =
        'inline-flex items-center gap-1 rounded-lg px-3 py-1.5 text-sm font-medium transition';
    const enabledClasses = 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50';
    const disabledClasses = 'cursor-not-allowed border border-slate-200 bg-slate-50 text-slate-400';

    if (!url) {
        return (
            <span className={`${baseClasses} ${disabledClasses}`} aria-disabled="true">
                {isPrev && <Icon className="h-4 w-4" />}
                {label}
                {!isPrev && <Icon className="h-4 w-4" />}
            </span>
        );
    }

    return (
        <Link href={url} {...linkProps} className={`${baseClasses} ${enabledClasses}`}>
            {isPrev && <Icon className="h-4 w-4" />}
            {label}
            {!isPrev && <Icon className="h-4 w-4" />}
        </Link>
    );
}
