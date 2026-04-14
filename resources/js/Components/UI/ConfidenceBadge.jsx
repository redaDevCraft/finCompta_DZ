export default function ConfidenceBadge({ confidence = 0 }) {
    const value = Number(confidence ?? 0);

    let className = 'bg-red-100 text-red-700 ring-red-200';
    let prefix = '⚠ ';

    if (value >= 0.85) {
        className = 'bg-emerald-100 text-emerald-700 ring-emerald-200';
        prefix = '';
    } else if (value >= 0.7) {
        className = 'bg-amber-100 text-amber-700 ring-amber-200';
    }

    return (
        <span
            className={`inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset ${className}`}
        >
            {prefix}
            {Math.round(value * 100)}%
        </span>
    );
}