import { Head, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

const formatCurrency = (value) =>
    new Intl.NumberFormat('fr-DZ', {
        style: 'currency',
        currency: 'DZD',
    }).format(Number(value ?? 0));

export default function AnalyticTrialBalance({
    rows = [],
    axes = [],
    sections = [],
    filters = {},
    totals = {},
}) {
    const [dateFrom, setDateFrom] = useState(filters.date_from || '');
    const [dateTo, setDateTo] = useState(filters.date_to || '');
    const [axisId, setAxisId] = useState(filters.axis_id || '');
    const [sectionId, setSectionId] = useState(filters.section_id || '');

    const filteredSections = useMemo(() => {
        if (!axisId) return sections;
        return sections.filter((item) => item.analytic_axis_id === axisId);
    }, [sections, axisId]);

    const applyFilters = () => {
        router.get(
            '/reports/analytic-trial-balance',
            {
                date_from: dateFrom || undefined,
                date_to: dateTo || undefined,
                axis_id: axisId || undefined,
                section_id: sectionId || undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            }
        );
    };

    const exportUrl = `/reports/analytic-trial-balance/export?date_from=${encodeURIComponent(dateFrom || '')}&date_to=${encodeURIComponent(dateTo || '')}&axis_id=${encodeURIComponent(axisId || '')}&section_id=${encodeURIComponent(sectionId || '')}`;

    return (
        <AuthenticatedLayout header="Balance analytique">
            <Head title="Balance analytique" />

            <div className="space-y-6">
                <div className="flex items-center justify-between gap-4">
                    <h2 className="text-xl font-semibold text-gray-900">Balance analytique</h2>
                    <a
                        href={exportUrl}
                        className="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-emerald-700"
                        title="L’export est généré en arrière-plan et apparaîtra dans « Mes exports »."
                    >
                        Exporter Excel
                    </a>
                </div>
                <div>
                    <p className="text-sm text-gray-500">
                        Balance par section analytique et compte comptable.
                    </p>
                </div>

                <div className="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    <div className="grid gap-4 md:grid-cols-4">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Date début</label>
                            <input
                                type="date"
                                value={dateFrom}
                                onChange={(e) => setDateFrom(e.target.value)}
                                className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                            />
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Date fin</label>
                            <input
                                type="date"
                                value={dateTo}
                                onChange={(e) => setDateTo(e.target.value)}
                                className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                            />
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Axe</label>
                            <select
                                value={axisId}
                                onChange={(e) => {
                                    setAxisId(e.target.value);
                                    setSectionId('');
                                }}
                                className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                            >
                                <option value="">Tous</option>
                                {axes.map((axis) => (
                                    <option key={axis.id} value={axis.id}>
                                        {axis.code} - {axis.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Section</label>
                            <select
                                value={sectionId}
                                onChange={(e) => setSectionId(e.target.value)}
                                className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                            >
                                <option value="">Toutes</option>
                                {filteredSections.map((section) => (
                                    <option key={section.id} value={section.id}>
                                        {section.code} - {section.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                    </div>

                    <div className="mt-4">
                        <button
                            type="button"
                            onClick={applyFilters}
                            className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                        >
                            Appliquer
                        </button>
                    </div>
                </div>

                <div className="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-gray-50">
                            <tr>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Compte</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Axe</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Section</th>
                                <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-600">Débit</th>
                                <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-600">Crédit</th>
                                <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-600">Solde</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100 bg-white">
                            {rows.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="px-4 py-10 text-center text-sm text-gray-500">
                                        Aucune donnée analytique sur cette période.
                                    </td>
                                </tr>
                            )}
                            {rows.map((row, idx) => (
                                <tr key={`${row.account_id}-${row.section_id ?? 'none'}-${idx}`}>
                                    <td className="px-4 py-3 text-sm text-gray-700">
                                        <span className="font-mono">{row.account_code}</span> - {row.account_label}
                                    </td>
                                    <td className="px-4 py-3 text-sm text-gray-700">
                                        {row.axis_code ? `${row.axis_code} - ${row.axis_name}` : '—'}
                                    </td>
                                    <td className="px-4 py-3 text-sm text-gray-700">
                                        {row.section_code ? `${row.section_code} - ${row.section_name}` : 'Non affecté'}
                                    </td>
                                    <td className="px-4 py-3 text-right text-sm text-gray-700">{formatCurrency(row.debit)}</td>
                                    <td className="px-4 py-3 text-right text-sm text-gray-700">{formatCurrency(row.credit)}</td>
                                    <td className="px-4 py-3 text-right text-sm font-semibold text-gray-900">{formatCurrency(row.balance)}</td>
                                </tr>
                            ))}
                        </tbody>
                        <tfoot className="bg-gray-50">
                            <tr>
                                <td colSpan={3} className="px-4 py-3 text-right text-sm font-semibold text-gray-700">Totaux</td>
                                <td className="px-4 py-3 text-right text-sm font-semibold text-gray-900">{formatCurrency(totals.debit)}</td>
                                <td className="px-4 py-3 text-right text-sm font-semibold text-gray-900">{formatCurrency(totals.credit)}</td>
                                <td className="px-4 py-3 text-right text-sm font-semibold text-gray-900">{formatCurrency(totals.balance)}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
