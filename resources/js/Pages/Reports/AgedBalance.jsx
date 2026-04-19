import { Head, router } from '@inertiajs/react';
import { Fragment, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

function formatMoney(value) {
    return new Intl.NumberFormat('fr-DZ', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
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

export default function AgedBalance({
    side = 'receivable',
    as_of_date,
    account_code,
    account = null,
    accounts = [],
    report,
}) {
    const [asOfDate, setAsOfDate] = useState(as_of_date);
    const [accountCode, setAccountCode] = useState(account_code);
    const [expanded, setExpanded] = useState(null);

    const isReceivable = side === 'receivable';
    const title = isReceivable
        ? 'Balance âgée clients (créances)'
        : 'Balance âgée fournisseurs (dettes)';
    const route_name = isReceivable
        ? 'reports.aged-receivables'
        : 'reports.aged-payables';

    const applyFilter = (e) => {
        e.preventDefault();
        router.get(
            route(route_name),
            { as_of_date: asOfDate, account_code: accountCode },
            { preserveState: true, replace: true }
        );
    };

    const rows = report?.rows ?? [];
    const totals = report?.totals ?? {
        b0_30: 0,
        b30_60: 0,
        b60_90: 0,
        b90_plus: 0,
        total: 0,
    };

    const displayAmount = (v) => (isReceivable ? v : -v);

    return (
        <AuthenticatedLayout header={title}>
            <Head title={title} />

            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-semibold text-slate-900">{title}</h1>
                    <p className="mt-1 text-sm text-slate-600">
                        Analyse d’ancienneté des soldes non lettrés par tiers, répartis en
                        tranches de 30 jours.
                    </p>
                </div>

                <form
                    onSubmit={applyFilter}
                    className="flex flex-wrap items-end gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
                >
                    <div>
                        <label className="mb-1 block text-sm font-medium text-slate-700">
                            Compte
                        </label>
                        <select
                            value={accountCode}
                            onChange={(e) => setAccountCode(e.target.value)}
                            className="rounded-xl border border-slate-300 px-3 py-2.5 text-sm"
                        >
                            {accounts
                                .filter((a) =>
                                    isReceivable
                                        ? a.code.startsWith('41') || a.code.startsWith('42')
                                        : a.code.startsWith('40') || a.code.startsWith('44')
                                )
                                .map((a) => (
                                    <option key={a.id} value={a.code}>
                                        {a.code} — {a.label}
                                    </option>
                                ))}
                        </select>
                    </div>
                    <div>
                        <label className="mb-1 block text-sm font-medium text-slate-700">
                            Date d’arrêté
                        </label>
                        <input
                            type="date"
                            value={asOfDate}
                            onChange={(e) => setAsOfDate(e.target.value)}
                            className="rounded-xl border border-slate-300 px-3 py-2.5 text-sm"
                        />
                    </div>
                    <button
                        type="submit"
                        className="rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-indigo-700"
                    >
                        Calculer
                    </button>
                </form>

                {!account ? (
                    <div className="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-500">
                        Aucun compte sélectionné ou trouvé pour ce préfixe.
                    </div>
                ) : (
                    <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <div className="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                            <div>
                                <div className="text-xs uppercase text-slate-500">Compte</div>
                                <div className="text-lg font-semibold text-slate-900">
                                    {account.code} — {account.label}
                                </div>
                            </div>
                            <div className="text-right">
                                <div className="text-xs text-slate-500">
                                    {isReceivable ? 'Total créances' : 'Total dettes'} au{' '}
                                    {formatDate(asOfDate)}
                                </div>
                                <div className="text-xl font-semibold text-slate-900">
                                    {formatMoney(displayAmount(totals.total))}
                                </div>
                            </div>
                        </div>

                        {rows.length === 0 ? (
                            <div className="p-10 text-center text-sm text-slate-500">
                                Aucun solde ouvert sur ce compte.
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-slate-200 text-sm">
                                    <thead className="bg-slate-50 text-xs uppercase text-slate-500">
                                        <tr>
                                            <th className="px-4 py-3 text-left">Tiers</th>
                                            <th className="px-4 py-3 text-right">0 – 30 j</th>
                                            <th className="px-4 py-3 text-right">30 – 60 j</th>
                                            <th className="px-4 py-3 text-right">60 – 90 j</th>
                                            <th className="px-4 py-3 text-right text-rose-600">
                                                &gt; 90 j
                                            </th>
                                            <th className="px-4 py-3 text-right">Total</th>
                                            <th className="px-4 py-3"></th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {rows.map((row) => (
                                            <Fragment key={row.contact_id ?? '_nc'}>
                                                <tr className="hover:bg-slate-50">
                                                    <td className="px-4 py-3 font-medium text-slate-900">
                                                        {row.contact_name}
                                                    </td>
                                                    <td className="px-4 py-3 text-right">
                                                        {formatMoney(displayAmount(row.b0_30))}
                                                    </td>
                                                    <td className="px-4 py-3 text-right">
                                                        {formatMoney(displayAmount(row.b30_60))}
                                                    </td>
                                                    <td className="px-4 py-3 text-right">
                                                        {formatMoney(displayAmount(row.b60_90))}
                                                    </td>
                                                    <td className="px-4 py-3 text-right font-semibold text-rose-700">
                                                        {formatMoney(displayAmount(row.b90_plus))}
                                                    </td>
                                                    <td className="px-4 py-3 text-right font-semibold text-slate-900">
                                                        {formatMoney(displayAmount(row.total))}
                                                    </td>
                                                    <td className="px-4 py-3 text-right">
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                setExpanded(
                                                                    expanded === row.contact_id
                                                                        ? null
                                                                        : row.contact_id
                                                                )
                                                            }
                                                            className="text-xs text-indigo-600 hover:underline"
                                                        >
                                                            {expanded === row.contact_id
                                                                ? 'Masquer'
                                                                : 'Détail'}
                                                        </button>
                                                    </td>
                                                </tr>
                                                {expanded === row.contact_id && (
                                                    <tr>
                                                        <td
                                                            colSpan={7}
                                                            className="bg-slate-50 px-6 py-3"
                                                        >
                                                            <table className="w-full text-xs">
                                                                <thead className="text-left text-slate-500">
                                                                    <tr>
                                                                        <th className="py-1">Date</th>
                                                                        <th className="py-1">Pièce</th>
                                                                        <th className="py-1">
                                                                            Libellé
                                                                        </th>
                                                                        <th className="py-1 text-right">
                                                                            Âge
                                                                        </th>
                                                                        <th className="py-1 text-right">
                                                                            Montant
                                                                        </th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody className="divide-y divide-slate-200">
                                                                    {row.lines.map((l) => (
                                                                        <tr key={l.id}>
                                                                            <td className="py-1">
                                                                                {formatDate(l.entry_date)}
                                                                            </td>
                                                                            <td className="py-1">
                                                                                {l.reference || '—'}
                                                                            </td>
                                                                            <td className="py-1">
                                                                                {l.description || '—'}
                                                                            </td>
                                                                            <td className="py-1 text-right">
                                                                                {l.age_days} j
                                                                            </td>
                                                                            <td className="py-1 text-right">
                                                                                {formatMoney(
                                                                                    displayAmount(
                                                                                        l.amount
                                                                                    )
                                                                                )}
                                                                            </td>
                                                                        </tr>
                                                                    ))}
                                                                </tbody>
                                                            </table>
                                                        </td>
                                                    </tr>
                                                )}
                                            </Fragment>
                                        ))}
                                    </tbody>
                                    <tfoot className="bg-slate-100 font-semibold">
                                        <tr>
                                            <td className="px-4 py-3">Total</td>
                                            <td className="px-4 py-3 text-right">
                                                {formatMoney(displayAmount(totals.b0_30))}
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                {formatMoney(displayAmount(totals.b30_60))}
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                {formatMoney(displayAmount(totals.b60_90))}
                                            </td>
                                            <td className="px-4 py-3 text-right text-rose-700">
                                                {formatMoney(displayAmount(totals.b90_plus))}
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                {formatMoney(displayAmount(totals.total))}
                                            </td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        )}
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
