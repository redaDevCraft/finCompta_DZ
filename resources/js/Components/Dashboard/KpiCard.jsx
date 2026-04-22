import { formatCurrency } from '@/utils/format';

export function KpiCard({ label, value, subLabel, subValue, variant = 'default' }) {
    const variantClass = {
        default: 'border-slate-200',
        warning: 'border-yellow-400',
        danger: 'border-red-400',
        success: 'border-green-500',
    }[variant];

    return (
        <div className={`rounded-xl border bg-white p-5 shadow-sm ${variantClass}`}>
            <p className="text-sm text-slate-500">{label}</p>
            <p className="mt-1 text-2xl font-semibold tabular-nums">{formatCurrency(value)}</p>
            {subLabel && (
                <p className="mt-1 text-xs text-slate-500">
                    {subLabel}: <span className="font-medium">{subValue}</span>
                </p>
            )}
        </div>
    );
}
