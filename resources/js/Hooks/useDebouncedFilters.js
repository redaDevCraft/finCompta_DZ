import { router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';

/**
 * Debounced Inertia partial-reload helper for filterable list pages.
 *
 * Why this exists:
 *   - Typing into a filter input must not fire one Inertia reload per
 *     keystroke. Debouncing (default 300ms) coalesces the bursts.
 *   - Filter changes must NOT refetch the whole page (layout, flash,
 *     auth props, currentCompany, tax rates, etc.). We pin the reload
 *     to the `only` keys the caller declares.
 *   - The UI must reflect the in-flight state. We surface a `loading`
 *     boolean that toggles on start/finish for any router.get that
 *     matches our url + data signature.
 *
 * Usage:
 *   const { values, setValue, loading, apply, reset } = useDebouncedFilters({
 *       url: '/invoices',
 *       only: ['invoices', 'filters'],
 *       defaults: { search: '', status: '', date_from: '', date_to: '' },
 *       initial: filters, // from Inertia props
 *   });
 *
 * `apply()` is optional: the hook already debounces setValue → router.get.
 * Call it to force an immediate push (e.g. from the reset button).
 */
export function useDebouncedFilters({
    url,
    only = [],
    defaults = {},
    initial = {},
    debounceMs = 300,
}) {
    const mergedInitial = { ...defaults, ...stripEmpty(initial) };
    const [values, setValues] = useState(mergedInitial);
    const [loading, setLoading] = useState(false);

    const timerRef = useRef(null);
    const lastSentRef = useRef(JSON.stringify(mergedInitial));
    const mountedRef = useRef(false);

    const push = useCallback(
        (next) => {
            const stripped = stripEmpty(next);
            const signature = JSON.stringify(stripped);

            if (signature === lastSentRef.current) {
                return;
            }
            lastSentRef.current = signature;

            router.get(url, stripped, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only,
                onStart: () => setLoading(true),
                onFinish: () => setLoading(false),
            });
        },
        [url, only]
    );

    useEffect(() => {
        // Skip the first mount push: the page already rendered with the
        // initial props; re-pushing would be a wasted round-trip.
        if (!mountedRef.current) {
            mountedRef.current = true;
            return;
        }

        if (timerRef.current) clearTimeout(timerRef.current);
        timerRef.current = setTimeout(() => push(values), debounceMs);

        return () => {
            if (timerRef.current) clearTimeout(timerRef.current);
        };
    }, [values, debounceMs, push]);

    const setValue = useCallback((key, value) => {
        setValues((prev) => ({ ...prev, [key]: value }));
    }, []);

    const apply = useCallback(() => {
        if (timerRef.current) clearTimeout(timerRef.current);
        push(values);
    }, [values, push]);

    const reset = useCallback(() => {
        setValues(defaults);
        if (timerRef.current) clearTimeout(timerRef.current);
        // Force-push immediately without waiting for the effect tick, so
        // the reset button feels instant.
        push(defaults);
    }, [defaults, push]);

    return { values, setValue, setValues, loading, apply, reset };
}

function stripEmpty(obj) {
    const out = {};
    for (const [k, v] of Object.entries(obj ?? {})) {
        if (v === null || v === undefined || v === '') continue;
        out[k] = v;
    }
    return out;
}
