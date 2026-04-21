import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

const formatCurrency = (value) =>
    new Intl.NumberFormat('fr-DZ', {
        style: 'currency',
        currency: 'DZD',
    }).format(Number(value ?? 0));

export default function Vat({
    period,
    sales_vat_buckets,
    purchase_vat,
    totals,
}) {
    const [year, setYear] = useState(period?.year ?? new Date().getFullYear());
    const [month, setMonth] = useState(period?.month ?? '');
    const [quarter, setQuarter] = useState(period?.quarter ?? '');

    const applyFilters = () => {
        router.get(
            '/reports/vat',
            {
                year,
                month: month || undefined,
                quarter: month ? undefined : quarter || undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            }
        );
    };

    const exportUrl = `/reports/vat/export?year=${year}${
        month ? `&month=${month}` : quarter ? `&quarter=${quarter}` : ''
    }`;

    const years = Array.from({ length: 6 }, (_, i) => new Date().getFullYear() - 3 + i);

    return (
        <AuthenticatedLayout header="Rapport TVA">
            <Head title="Rapport TVA" />

            <div className="space-y-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h2 className="text-xl font-semibold text-gray-900">Rapport TVA</h2>
                        <p className="text-sm text-gray-500">
                            Analyse de la TVA collectée et déductible
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-3">
                        <a
                            href="/reports/runs"
                            className="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            Mes exports
                        </a>
                        <a
                            href={exportUrl}
                            className="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-emerald-700"
                            title="L’export est généré en arrière-plan et apparaîtra dans « Mes exports »."
                        >
                            Exporter Excel
                        </a>
                    </div>
                </div>

                <div className="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    <div className="grid gap-4 md:grid-cols-3">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">
                                Année
                            </label>
                            <select
                                value={year}
                                onChange={(e) => setYear(e.target.value)}
                                className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                            >
                                {years.map((item) => (
                                    <option key={item} value={item}>
                                        {item}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">
                                Mois
                            </label>
                            <select
                                value={month}
                                onChange={(e) => {
                                    setMonth(e.target.value);
                                    if (e.target.value) setQuarter('');
                                }}
                                className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                            >
                                <option value="">Aucun</option>
                                <option value="1">Janvier</option>
                                <option value="2">Février</option>
                                <option value="3">Mars</option>
                                <option value="4">Avril</option>
                                <option value="5">Mai</option>
                                <option value="6">Juin</option>
                                <option value="7">Juillet</option>
                                <option value="8">Août</option>
                                <option value="9">Septembre</option>
                                <option value="10">Octobre</option>
                                <option value="11">Novembre</option>
                                <option value="12">Décembre</option>
                            </select>
                        </div>

                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">
                                Trimestre
                            </label>
                            <select
                                value={quarter}
                                onChange={(e) => {
                                    setQuarter(e.target.value);
                                    if (e.target.value) setMonth('');
                                }}
                                className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                            >
                                <option value="">Aucun</option>
                                <option value="1">T1</option>
                                <option value="2">T2</option>
                                <option value="3">T3</option>
                                <option value="4">T4</option>
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

                <div className="grid gap-6 xl:grid-cols-2">
                    <div className="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                        <div className="border-b border-gray-200 px-5 py-4">
                            <h3 className="text-base font-semibold text-gray-900">
                                TVA Collectée
                            </h3>
                        </div>

                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                                        Taux
                                    </th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                                        Base HT
                                    </th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                                        TVA
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100 bg-white">
                                {sales_vat_buckets?.length > 0 ? (
                                    sales_vat_buckets.map((row) => (
                                        <tr key={`sales-${row.rate_pct}`}>
                                            <td className="px-4 py-3 text-sm text-gray-700">
                                                {row.rate_pct}%
                                            </td>
                                            <td className="px-4 py-3 text-sm text-gray-700">
                                                {formatCurrency(row.base_ht)}
                                            </td>
                                            <td className="px-4 py-3 text-sm text-gray-700">
                                                {formatCurrency(row.vat_amount)}
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan={3} className="px-4 py-10 text-center text-sm text-gray-500">
                                            Aucune donnée
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    <div className="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                        <div className="border-b border-gray-200 px-5 py-4">
                            <h3 className="text-base font-semibold text-gray-900">
                                TVA Déductible
                            </h3>
                        </div>

                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                                        Taux
                                    </th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                                        Base HT
                                    </th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                                        TVA
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100 bg-white">
                                {purchase_vat?.length > 0 ? (
                                    purchase_vat.map((row) => (
                                        <tr key={`purchase-${row.rate_pct}`}>
                                            <td className="px-4 py-3 text-sm text-gray-700">
                                                {row.rate_pct}%
                                            </td>
                                            <td className="px-4 py-3 text-sm text-gray-700">
                                                {formatCurrency(row.base_ht)}
                                            </td>
                                            <td className="px-4 py-3 text-sm text-gray-700">
                                                {formatCurrency(row.vat_amount)}
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan={3} className="px-4 py-10 text-center text-sm text-gray-500">
                                            Aucune donnée
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                    <h3 className="text-base font-semibold text-gray-900">Synthèse</h3>

                    <div className="mt-4 grid gap-4 md:grid-cols-3">
                        <div className="rounded-lg bg-blue-50 p-4">
                            <p className="text-sm text-blue-700">Total collectée</p>
                            <p className="mt-1 text-lg font-bold text-blue-900">
                                {formatCurrency(totals?.collected)}
                            </p>
                        </div>

                        <div className="rounded-lg bg-amber-50 p-4">
                            <p className="text-sm text-amber-700">Total déductible</p>
                            <p className="mt-1 text-lg font-bold text-amber-900">
                                {formatCurrency(totals?.deductible)}
                            </p>
                        </div>

                        <div className="rounded-lg bg-emerald-50 p-4">
                            <p className="text-sm text-emerald-700">Solde à payer</p>
                            <p className="mt-1 text-lg font-bold text-emerald-900">
                                {formatCurrency(totals?.balance)}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}