import { Head, Link, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Badge from '@/Components/UI/Badge';
import CursorPager from '@/Components/UI/CursorPager';
import TableSkeleton from '@/Components/UI/TableSkeleton';
import { useDebouncedFilters } from '@/Hooks/useDebouncedFilters';
import { useNotification } from '@/Context/NotificationContext';

const formatCurrency = (value) =>
    new Intl.NumberFormat('fr-DZ', {
        style: 'currency',
        currency: 'DZD',
    }).format(Number(value ?? 0));

const formatDate = (value) => {
    if (!value) return '—';

    return new Intl.DateTimeFormat('fr-DZ', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).format(new Date(value));
};

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

export default function Index({ invoices, filters = {} }) {
    const { confirm } = useNotification();

    // Debounced partial reload: typing into a filter only re-fetches the
    // `invoices` + `filters` props, not the entire Inertia payload. The
    // hook itself coalesces keystrokes so we fire at most one request
    // per 300ms burst.
    const { values, setValue, loading, apply, reset } = useDebouncedFilters({
        url: '/invoices',
        only: ['invoices', 'filters'],
        defaults: { search: '', status: '', date_from: '', date_to: '' },
        initial: filters,
    });

    const issueInvoice = async (invoice) => {
        const ok = await confirm({
            title: 'Émettre la facture',
            message: `Émettre la facture ${invoice.invoice_number || '(brouillon)'} ? Elle sera numérotée et non-modifiable.`,
            confirmLabel: 'Émettre',
        });
        if (!ok) return;
        router.post(`/invoices/${invoice.id}/issue`, {}, { preserveScroll: true });
    };

    const voidInvoice = async (invoice) => {
        const ok = await confirm({
            title: 'Annuler la facture',
            message: `Annuler la facture ${invoice.invoice_number || ''} ?`,
            confirmLabel: 'Annuler la facture',
        });
        if (!ok) return;
        router.post(`/invoices/${invoice.id}/void`, {}, { preserveScroll: true });
    };

    const invoiceRows = invoices?.data ?? [];
    const totalInvoices = invoiceRows.length;
    const draftCount = invoiceRows.filter((item) => item.status === 'draft').length;
    const overdueCount = invoiceRows.filter(
        (item) => item.payment_status === 'overdue'
    ).length;
    const totalTtc = invoiceRows.reduce((sum, item) => sum + Number(item.total_ttc ?? 0), 0);

    return (
        <AuthenticatedLayout header="Factures">
            <Head title="Factures" />

            <div className="space-y-6">
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h2 className="text-xl font-semibold text-gray-900">Liste des factures</h2>
                        <p className="text-sm text-gray-500">
                            Consultez, filtrez et gérez vos factures
                        </p>
                    </div>

                    <Link
                        href="/invoices/create"
                        className="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-indigo-700"
                    >
                        Nouvelle facture
                    </Link>
                </div>

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <MiniStat label="Factures (page)" value={totalInvoices} />
                    <MiniStat label="Brouillons" value={draftCount} tone="amber" />
                    <MiniStat label="Échues non soldées" value={overdueCount} tone="indigo" />
                    <MiniStat label="Total TTC (page)" value={formatCurrency(totalTtc)} tone="emerald" />
                </div>

                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        apply();
                    }}
                    className="rounded-xl border border-gray-200 bg-white p-4 shadow-sm"
                >
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">
                                Recherche
                            </label>
                            <input
                                type="text"
                                value={values.search}
                                onChange={(e) => setValue('search', e.target.value)}
                                placeholder="Client ou N° facture"
                                className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200"
                            />
                        </div>

                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">
                                Statut
                            </label>
                            <select
                                value={values.status}
                                onChange={(e) => setValue('status', e.target.value)}
                                className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200"
                            >
                                <option value="">Tous</option>
                                <option value="draft">Brouillon</option>
                                <option value="issued">Émise</option>
                                <option value="partially_paid">Partiellement payée</option>
                                <option value="paid">Payée</option>
                                <option value="unpaid">Impayée</option>
                                <option value="overdue">En retard</option>
                                <option value="voided">Annulée</option>
                                <option value="replaced">Remplacée</option>
                            </select>
                        </div>

                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">
                                Du
                            </label>
                            <input
                                type="date"
                                value={values.date_from}
                                onChange={(e) => setValue('date_from', e.target.value)}
                                className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200"
                            />
                        </div>

                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">
                                Au
                            </label>
                            <input
                                type="date"
                                value={values.date_to}
                                onChange={(e) => setValue('date_to', e.target.value)}
                                className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200"
                            />
                        </div>
                    </div>

                    <div className="mt-4 flex flex-wrap items-center gap-3">
                        <button
                            type="button"
                            onClick={() => setValue('status', 'draft')}
                            className="rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-medium text-amber-800 hover:bg-amber-100"
                        >
                            Brouillons
                        </button>
                        <button
                            type="button"
                            onClick={() => setValue('status', 'issued')}
                            className="rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1 text-xs font-medium text-indigo-800 hover:bg-indigo-100"
                        >
                            Émises
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
                            onClick={() => setValue('status', 'overdue')}
                            className="rounded-full border border-rose-200 bg-rose-50 px-3 py-1 text-xs font-medium text-rose-800 hover:bg-rose-100"
                        >
                            En retard
                        </button>
                        <button
                            type="button"
                            onClick={reset}
                            className="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            Réinitialiser
                        </button>
                        <span className="ml-auto text-xs text-gray-500">
                            {loading
                                ? 'Chargement…'
                                : `${invoices?.data?.length ?? 0} résultat(s) sur cette page`}
                        </span>
                    </div>
                </form>

                <div className="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="sticky top-0 z-10 bg-gray-50">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                                        N° Facture
                                    </th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                                        Type
                                    </th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                                        Client
                                    </th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                                        Date d'émission
                                    </th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                                        Échéance
                                    </th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                                        Total HT
                                    </th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                                        Total TTC
                                    </th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                                        Payé
                                    </th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                                        Reste
                                    </th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                                        Statut
                                    </th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                                        Actions
                                    </th>
                                </tr>
                            </thead>

                            {loading ? (
                                <TableSkeleton rows={8} columns={11} />
                            ) : (
                            <tbody className="divide-y divide-gray-100 bg-white">
                                {invoices?.data?.length > 0 ? (
                                    invoices.data.map((invoice) => (
                                        <tr key={invoice.id} className="hover:bg-gray-50">
                                            <td className="px-4 py-3 text-sm font-medium text-gray-900">
                                                {invoice.invoice_number ?? 'Brouillon'}
                                            </td>
                                            <td className="px-4 py-3 text-sm text-gray-700">
                                                {invoice.document_type === 'invoice' && 'Facture'}
                                                {invoice.document_type === 'credit_note' && 'Avoir'}
                                                {invoice.document_type === 'quote' && 'Devis'}
                                                {invoice.document_type === 'delivery_note' && 'Bon de livraison'}
                                            </td>
                                            <td className="px-4 py-3 text-sm text-gray-700">
                                                {invoice.contact?.display_name ?? 'Client non renseigné'}
                                            </td>
                                            <td className="px-4 py-3 text-sm text-gray-700">
                                                {formatDate(invoice.issue_date)}
                                            </td>
                                            <td className="px-4 py-3 text-sm text-gray-700">
                                                {formatDate(invoice.due_date)}
                                            </td>
                                            <td className="px-4 py-3 text-sm text-gray-700">
                                                {formatCurrency(invoice.subtotal_ht)}
                                            </td>
                                            <td className="px-4 py-3 text-sm text-gray-700">
                                                {formatCurrency(invoice.total_ttc)}
                                            </td>
                                            <td className="px-4 py-3 text-sm text-gray-700">
                                                {formatCurrency(invoice.total_paid)}
                                            </td>
                                            <td className="px-4 py-3 text-sm text-gray-700">
                                                {formatCurrency(invoice.remaining)}
                                            </td>
                                            <td className="px-4 py-3 text-sm text-gray-700">
                                                <Badge status={invoice.payment_status ?? invoice.status} />
                                            </td>
                                            <td className="px-4 py-3 text-sm text-gray-700">
                                                <div className="flex flex-wrap gap-2">
                                                    <Link
                                                        href={`/invoices/${invoice.id}`}
                                                        className="rounded-md border border-gray-300 px-2.5 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50"
                                                    >
                                                        Voir
                                                    </Link>

                                                    {invoice.status === 'draft' && (
                                                        <button
                                                            type="button"
                                                            onClick={() => issueInvoice(invoice)}
                                                            className="rounded-md bg-indigo-600 px-2.5 py-1.5 text-xs font-medium text-white hover:bg-indigo-700"
                                                        >
                                                            Émettre
                                                        </button>
                                                    )}

                                                    {invoice.status !== 'draft' && invoice.pdf_path && (
                                                        <a
                                                            href={`/invoices/${invoice.id}/pdf`}
                                                            target="_blank"
                                                            rel="noreferrer"
                                                            className="rounded-md border border-blue-300 px-2.5 py-1.5 text-xs font-medium text-blue-700 hover:bg-blue-50"
                                                        >
                                                            PDF
                                                        </a>
                                                    )}

                                                    {(invoice.status === 'issued' || invoice.status === 'partially_paid') && (
                                                        <button
                                                            type="button"
                                                            onClick={() => voidInvoice(invoice)}
                                                            className="rounded-md border border-red-300 px-2.5 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50"
                                                        >
                                                            Annuler
                                                        </button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td
                                            colSpan={11}
                                            className="px-4 py-10 text-center text-sm text-gray-500"
                                        >
                                            <div className="space-y-2">
                                                <p>Aucune facture trouvée</p>
                                                <Link
                                                    href="/invoices/create"
                                                    className="inline-flex rounded-md border border-indigo-200 bg-indigo-50 px-2.5 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-100"
                                                >
                                                    Créer une facture
                                                </Link>
                                            </div>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                            )}
                        </table>
                    </div>

                    <CursorPager paginator={invoices} only={['invoices', 'filters']} />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}