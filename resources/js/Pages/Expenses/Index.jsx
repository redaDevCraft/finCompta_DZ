import { Head, Link, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import CursorPager from '@/Components/UI/CursorPager';
import TableSkeleton from '@/Components/UI/TableSkeleton';
import { useDebouncedFilters } from '@/Hooks/useDebouncedFilters';
import { useNotification } from '@/Context/NotificationContext';

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

function MiniStat({ label, value, tone = 'slate' }) {
    const tones = {
        slate: 'border-slate-200 bg-slate-50 text-slate-800',
        indigo: 'border-indigo-200 bg-indigo-50 text-indigo-800',
        emerald: 'border-emerald-200 bg-emerald-50 text-emerald-800',
        amber: 'border-amber-200 bg-amber-50 text-amber-800',
    };

    return (
        <div className={`rounded-lg border px-3 py-2 ${tones[tone] || tones.slate}`}>
            <p className="text-[11px] uppercase tracking-wide opacity-80">{label}</p>
            <p className="mt-1 text-sm font-semibold">{value}</p>
        </div>
    );
}

export default function Index({ expenses, filters = {} }) {
    const { confirm } = useNotification();

    // Debounced partial reload (same contract as Invoices/Index).
    const { values, setValue, loading, apply, reset } = useDebouncedFilters({
        url: '/expenses',
        only: ['expenses', 'filters'],
        defaults: { search: '', status: '', date_from: '', date_to: '' },
        initial: filters,
    });

    const confirmExpense = async (expense) => {
        const ok = await confirm({
            title: 'Confirmer la dépense',
            message: `Confirmer la dépense ${expense.reference || ''} et générer l’écriture comptable ?`,
            confirmLabel: 'Confirmer',
        });
        if (!ok) return;

        router.post(
            `/expenses/${expense.id}/confirm`,
            {},
            {
                preserveScroll: true,
            }
        );
    };

    const expenseRows = expenses?.data ?? [];
    const totalExpenses = expenseRows.length;
    const draftCount = expenseRows.filter((item) => item.status === 'draft').length;
    const paidCount = expenseRows.filter((item) => item.status === 'paid').length;
    const totalTtc = expenseRows.reduce((sum, item) => sum + Number(item.total_ttc ?? 0), 0);

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

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <MiniStat label="Dépenses (page)" value={totalExpenses} />
                    <MiniStat label="Brouillons" value={draftCount} tone="amber" />
                    <MiniStat label="Payées" value={paidCount} tone="emerald" />
                    <MiniStat label="Total TTC (page)" value={formatMoney(totalTtc)} tone="indigo" />
                </div>

                <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            apply();
                        }}
                        className="grid gap-4 md:grid-cols-5"
                    >
                        <div className="md:col-span-2">
                            <label className="mb-1 block text-sm font-medium text-slate-700">
                                Recherche
                            </label>
                            <input
                                type="text"
                                value={values.search}
                                onChange={(e) => setValue('search', e.target.value)}
                                placeholder="Fournisseur, description, référence…"
                                className="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"
                            />
                        </div>

                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">
                                Statut
                            </label>
                            <select
                                value={values.status}
                                onChange={(e) => setValue('status', e.target.value)}
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
                                value={values.date_from}
                                onChange={(e) => setValue('date_from', e.target.value)}
                                className="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"
                            />
                        </div>

                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">
                                Au
                            </label>
                            <input
                                type="date"
                                value={values.date_to}
                                onChange={(e) => setValue('date_to', e.target.value)}
                                className="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"
                            />
                        </div>

                        <div className="flex items-end gap-2 md:col-span-5">
                            <button
                                type="button"
                                onClick={() => setValue('status', 'draft')}
                                className="rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-medium text-amber-800 hover:bg-amber-100"
                            >
                                Brouillons
                            </button>
                            <button
                                type="button"
                                onClick={() => setValue('status', 'confirmed')}
                                className="rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1 text-xs font-medium text-indigo-800 hover:bg-indigo-100"
                            >
                                Confirmées
                            </button>
                            <button
                                type="button"
                                onClick={() => setValue('status', 'paid')}
                                className="rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-800 hover:bg-emerald-100"
                            >
                                Payées
                            </button>
                            <button
                                type="button"
                                onClick={reset}
                                className="inline-flex items-center justify-center rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
                            >
                                Réinitialiser
                            </button>
                            <span className="ml-auto text-xs text-slate-500">
                                {loading
                                    ? 'Chargement…'
                                    : `${expenses?.data?.length ?? 0} résultat(s) sur cette page`}
                            </span>
                        </div>
                    </form>
                </div>

                <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-200">
                            <thead className="sticky top-0 z-10 bg-slate-50">
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

                            {loading ? (
                                <TableSkeleton rows={8} columns={6} />
                            ) : (
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
                                            <div className="space-y-2">
                                                <p>Aucune dépense trouvée.</p>
                                                <Link
                                                    href="/expenses/create"
                                                    className="inline-flex rounded-md border border-indigo-200 bg-indigo-50 px-2.5 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-100"
                                                >
                                                    Ajouter une dépense
                                                </Link>
                                            </div>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                            )}
                        </table>
                    </div>

                    <CursorPager paginator={expenses} only={['expenses', 'filters']} />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
