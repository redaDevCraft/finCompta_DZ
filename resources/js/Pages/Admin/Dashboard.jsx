import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link } from '@inertiajs/react';
import { WalletCards } from 'lucide-react';

export default function AdminDashboard({ pendingPayments, recentPayments }) {
    return (
        <AdminLayout header="Tableau de bord administrateur">
            <Head title="Admin — Tableau de bord" />

            <div className="mx-auto max-w-5xl space-y-8">
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div className="text-sm font-medium text-slate-500">Paiements en attente / traitement</div>
                        <div className="mt-2 text-3xl font-bold text-slate-900">{pendingPayments}</div>
                        <Link
                            href="/admin/payments"
                            className="mt-4 inline-flex items-center gap-2 text-sm font-semibold text-amber-700 hover:text-amber-800"
                        >
                            <WalletCards className="h-4 w-4" />
                            Voir les paiements
                        </Link>
                    </div>
                </div>

                <div className="rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-100 px-6 py-4">
                        <h2 className="text-base font-semibold text-slate-900">Derniers paiements</h2>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-200 text-sm">
                            <thead className="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                                <tr>
                                    <th className="px-6 py-3">Réf.</th>
                                    <th className="px-6 py-3">Société</th>
                                    <th className="px-6 py-3">Statut</th>
                                    <th className="px-6 py-3">Montant</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {recentPayments.length === 0 && (
                                    <tr>
                                        <td colSpan={4} className="px-6 py-8 text-center text-slate-500">
                                            Aucun paiement récent.
                                        </td>
                                    </tr>
                                )}
                                {recentPayments.map((p) => (
                                    <tr key={p.id} className="hover:bg-slate-50/80">
                                        <td className="px-6 py-3 font-mono text-xs">{p.reference}</td>
                                        <td className="px-6 py-3 text-slate-700">{p.company?.raison_sociale ?? '—'}</td>
                                        <td className="px-6 py-3">
                                            <span className="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700">
                                                {p.status}
                                            </span>
                                        </td>
                                        <td className="px-6 py-3 font-medium tabular-nums">
                                            {p.amount_dzd?.toLocaleString?.('fr-DZ') ?? p.amount_dzd} {p.currency}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
