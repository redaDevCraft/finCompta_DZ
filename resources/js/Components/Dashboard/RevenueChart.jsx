import {
    AreaChart,
    Area,
    XAxis,
    YAxis,
    CartesianGrid,
    Tooltip,
    ResponsiveContainer,
    Legend,
} from 'recharts';
import { formatCurrency } from '@/utils/format';

export function RevenueChart({ invoiced, collected, expenses }) {
    const data = (invoiced ?? []).map((d, i) => ({
        label: d.label,
        invoiced: d.value,
        collected: collected?.[i]?.value ?? 0,
        expenses: expenses?.[i]?.value ?? 0,
    }));

    return (
        <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h3 className="mb-4 text-sm font-medium text-slate-500">
                Revenue & Collections — Last 12 months
            </h3>
            <ResponsiveContainer width="100%" height={260}>
                <AreaChart data={data} margin={{ top: 4, right: 4, left: 0, bottom: 0 }}>
                    <defs>
                        <linearGradient id="invoiced" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="5%" stopColor="#4f46e5" stopOpacity={0.15} />
                            <stop offset="95%" stopColor="#4f46e5" stopOpacity={0} />
                        </linearGradient>
                        <linearGradient id="collected" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="5%" stopColor="#16a34a" stopOpacity={0.15} />
                            <stop offset="95%" stopColor="#16a34a" stopOpacity={0} />
                        </linearGradient>
                    </defs>
                    <CartesianGrid strokeDasharray="3 3" stroke="#e2e8f0" />
                    <XAxis dataKey="label" tick={{ fontSize: 11 }} />
                    <YAxis tickFormatter={(v) => formatCurrency(v, true)} tick={{ fontSize: 11 }} />
                    <Tooltip formatter={(value) => formatCurrency(value)} />
                    <Legend />
                    <Area type="monotone" dataKey="invoiced" stroke="#4f46e5" fill="url(#invoiced)" name="Invoiced" />
                    <Area type="monotone" dataKey="collected" stroke="#16a34a" fill="url(#collected)" name="Collected" />
                    <Area type="monotone" dataKey="expenses" stroke="#d97706" fill="none" strokeDasharray="4 2" name="Expenses" />
                </AreaChart>
            </ResponsiveContainer>
        </div>
    );
}
