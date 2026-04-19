import { Head, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

function formatMoney(value) {
    return new Intl.NumberFormat('fr-DZ', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(value ?? 0));
}

const classLabels = {
    1: 'Classe 1 — Capitaux propres & passifs non-courants',
    2: 'Classe 2 — Immobilisations',
    3: 'Classe 3 — Stocks',
    4: 'Classe 4 — Tiers',
    5: 'Classe 5 — Trésorerie',
    6: 'Classe 6 — Charges',
    7: 'Classe 7 — Produits',
};

export default function TrialBalance({ rows = [], filters = {}, totals = {} }) {
    const [dateFrom, setDateFrom] = useState(filters.date_from || '');
    const [dateTo, setDateTo] = useState(filters.date_to || '');
    const [hideZero, setHideZero] = useState(true);
    const [search, setSearch] = useState('');

    const applyFilters = (e) => {
        e.preventDefault();
        router.get(
            route('ledger.trial-balance'),
            {
                date_from: dateFrom || undefined,
                date_to: dateTo || undefined,
            },
            { preserveState: true, replace: true }
        );
    };

    const clearFilters = () => {
        setDateFrom('');
        setDateTo('');
        router.get(route('ledger.trial-balance'), {}, { preserveState: true, replace: true });
    };

    const filtered = useMemo(() => {
        const needle = search.trim().toLowerCase();
        return rows.filter((r) => {
            if (hideZero && r.total_debit === 0 && r.total_credit === 0) return false;
            if (!needle) return true;
            return (
                r.code.toLowerCase().includes(needle) ||
                (r.label || '').toLowerCase().includes(needle)
            );
        });
    }, [rows, hideZero, search]);

    const grouped = useMemo(() => {
        const map = new Map();
        filtered.forEach((r) => {
            const cls = r.class || 0;
            if (!map.has(cls)) map.set(cls, []);
            map.get(cls).push(r);
        });
        return Array.from(map.entries()).sort((a, b) => a[0] - b[0]);
    }, [filtered]);

    const visibleTotals = useMemo(() => {
        return filtered.reduce(
            (acc, r) => {
                acc.debit += r.total_debit;
                acc.credit += r.total_credit;
                return acc;
            },
            { debit: 0, credit: 0 }
        );
    }, [filtered]);

    const isBalanced = Math.abs(visibleTotals.debit - visibleTotals.credit) < 0.01;

    return (
        <AuthenticatedLayout header="Balance comptable">
            <Head title="Balance comptable" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold text-slate-900">Balance générale</h1>
                        <p className="mt-1 text-sm text-slate-600">
                            Totaux débit / crédit et solde par compte sur les écritures validées.
                        </p>
                    </div>
                    <a
                        href={route('reports.bilan', { as_of_date: dateTo || undefined })}
                        className="inline-flex items-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
                    >
                        Voir le Bilan →
                    </a>
                </div>

                <form
                    onSubmit={applyFilters}
                    className="grid gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-5"
                >
                    <div>
                        <label className="mb-1 block text-sm font-medium text-slate-700">
                            Date début
                        </label>
                        <input
                            type="date"
                            value={dateFrom}
                            onChange={(e) => setDateFrom(e.target.value)}
                            className="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"
                        />
                    </div>
                    <div>
                        <label className="mb-1 block text-sm font-medium text-slate-700">
                            Date fin
                        </label>
                        <input
                            type="date"
                            value={dateTo}
                            onChange={(e) => setDateTo(e.target.value)}
                            className="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"
                        />
                    </div>
                    <div>
                        <label className="mb-1 block text-sm font-medium text-slate-700">
                            Recherche
                        </label>
                        <input
                            type="text"
                            placeholder="code ou libellé"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"
                        />
                    </div>
                    <div className="flex items-end">
                        <label className="flex items-center gap-2 text-sm text-slate-700">
                            <input
                                type="checkbox"
                                checked={hideZero}
                                onChange={(e) => setHideZero(e.target.checked)}
                                className="h-4 w-4 rounded border-slate-300 text-indigo-600"
                            />
                            Masquer comptes inactifs
                        </label>
                    </div>
                    <div className="flex items-end gap-2">
                        <button
                            type="submit"
                            className="flex-1 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-indigo-700"
                        >
                            Appliquer
                        </button>
                        <button
                            type="button"
                            onClick={clearFilters}
                            className="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
                        >
                            Réinitialiser
                        </button>
                    </div>
                </form>

                <div className="grid gap-4 sm:grid-cols-3">
                    <KpiCard label="Total Débit" value={visibleTotals.debit} tone="neutral" />
                    <KpiCard label="Total Crédit" value={visibleTotals.credit} tone="neutral" />
                    <KpiCard
                        label="Équilibre"
                        value={visibleTotals.debit - visibleTotals.credit}
                        tone={isBalanced ? 'success' : 'warning'}
                        note={isBalanced ? 'Balance équilibrée ✓' : 'Écart détecté'}
                    />
                </div>

                <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    {grouped.length === 0 ? (
                        <div className="p-12 text-center text-sm text-slate-500">
                            Aucun mouvement trouvé sur la période sélectionnée.
                        </div>
                    ) : (
                        <table className="min-w-full divide-y divide-slate-200">
                            <thead className="bg-slate-50">
                                <tr className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    <th className="px-4 py-3 text-left">Code</th>
                                    <th className="px-4 py-3 text-left">Libellé</th>
                                    <th className="px-4 py-3 text-right">Débit</th>
                                    <th className="px-4 py-3 text-right">Crédit</th>
                                    <th className="px-4 py-3 text-right">Solde</th>
                                    <th className="px-4 py-3"></th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {grouped.map(([cls, items]) => {
                                    const gDebit = items.reduce((s, i) => s + i.total_debit, 0);
                                    const gCredit = items.reduce((s, i) => s + i.total_credit, 0);

                                    return (
                                        <GroupRows
                                            key={cls}
                                            classId={cls}
                                            title={classLabels[cls] || `Classe ${cls}`}
                                            items={items}
                                            groupDebit={gDebit}
                                            groupCredit={gCredit}
                                        />
                                    );
                                })}
                            </tbody>
                            <tfoot className="bg-slate-900 text-white">
                                <tr className="text-sm">
                                    <td colSpan={2} className="px-4 py-3 font-semibold">
                                        TOTAL GÉNÉRAL
                                    </td>
                                    <td className="px-4 py-3 text-right font-semibold">
                                        {formatMoney(visibleTotals.debit)}
                                    </td>
                                    <td className="px-4 py-3 text-right font-semibold">
                                        {formatMoney(visibleTotals.credit)}
                                    </td>
                                    <td className="px-4 py-3 text-right font-semibold">
                                        {formatMoney(visibleTotals.debit - visibleTotals.credit)}
                                    </td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function KpiCard({ label, value, tone = 'neutral', note }) {
    const toneClass =
        tone === 'success'
            ? 'border-emerald-200 bg-emerald-50 text-emerald-900'
            : tone === 'warning'
            ? 'border-amber-200 bg-amber-50 text-amber-900'
            : 'border-slate-200 bg-white text-slate-900';

    return (
        <div className={`rounded-2xl border p-4 shadow-sm ${toneClass}`}>
            <div className="text-xs font-medium uppercase tracking-wide opacity-70">{label}</div>
            <div className="mt-1 text-2xl font-semibold">{formatMoney(value)}</div>
            {note && <div className="mt-1 text-xs opacity-70">{note}</div>}
        </div>
    );
}

function GroupRows({ classId, title, items, groupDebit, groupCredit }) {
    const [open, setOpen] = useState(true);

    return (
        <>
            <tr className="bg-slate-50">
                <td colSpan={6} className="px-4 py-2">
                    <button
                        type="button"
                        onClick={() => setOpen(!open)}
                        className="flex w-full items-center justify-between text-sm font-semibold text-slate-800"
                    >
                        <span className="flex items-center gap-2">
                            <span className="text-xs text-slate-400">{open ? '▾' : '▸'}</span>
                            {title}
                            <span className="rounded-full bg-white px-2 py-0.5 text-xs font-normal text-slate-500">
                                {items.length}
                            </span>
                        </span>
                        <span className="font-mono text-xs text-slate-600">
                            {formatMoney(groupDebit)} / {formatMoney(groupCredit)}
                        </span>
                    </button>
                </td>
            </tr>
            {open &&
                items.map((row) => {
                    const solde = row.total_debit - row.total_credit;

                    return (
                        <tr key={row.id} className="text-sm hover:bg-slate-50">
                            <td className="px-4 py-2 font-mono text-slate-700">{row.code}</td>
                            <td className="px-4 py-2 text-slate-800">{row.label}</td>
                            <td className="px-4 py-2 text-right tabular-nums">
                                {row.total_debit > 0 ? formatMoney(row.total_debit) : '—'}
                            </td>
                            <td className="px-4 py-2 text-right tabular-nums">
                                {row.total_credit > 0 ? formatMoney(row.total_credit) : '—'}
                            </td>
                            <td
                                className={`px-4 py-2 text-right font-medium tabular-nums ${
                                    solde > 0
                                        ? 'text-emerald-700'
                                        : solde < 0
                                        ? 'text-rose-700'
                                        : 'text-slate-500'
                                }`}
                            >
                                {formatMoney(solde)}
                            </td>
                            <td className="px-4 py-2 text-right">
                                <a
                                    href={route('ledger.account', { account_id: row.id })}
                                    className="text-xs font-medium text-indigo-600 hover:underline"
                                >
                                    Grand livre →
                                </a>
                            </td>
                        </tr>
                    );
                })}
        </>
    );
}
