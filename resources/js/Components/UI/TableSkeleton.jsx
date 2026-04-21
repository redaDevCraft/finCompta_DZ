/**
 * Shared skeleton rows for list tables.
 *
 * Rendered during Inertia partial reloads triggered by
 * useDebouncedFilters so the user gets immediate visual feedback
 * instead of a frozen-looking UI.
 */
export default function TableSkeleton({ rows = 6, columns = 5, className = '' }) {
    return (
        <tbody className={`divide-y divide-slate-100 ${className}`} aria-busy="true" aria-live="polite">
            {Array.from({ length: rows }, (_, rowIdx) => (
                <tr key={rowIdx} className="animate-pulse">
                    {Array.from({ length: columns }, (_, colIdx) => (
                        <td key={colIdx} className="px-4 py-3">
                            <div
                                className="h-3 rounded bg-slate-200"
                                style={{ width: `${40 + ((rowIdx + colIdx) * 13) % 45}%` }}
                            />
                        </td>
                    ))}
                </tr>
            ))}
        </tbody>
    );
}
