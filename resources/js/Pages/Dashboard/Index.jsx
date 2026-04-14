import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeftRight,
    BadgeEuro,
    FileText,
    Receipt,
    Wallet,
} from 'lucide-react';
import StatCard from '@/Components/UI/StatCard';
import Alert from '@/Components/UI/Alert';
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

export default function Index({
    revenue_mtd,
    expenses_mtd,
    ar_total,
    ap_total,
    recent_invoices,
    unmatched_bank_count,
    pending_documents_count,
}) {
    return (
        <AuthenticatedLayout header="Tableau de bord">
            <Head title="Tableau de bord" />

            <div className="space-y-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 className="text-xl font-semibold text-gray-900">
                            Vue d’ensemble
                        </h2>
                        <p className="text-sm text-gray-500">
                            Indicateurs clés de votre activité
                        </p>
                    </div>

                    <Link
                        href="/invoices/create"
                        className="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-indigo-700"
                    >
                        Nouvelle facture
                    </Link>
                </div>

                <div className="grid grid-cols-2 gap-4 xl:grid-cols-4">
                    <StatCard
                        label="CA du mois"
                        value={revenue_mtd}
                        icon={BadgeEuro}
                        color="teal"
                    />

                    <StatCard
                        label="Dépenses MTD"
                        value={expenses_mtd}
                        icon={Receipt}
                        color="amber"
                    />

                    <StatCard
                        label="Créances clients"
                        value={ar_total}
                        icon={FileText}
                        color="blue"
                    />

                    <StatCard
                        label="Dettes fournisseurs"
                        value={ap_total}
                        icon={Wallet}
                        color="gray"
                    />
                </div>

                <div className="space-y-3">
                    {unmatched_bank_count > 0 && (
                        <Alert variant="warning">
                            <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    {unmatched_bank_count} opération(s) bancaire(s) non rapprochée(s)
                                </div>
                                <Link
                                    href="/bank/reconcile"
                                    className="inline-flex items-center gap-2 text-sm font-medium text-amber-900 underline"
                                >
                                    <ArrowLeftRight className="h-4 w-4" />
                                    Aller au rapprochement
                                </Link>
                            </div>
                        </Alert>
                    )}

                    {pending_documents_count > 0 && (
                        <Alert variant="info">
                            {pending_documents_count} document(s) en cours de traitement
                        </Alert>
                    )}
                </div>

                <div className="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                    <div className="border-b border-gray-200 px-5 py-4">
                        <h3 className="text-base font-semibold text-gray-900">
                            Factures récentes
                        </h3>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                                        N° Facture
                                    </th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                                        Client
                                    </th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                                        Date
                                    </th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                                        Total TTC
                                    </th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                                        Statut
                                    </th>
                                </tr>
                            </thead>

                            <tbody className="divide-y divide-gray-100 bg-white">
                                {recent_invoices?.length > 0 ? (
                                    recent_invoices.map((invoice) => (
                                        <tr key={invoice.id} className="hover:bg-gray-50">
                                            <td className="px-4 py-3 text-sm font-medium text-gray-900">
                                                <Link
                                                    href={`/invoices/${invoice.id}`}
                                                    className="hover:text-indigo-600"
                                                >
                                                    {invoice.invoice_number ?? 'Brouillon'}
                                                </Link>
                                            </td>

                                            <td className="px-4 py-3 text-sm text-gray-700">
                                                {invoice.contact?.display_name ?? 'Client non renseigné'}
                                            </td>

                                            <td className="px-4 py-3 text-sm text-gray-700">
                                                {formatDate(invoice.issue_date)}
                                            </td>

                                            <td className="px-4 py-3 text-sm text-gray-700">
                                                {formatCurrency(invoice.total_ttc)}
                                            </td>

                                            <td className="px-4 py-3 text-sm text-gray-700">
                                                <Badge status={invoice.status} />
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td
                                            colSpan={5}
                                            className="px-4 py-10 text-center text-sm text-gray-500"
                                        >
                                            Aucune facture récente
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