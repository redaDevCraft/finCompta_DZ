import { Head, Link, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Badge from '@/Components/UI/Badge';

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

export default function Index({ quotes, filters = {} }) {
    const applyFilters = (next) => {
        router.get('/quotes', next, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    return (
        <AuthenticatedLayout header="Devis">
            <Head title="Devis" />

            <div className="space-y-6">
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h2 className="text-xl font-semibold text-gray-900">Liste des devis</h2>
                        <p className="text-sm text-gray-500">Créez, suivez et convertissez vos devis</p>
                    </div>
                    <Link
                        href="/quotes/create"
                        className="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-indigo-700"
                    >
                        Nouveau devis
                    </Link>
                </div>

                <div className="grid gap-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm md:grid-cols-4">
                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">Statut</label>
                        <select
                            value={filters.status ?? ''}
                            onChange={(e) => applyFilters({ ...filters, status: e.target.value })}
                            className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                        >
                            <option value="">Tous</option>
                            <option value="draft">Brouillon</option>
                            <option value="sent">Envoyé</option>
                            <option value="accepted">Accepté</option>
                            <option value="rejected">Rejeté</option>
                            <option value="expired">Expiré</option>
                        </select>
                    </div>
                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">Du</label>
                        <input
                            type="date"
                            value={filters.date_from ?? ''}
                            onChange={(e) => applyFilters({ ...filters, date_from: e.target.value })}
                            className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                        />
                    </div>
                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">Au</label>
                        <input
                            type="date"
                            value={filters.date_to ?? ''}
                            onChange={(e) => applyFilters({ ...filters, date_to: e.target.value })}
                            className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                        />
                    </div>
                    <div className="flex items-end">
                        <button
                            type="button"
                            onClick={() => applyFilters({ status: '', date_from: '', date_to: '' })}
                            className="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            Réinitialiser
                        </button>
                    </div>
                </div>

                <div className="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">N° Devis</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Client</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Date</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Montant</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Statut</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100 bg-white">
                                {quotes?.data?.length ? (
                                    quotes.data.map((quote) => (
                                        <tr key={quote.id} className="hover:bg-gray-50">
                                            <td className="px-4 py-3 text-sm font-medium text-gray-900">{quote.number}</td>
                                            <td className="px-4 py-3 text-sm text-gray-700">{quote.contact?.display_name ?? '—'}</td>
                                            <td className="px-4 py-3 text-sm text-gray-700">{formatDate(quote.issue_date)}</td>
                                            <td className="px-4 py-3 text-sm text-gray-700">{formatCurrency(quote.total)}</td>
                                            <td className="px-4 py-3 text-sm text-gray-700"><Badge status={quote.status} /></td>
                                            <td className="px-4 py-3 text-sm text-gray-700">
                                                <div className="flex flex-wrap gap-2">
                                                    <Link href={`/quotes/${quote.id}`} className="rounded-md border border-gray-300 px-2.5 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">Voir</Link>
                                                    {quote.status === 'draft' && (
                                                        <Link href={`/quotes/${quote.id}/edit`} className="rounded-md border border-indigo-300 px-2.5 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-50">Modifier</Link>
                                                    )}
                                                    {(quote.status === 'sent' || quote.status === 'accepted' || quote.status === 'draft') && !quote.invoice_id && (
                                                        <button
                                                            type="button"
                                                            onClick={() => router.post(`/quotes/${quote.id}/convert-to-invoice`)}
                                                            className="rounded-md bg-indigo-600 px-2.5 py-1.5 text-xs font-medium text-white hover:bg-indigo-700"
                                                        >
                                                            Convertir
                                                        </button>
                                                    )}
                                                    <a
                                                        href={`/quotes/${quote.id}/pdf`}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        className="rounded-md border border-blue-300 px-2.5 py-1.5 text-xs font-medium text-blue-700 hover:bg-blue-50"
                                                    >
                                                        PDF
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan={6} className="px-4 py-8 text-center text-sm text-gray-500">
                                            Aucun devis trouvé.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
