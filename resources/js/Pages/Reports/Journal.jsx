import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Fragment, useState } from 'react';
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

export default function Journal({
    entries,
    filters = {},
    journalOptions = [],
    statusOptions = [],
}) {
    const { flash } = usePage().props;
    const [status, setStatus] = useState(filters.status ?? '');
    const [journalCode, setJournalCode] = useState(filters.journal_code ?? '');
    const [dateFrom, setDateFrom] = useState(filters.date_from ?? '');
    const [dateTo, setDateTo] = useState(filters.date_to ?? '');
    const [openEntryId, setOpenEntryId] = useState(null);
    const postForm = useForm({ journal_entry_id: '' });

    const applyFilters = (event) => {
        event.preventDefault();

        router.get(
            route('ledger.journal'),
            {
                status: status || undefined,
                journal_code: journalCode || undefined,
                date_from: dateFrom || undefined,
                date_to: dateTo || undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            }
        );
    };

    const resetFilters = () => {
        setStatus('');
        setJournalCode('');
        setDateFrom('');
        setDateTo('');
        router.get(route('ledger.journal'));
    };

    const postEntry = (id) => {
        postForm.setData('journal_entry_id', id);
        postForm.post(route('ledger.post'), {
            preserveScroll: true,
        });
    };

    return (
        <AuthenticatedLayout header="Journal">
            <Head title="Journal" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold text-slate-900">Journal comptable</h1>
                        <p className="mt-1 text-sm text-slate-600">
                            Consultez et validez les écritures comptables.
                        </p>
                    </div>
                    <Link
                        href={route('ledger.entries.create')}
                        className="inline-flex items-center rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-indigo-700"
                    >
                        + Nouvelle écriture
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
                    <form onSubmit={applyFilters} className="grid gap-4 md:grid-cols-5">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">Statut</label>
                            <select
                                value={status}
                                onChange={(e) => setStatus(e.target.value)}
                                className="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"
                            >
                                <option value="">Tous</option>
                                {statusOptions.map((option) => (
                                    <option key={option.value} value={option.value}>
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">Journal</label>
                            <select
                                value={journalCode}
                                onChange={(e) => setJournalCode(e.target.value)}
                                className="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"
                            >
                                <option value="">Tous</option>
                                {journalOptions.map((option) => (
                                    <option key={option.value} value={option.value}>
                                        {option.label}
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
                        <div className="flex items-end gap-2">
                            <button
                                type="submit"
                                className="inline-flex flex-1 items-center justify-center rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-slate-800"
                            >
                                Filtrer
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

                <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-200">
                            <thead className="bg-slate-50">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                        Date
                                    </th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                        Journal
                                    </th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                        Reference
                                    </th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                        Libelle
                                    </th>
                                    <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">
                                        Debit
                                    </th>
                                    <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">
                                        Credit
                                    </th>
                                    <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 bg-white">
                                {entries?.data?.length ? (
                                    entries.data.map((entry) => {
                                        const statusBadge = statusMap[entry.status] ?? {
                                            label: entry.status ?? '—',
                                            className: 'bg-slate-100 text-slate-700',
                                        };

                                        return (
                                            <Fragment key={entry.id}>
                                                <tr key={entry.id} className="hover:bg-slate-50">
                                                    <td className="whitespace-nowrap px-4 py-3 text-sm text-slate-700">
                                                        {formatDate(entry.entry_date)}
                                                    </td>
                                                    <td className="px-4 py-3 text-sm text-slate-900">
                                                        {entry.journal_code || '—'}
                                                    </td>
                                                    <td className="px-4 py-3 text-sm text-slate-700">
                                                        {entry.reference || '—'}
                                                    </td>
                                                    <td className="px-4 py-3 text-sm text-slate-700">
                                                        {entry.description || '—'}
                                                    </td>
                                                    <td className="whitespace-nowrap px-4 py-3 text-right text-sm text-slate-700">
                                                        {formatMoney(entry.totals?.debit)}
                                                    </td>
                                                    <td className="whitespace-nowrap px-4 py-3 text-right text-sm text-slate-700">
                                                        {formatMoney(entry.totals?.credit)}
                                                    </td>
                                                    <td className="px-4 py-3 text-right text-sm">
                                                        <div className="flex items-center justify-end gap-2">
                                                            <span
                                                                className={`inline-flex rounded-full px-2.5 py-1 text-xs font-medium ${statusBadge.className}`}
                                                            >
                                                                {statusBadge.label}
                                                            </span>
                                                            {entry.status === 'draft' && (
                                                                <>
                                                                    <button
                                                                        type="button"
                                                                        onClick={() => postEntry(entry.id)}
                                                                        disabled={postForm.processing}
                                                                        className="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-emerald-700 disabled:opacity-60"
                                                                    >
                                                                        Valider
                                                                    </button>
                                                                    {(!entry.source_type || entry.source_type === 'manual') && (
                                                                        <>
                                                                            <Link
                                                                                href={route('ledger.entries.edit', entry.id)}
                                                                                className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
                                                                            >
                                                                                Modifier
                                                                            </Link>
                                                                            <button
                                                                                type="button"
                                                                                onClick={() => {
                                                                                    if (confirm('Supprimer cette écriture ?')) {
                                                                                        router.delete(route('ledger.entries.destroy', entry.id), {
                                                                                            preserveScroll: true,
                                                                                        });
                                                                                    }
                                                                                }}
                                                                                className="rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-medium text-rose-700 hover:bg-rose-50"
                                                                            >
                                                                                Supprimer
                                                                            </button>
                                                                        </>
                                                                    )}
                                                                </>
                                                            )}
                                                            <button
                                                                type="button"
                                                                onClick={() =>
                                                                    setOpenEntryId(
                                                                        openEntryId === entry.id ? null : entry.id
                                                                    )
                                                                }
                                                                className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
                                                            >
                                                                Lignes
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                                {openEntryId === entry.id && (
                                                    <tr className="bg-slate-50">
                                                        <td colSpan={7} className="px-4 py-3">
                                                            <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white">
                                                                <table className="min-w-full divide-y divide-slate-200">
                                                                    <thead className="bg-slate-100">
                                                                        <tr>
                                                                            <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                                                Compte
                                                                            </th>
                                                                            <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                                                Libelle
                                                                            </th>
                                                                            <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                                                Tiers
                                                                            </th>
                                                                            <th className="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                                                Debit
                                                                            </th>
                                                                            <th className="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                                                Credit
                                                                            </th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody className="divide-y divide-slate-100">
                                                                        {entry.lines?.length ? (
                                                                            entry.lines.map((line) => (
                                                                                <tr key={line.id}>
                                                                                    <td className="px-3 py-2 text-sm text-slate-700">
                                                                                        {line.account
                                                                                            ? `${line.account.code} - ${line.account.label}`
                                                                                            : '—'}
                                                                                    </td>
                                                                                    <td className="px-3 py-2 text-sm text-slate-700">
                                                                                        {line.description || '—'}
                                                                                    </td>
                                                                                    <td className="px-3 py-2 text-sm text-slate-700">
                                                                                        {line.contact?.display_name || '—'}
                                                                                    </td>
                                                                                    <td className="px-3 py-2 text-right text-sm text-slate-700">
                                                                                        {formatMoney(line.debit)}
                                                                                    </td>
                                                                                    <td className="px-3 py-2 text-right text-sm text-slate-700">
                                                                                        {formatMoney(line.credit)}
                                                                                    </td>
                                                                                </tr>
                                                                            ))
                                                                        ) : (
                                                                            <tr>
                                                                                <td
                                                                                    colSpan={5}
                                                                                    className="px-3 py-6 text-center text-sm text-slate-500"
                                                                                >
                                                                                    Aucune ligne.
                                                                                </td>
                                                                            </tr>
                                                                        )}
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                )}
                                            </Fragment>
                                        );
                                    })
                                ) : (
                                    <tr>
                                        <td colSpan={7} className="px-4 py-10 text-center text-sm text-slate-500">
                                            Aucune ecriture trouvee.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {entries?.links?.length > 0 && (
                        <div className="flex flex-wrap items-center gap-2 border-t border-slate-200 px-4 py-4">
                            {entries.links.map((link, index) => (
                                <button
                                    key={index}
                                    type="button"
                                    disabled={!link.url}
                                    onClick={() => link.url && router.visit(link.url)}
                                    className={`rounded-lg px-3 py-1.5 text-sm ${
                                        link.active
                                            ? 'bg-indigo-600 text-white'
                                            : 'border border-slate-300 bg-white text-slate-700'
                                    } ${!link.url ? 'cursor-not-allowed opacity-50' : 'hover:bg-slate-50'}`}
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
