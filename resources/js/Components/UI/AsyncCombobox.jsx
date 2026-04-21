import { Combobox, ComboboxButton, ComboboxInput, ComboboxOption, ComboboxOptions } from '@headlessui/react';
import { Check, ChevronsUpDown, Loader2 } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

/**
 * Async typeahead combobox backed by a server suggest endpoint.
 *
 * Performance contract:
 *   - Debounces the query (default 250ms) to cap the request rate while
 *     the user types.
 *   - Aborts in-flight requests when the query changes (race-free).
 *   - Hits the wire only after `minChars` characters (default 2).
 *   - Caches results per (endpoint, query) for the session so toggling
 *     the dropdown doesn't refetch.
 *
 * UX contract:
 *   - `prefill` = the currently selected option as returned by the server
 *     (shape: { id, ...labelFields }). Lets edit forms show the chosen
 *     item without needing the full list pre-loaded.
 *   - `getLabel(option)` / `renderOption(option)` are the only things
 *     consumers must wire up for a given endpoint.
 *
 * Rules followed:
 *   - Never render an unbounded list: the server caps results at 50.
 *   - Never fire a request per keystroke: debounce + abort.
 *   - Never re-fetch data we already have: in-memory cache keyed on query.
 */
export default function AsyncCombobox({
    endpoint,
    value,
    onChange,
    prefill = null,
    placeholder = 'Rechercher…',
    emptyMessage = 'Aucun résultat',
    minCharsMessage = 'Tapez pour rechercher',
    getLabel,
    renderOption,
    extraParams = {},
    debounceMs = 250,
    minChars = 2,
    disabled = false,
    required = false,
    ariaLabel,
    className = '',
    nullable = true,
}) {
    const [query, setQuery] = useState('');
    const [options, setOptions] = useState(prefill ? [prefill] : []);
    const [loading, setLoading] = useState(false);
    const [open, setOpen] = useState(false);

    const cacheRef = useRef(new Map());
    const abortRef = useRef(null);
    const debounceTimerRef = useRef(null);
    // When the server 429s us, park new searches until the Retry-After
    // window has passed. Without this, a fast typist locked out by the
    // suggest rate limiter would keep re-triggering the same denial
    // with every keystroke.
    const cooldownUntilRef = useRef(0);

    // Keep a stable reference to extraParams so the effect below doesn't
    // re-run on every parent re-render when the object literal changes
    // identity without changing content.
    const extraParamsKey = useMemo(
        () => JSON.stringify(extraParams ?? {}),
        [extraParams]
    );

    const selectedOption = useMemo(() => {
        if (!value) return null;
        if (prefill && prefill.id === value) return prefill;
        return options.find((o) => o.id === value) ?? prefill ?? null;
    }, [value, options, prefill]);

    const runSearch = useCallback(
        async (rawQuery) => {
            const trimmed = rawQuery.trim();

            if (trimmed.length < minChars) {
                setOptions(prefill ? [prefill] : []);
                return;
            }

            const cacheKey = `${endpoint}?${extraParamsKey}&q=${trimmed}`;
            const cached = cacheRef.current.get(cacheKey);
            if (cached) {
                setOptions(cached);
                return;
            }

            if (Date.now() < cooldownUntilRef.current) {
                return;
            }

            if (abortRef.current) {
                abortRef.current.abort();
            }
            const controller = new AbortController();
            abortRef.current = controller;

            setLoading(true);

            try {
                const response = await window.axios.get(endpoint, {
                    params: { q: trimmed, ...extraParams },
                    signal: controller.signal,
                });
                const data = Array.isArray(response?.data?.data) ? response.data.data : [];
                cacheRef.current.set(cacheKey, data);
                setOptions(data);
            } catch (error) {
                if (error?.name === 'CanceledError' || error?.code === 'ERR_CANCELED') {
                    return;
                }
                // Honor the server's back-off. Axios surfaces 429 via
                // error.response with the headers the limiter emitted.
                if (error?.response?.status === 429) {
                    const retryAfter = Number(error.response.headers?.['retry-after']) || 30;
                    cooldownUntilRef.current = Date.now() + retryAfter * 1000;
                    setOptions([]);
                    return;
                }
                setOptions([]);
                if (typeof console !== 'undefined') {
                    console.error('AsyncCombobox: suggest request failed', error);
                }
            } finally {
                if (abortRef.current === controller) {
                    abortRef.current = null;
                    setLoading(false);
                }
            }
        },
        [endpoint, extraParams, extraParamsKey, minChars, prefill]
    );

    useEffect(() => {
        if (!open) return;

        if (debounceTimerRef.current) {
            clearTimeout(debounceTimerRef.current);
        }
        debounceTimerRef.current = setTimeout(() => {
            runSearch(query);
        }, debounceMs);

        return () => {
            if (debounceTimerRef.current) {
                clearTimeout(debounceTimerRef.current);
            }
        };
    }, [query, open, debounceMs, runSearch]);

    useEffect(() => () => {
        if (abortRef.current) abortRef.current.abort();
    }, []);

    const displayValue = useCallback(
        (opt) => (opt ? getLabel(opt) : ''),
        [getLabel]
    );

    const showMinChars = query.trim().length > 0 && query.trim().length < minChars;
    const emptyState = !loading && options.length === 0 && query.trim().length >= minChars;

    return (
        <Combobox
            value={selectedOption}
            onChange={(next) => onChange(next?.id ?? null, next ?? null)}
            disabled={disabled}
            nullable={nullable}
        >
            <div className={`relative ${className}`}>
                <div className="relative">
                    <ComboboxInput
                        className="w-full rounded-lg border border-slate-300 px-3 py-2 pr-9 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200 disabled:bg-slate-50"
                        displayValue={displayValue}
                        onChange={(e) => setQuery(e.target.value)}
                        onFocus={() => setOpen(true)}
                        placeholder={placeholder}
                        aria-label={ariaLabel}
                        required={required}
                        autoComplete="off"
                        spellCheck={false}
                    />
                    <ComboboxButton
                        className="absolute inset-y-0 right-0 flex items-center pr-2 text-slate-400"
                        aria-label="Ouvrir la liste"
                        onClick={() => setOpen(true)}
                    >
                        {loading ? (
                            <Loader2 className="h-4 w-4 animate-spin" />
                        ) : (
                            <ChevronsUpDown className="h-4 w-4" />
                        )}
                    </ComboboxButton>
                </div>

                <ComboboxOptions
                    className="absolute z-20 mt-1 max-h-60 w-full overflow-auto rounded-lg border border-slate-200 bg-white py-1 text-sm shadow-lg focus:outline-none"
                    onFocus={() => setOpen(true)}
                >
                    {showMinChars && (
                        <li className="px-3 py-2 text-xs text-slate-500">
                            {minCharsMessage}
                        </li>
                    )}

                    {emptyState && (
                        <li className="px-3 py-2 text-xs text-slate-500">
                            {emptyMessage}
                        </li>
                    )}

                    {options.map((option) => (
                        <ComboboxOption
                            key={option.id}
                            value={option}
                            className={({ focus }) =>
                                [
                                    'relative cursor-pointer select-none py-2 pl-8 pr-3',
                                    focus ? 'bg-indigo-50 text-indigo-900' : 'text-slate-800',
                                ].join(' ')
                            }
                        >
                            {({ selected }) => (
                                <>
                                    {selected && (
                                        <span className="absolute inset-y-0 left-0 flex items-center pl-2 text-indigo-600">
                                            <Check className="h-4 w-4" />
                                        </span>
                                    )}
                                    {renderOption ? renderOption(option) : getLabel(option)}
                                </>
                            )}
                        </ComboboxOption>
                    ))}
                </ComboboxOptions>
            </div>
        </Combobox>
    );
}
