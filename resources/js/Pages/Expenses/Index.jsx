import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

function formatMoney(value, currency = 'DZD') {
    return new Intl.NumberFormat('fr-DZ', {
        style: 'currency',
        currency,
        minimumFractionDigits: 2,
    }).format(Number(value || 0));
}

function formatDate(value) {
    if (!value) return '—';
    return new Intl.DateTimeFormat('fr-FR', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).format(new Date(value));
}

function statusBadge(status) {
    const map = {
        draft: 'bg-slate-100 text-slate-700',
        confirmed: 'bg-blue-100 text-blue-700',
        paid: 'bg-emerald-100 text-emerald-700',
        cancelled: 'bg-rose-100 text-rose-700',
    };

    const labels = {
        draft: 'Brouillon',
        confirmed: 'Confirmée',
        paid: 'Payée',
        cancelled: 'Annulée',
    };

    return (
        <span
            className={`inline-flex rounded-full px-2.5 py-1 text-xs font-medium ${
                map[status] || 'bg-slate-100 text-slate-700'
            }`}
        >
            {labels[status] || status || '—'}
        </span>
    );
}

export default function Index({ expenses, filters = {} }) {
    const { flash } = usePage().props;
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const [dateFrom, setDateFrom] = useState(filters.date_from ?? '');
    const [dateTo, setDateTo] = useState(filters.date_to ?? '');

    const submitFilters = (e) => {
        e.preventDefault();
        router.get(
            '/expenses',
            {
                search: search || undefined,
                status: status || undefined,
                date_from: dateFrom || undefined,
                date_to: dateTo || undefined,
            },
            { preserveState: true, replace: true }
        );
    };

    const resetFilters = () => {
        setSearch('');
        setStatus('');
        setDateFrom('');
        setDateTo('');
        router.get('/expenses', {}, { preserveState: true, replace: true });
    };

    const confirmExpense = (expense) => {
        if (
            !confirm(
                `Confirmer la dépense ${expense.reference || ''} et générer l’écriture comptable ?`
            )
        ) {
            return;
        }

        router.post(
            `/expenses/${expense.id}/confirm`,
            {},
            {
                preserveScroll: true,
            }
        );
    };

    return (
        <AuthenticatedLayout header="Dépenses">
            <Head title="Dépenses" />

            <div className="space-y-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-slate-900">Dépenses</h1>
                        <p className="mt-1 text-sm text-slate-600">
                            Gérez les dépenses de votre entreprise.
                        </p>
                    </div>

                    <Link
                        href="/expenses/create"
                        className="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-indigo-700"
                    >
                        + Nouvelle dépense
                    </Link>
                </div>

                {flash?.success && (
                    <div className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                        {flash.success}
                    </div>
                )}
                {flash?.error && (
                    <div className="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                        {flash.error}
                    </div>
                )}

                <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <form onSubmit={submitFilters} className="grid gap-4 md:grid-cols-5">
                        <div className="md:col-span-2">
                            <label className="mb-1 block text-sm font-medium text-slate-700">
                                Recherche
                            </label>
                            <input
                                type="text"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Fournisseur, description, référence…"
                                className="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"
                            />
                        </div>

                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">
                                Statut
                            </label>
                            <select
                                value={status}
                                onChange={(e) => setStatus(e.target.value)}
                                className="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"
                            >
                                <option value="">Tous</option>
                                <option value="draft">Brouillon</option>
                                <option value="confirmed">Confirmée</option>
                                <option value="paid">Payée</option>
                                <option value="cancelled">Annulée</option>
                            </select>
                        </div>

                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">
                                Du
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
                                Au
                            </label>
                            <input
                                type="date"
                                value={dateTo}
                                onChange={(e) => setDateTo(e.target.value)}
                                className="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"
                            />
                        </div>

                        <div className="flex items-end gap-2 md:col-span-5">
                            <button
                                type="submit"
                                className="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-indigo-700"
                            >
                                Filtrer
                            </button>
                            <button
                                type="button"
                                onClick={resetFilters}
                                className="inline-flex items-center justify-center rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
                            >
                                Réinitialiser
                            </button>
                            <span className="ml-auto text-xs text-slate-500">
                                {expenses?.total ?? expenses?.data?.length ?? 0} résultat(s)
                            </span>
                        </div>
                    </form>
                </div>

                <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-200">
                            <thead className="bg-slate-50">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                        Date
                                    </th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                        Fournisseur
                                    </th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                        Description
                                    </th>
                                    <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">
                                        Montant
                                    </th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                        Statut
                                    </th>
                                    <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">
                                        Actions
                                    </th>
                                </tr>
                            </thead>

                            <tbody className="divide-y divide-slate-100 bg-white">
                                {expenses?.data?.length ? (
                                    expenses.data.map((expense) => (
                                        <tr key={expense.id} className="hover:bg-slate-50">
                                            <td className="whitespace-nowrap px-4 py-3 text-sm text-slate-700">
                                                {formatDate(expense.expense_date)}
                                            </td>
                                            <td className="px-4 py-3 text-sm text-slate-900">
                                                {expense.contact?.display_name || '—'}
                                                {expense.reference && (
                                                    <div className="text-xs text-slate-400">
                                                        {expense.reference}
                                                    </div>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-sm text-slate-700">
                                                {expense.description || '—'}
                                            </td>
                                            <td className="whitespace-nowrap px-4 py-3 text-right text-sm font-medium text-slate-900">
                                                {formatMoney(
                                                    expense.total_ttc ?? 0,
                                                    expense.currency || 'DZD'
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-sm">
                                                {statusBadge(expense.status)}
                                            </td>
                                            <td className="px-4 py-3 text-right text-sm">
                                                <div className="flex flex-wrap items-center justify-end gap-2">
                                                    <Link
                                                        href={`/expenses/${expense.id}`}
                                                        className="rounded-md border border-slate-300 px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
                                                    >
                                                        Voir
                                                    </Link>
                                                    {expense.status === 'draft' && (
                                                        <button
                                                            type="button"
                                                            onClick={() => confirmExpense(expense)}
                                                            className="rounded-md bg-indigo-600 px-2.5 py-1.5 text-xs font-medium text-white hover:bg-indigo-700"
                                                        >
                                                            Confirmer
                                                        </button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td
                                            colSpan="6"
                                            className="px-4 py-10 text-center text-sm text-slate-500"
                                        >
                                            Aucune dépense trouvée.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {expenses?.links?.length > 0 && (
                        <div className="flex flex-wrap items-center gap-2 border-t border-slate-200 px-4 py-4">
                            {expenses.links.map((link, index) => (
                                <button
                                    key={index}
                                    type="button"
                                    disabled={!link.url}
                                    onClick={() => link.url && router.visit(link.url)}
                                    className={`rounded-lg px-3 py-1.5 text-sm ${
                                        link.active
                                            ? 'bg-indigo-600 text-white'
                                            : 'border border-slate-300 bg-white text-slate-700'
                                    } ${
                                        !link.url
                                            ? 'cursor-not-allowed opacity-50'
                                            : 'hover:bg-slate-50'
                                    }`}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
