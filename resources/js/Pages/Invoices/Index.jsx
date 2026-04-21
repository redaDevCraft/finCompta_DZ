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
                            <thead className="bg-gray-50">
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
                                        Statut
                                    </th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                                        Actions
                                    </th>
                                </tr>
                            </thead>

                            {loading ? (
                                <TableSkeleton rows={8} columns={9} />
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
                                                <Badge status={invoice.status} />
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
                                            colSpan={9}
                                            className="px-4 py-10 text-center text-sm text-gray-500"
                                        >
                                            Aucune facture trouvée
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