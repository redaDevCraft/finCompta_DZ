import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

function formatMoney(value) {
    return new Intl.NumberFormat('fr-DZ', {
        style: 'currency',
        currency: 'DZD',
        minimumFractionDigits: 2,
    }).format(Number(value ?? 0));
}

function formatDate(value) {
    if (!value) return '—';
    return new Intl.DateTimeFormat('fr-FR', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).format(new Date(value));
}

const statusMap = {
    draft: { label: 'Brouillon', className: 'bg-amber-100 text-amber-800' },
    posted: { label: 'Validée', className: 'bg-emerald-100 text-emerald-800' },
    reversed: { label: 'Extournée', className: 'bg-rose-100 text-rose-800' },
};

export default function AccountLedger({
    accounts = [],
    selectedAccountId = null,
    account = null,
    rows = [],
    openingBalance = 0,
    totals = { debit: 0, credit: 0, balance: 0 },
    filters = {},
}) {
    const [accountId, setAccountId] = useState(selectedAccountId || '');
    const [dateFrom, setDateFrom] = useState(filters.date_from || '');
    const [dateTo, setDateTo] = useState(filters.date_to || '');
    const [includeDraft, setIncludeDraft] = useState(!!filters.include_draft);

    const applyFilters = (e) => {
        e.preventDefault();
        router.get(route('ledger.account'), {
            account_id: accountId || undefined,
            date_from: dateFrom || undefined,
            date_to: dateTo || undefined,
            include_draft: includeDraft ? 1 : undefined,
        }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const resetFilters = () => {
        setAccountId('');
        setDateFrom('');
        setDateTo('');
        setIncludeDraft(false);
        router.get(route('ledger.account'));
    };

    return (
        <AuthenticatedLayout header="Grand Livre">
            <Head title="Grand Livre" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-semibold text-slate-900">Grand Livre</h1>
                    <p className="mt-1 text-sm text-slate-600">
                        Consultez le détail des mouvements d’un compte sur une période.
                    </p>
                </div>

                <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <form onSubmit={applyFilters} className="grid gap-4 md:grid-cols-6">
                        <div className="md:col-span-2">
                            <label className="mb-1 block text-sm font-medium text-slate-700">Compte</label>
                            <select
                                value={accountId}
                                onChange={(e) => setAccountId(e.target.value)}
                                className="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"
                            >
                                <option value="">— Sélectionner un compte —</option>
                                {accounts.map((a) => (
                                    <option key={a.id} value={a.id}>
                                        {a.code} — {a.label}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">Du</label>
                            <input
                                type="date"
                                value={dateFrom}
                                onChange={(e) => setDateFrom(e.target.value)}
                                className="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"
                            />
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">Au</label>
                            <input
                                type="date"
                                value={dateTo}
                                onChange={(e) => setDateTo(e.target.value)}
                                className="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"
                            />
                        </div>
                        <div className="flex items-end">
                            <label className="flex items-center gap-2 text-sm text-slate-700">
                                <input
                                    type="checkbox"
                                    checked={includeDraft}
                                    onChange={(e) => setIncludeDraft(e.target.checked)}
                                    className="h-4 w-4 rounded border-slate-300 text-indigo-600"
                                />
                                Inclure brouillons
                            </label>
                        </div>
                        <div className="flex flex-wrap items-end gap-2 md:justify-end">
                            <button
                                type="submit"
                                className="inline-flex items-center justify-center rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-slate-800"
                            >
                                Afficher
                            </button>
                            <button
                                type="button"
                                onClick={resetFilters}
                                className="inline-flex items-center justify-center rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
                            >
                                Reset
                            </button>
                        </div>
                    </form>
                </div>

                {account ? (
                    <div className="space-y-4">
                        <div className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <div>
                                <div className="text-xs font-medium uppercase tracking-wide text-slate-500">
                                    Compte
                                </div>
                                <div className="mt-1 text-xl font-semibold text-slate-900">
                                    {account.code} — {account.label}
                                </div>
                                <div className="mt-1 text-xs text-slate-500">
                                    Classe {account.class} · {account.type}
                                </div>
                            </div>
                            <div className="grid grid-cols-3 gap-6 text-right">
                                <div>
                                    <div className="text-xs text-slate-500">Solde d’ouverture</div>
                                    <div className="mt-1 text-sm font-semibold text-slate-900">
                                        {formatMoney(openingBalance)}
                                    </div>
                                </div>
                                <div>
                                    <div className="text-xs text-slate-500">Mouvements (D / C)</div>
                                    <div className="mt-1 text-sm font-semibold text-slate-900">
                                        {formatMoney(totals.debit)} / {formatMoney(totals.credit)}
                                    </div>
                                </div>
                                <div>
                                    <div className="text-xs text-slate-500">Solde de clôture</div>
                                    <div
                                        className={`mt-1 text-sm font-semibold ${
                                            totals.balance >= 0
                                                ? 'text-emerald-700'
                                                : 'text-rose-700'
                                        }`}
                                    >
                                        {formatMoney(totals.balance)}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-slate-200">
                                    <thead className="bg-slate-50">
                                        <tr>
                                            <th className="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                Date
                                            </th>
                                            <th className="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                Journal
                                            </th>
                                            <th className="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                Pièce
                                            </th>
                                            <th className="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                Libellé
                                            </th>
                                            <th className="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                Tiers
                                            </th>
                                            <th className="px-3 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                Débit
                                            </th>
                                            <th className="px-3 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                Crédit
                                            </th>
                                            <th className="px-3 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                Solde
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100 bg-white">
                                        <tr className="bg-slate-50/60">
                                            <td colSpan={5} className="px-3 py-2 text-sm font-medium text-slate-600">
                                                Solde d’ouverture au {dateFrom ? formatDate(dateFrom) : '—'}
                                            </td>
                                            <td colSpan={2} />
                                            <td className="px-3 py-2 text-right text-sm font-semibold text-slate-900">
                                                {formatMoney(openingBalance)}
                                            </td>
                                        </tr>
                                        {rows.length ? rows.map((row) => {
                                            const badge = statusMap[row.status];
                                            return (
                                                <tr key={row.id} className="hover:bg-slate-50">
                                                    <td className="whitespace-nowrap px-3 py-2 text-sm text-slate-700">
                                                        {formatDate(row.entry_date)}
                                                    </td>
                                                    <td className="px-3 py-2 text-sm text-slate-700">
                                                        <div className="flex items-center gap-2">
                                                            <span>{row.journal_code || '—'}</span>
                                                            {badge && (
                                                                <span
                                                                    className={`inline-flex rounded-full px-1.5 py-0.5 text-[10px] font-medium ${badge.className}`}
                                                                >
                                                                    {badge.label}
                                                                </span>
                                                            )}
                                                        </div>
                                                    </td>
                                                    <td className="px-3 py-2 text-sm text-slate-700">
                                                        {row.reference || '—'}
                                                    </td>
                                                    <td className="px-3 py-2 text-sm text-slate-700">
                                                        {row.line_description || row.entry_description || '—'}
                                                    </td>
                                                    <td className="px-3 py-2 text-sm text-slate-700">
                                                        {row.contact_name || '—'}
                                                    </td>
                                                    <td className="whitespace-nowrap px-3 py-2 text-right text-sm text-slate-700">
                                                        {row.debit > 0 ? formatMoney(row.debit) : '—'}
                                                    </td>
                                                    <td className="whitespace-nowrap px-3 py-2 text-right text-sm text-slate-700">
                                                        {row.credit > 0 ? formatMoney(row.credit) : '—'}
                                                    </td>
                                                    <td className="whitespace-nowrap px-3 py-2 text-right text-sm font-medium text-slate-900">
                                                        {formatMoney(row.running_balance)}
                                                    </td>
                                                </tr>
                                            );
                                        }) : (
                                            <tr>
                                                <td colSpan={8} className="px-3 py-8 text-center text-sm text-slate-500">
                                                    Aucun mouvement sur la période.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                    <tfoot className="bg-slate-50">
                                        <tr>
                                            <td colSpan={5} className="px-3 py-3 text-right text-sm font-semibold text-slate-700">
                                                Totaux
                                            </td>
                                            <td className="px-3 py-3 text-right text-sm font-semibold text-slate-900">
                                                {formatMoney(totals.debit)}
                                            </td>
                                            <td className="px-3 py-3 text-right text-sm font-semibold text-slate-900">
                                                {formatMoney(totals.credit)}
                                            </td>
                                            <td className="px-3 py-3 text-right text-sm font-semibold text-slate-900">
                                                {formatMoney(totals.balance)}
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                ) : (
                    <div className="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-500">
                        Sélectionnez un compte pour afficher son grand livre.
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
