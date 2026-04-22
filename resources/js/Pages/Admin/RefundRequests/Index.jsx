import AdminLayout from '@/Layouts/AdminLayout';
import { Head, router } from '@inertiajs/react';

export default function RefundRequestsIndex({ refundRequests = [] }) {
    const statuses = ['reviewing', 'approved', 'rejected', 'refunded'];

    function updateStatus(id, status) {
        router.patch(route('admin.refund-requests.update', id), { status }, { preserveScroll: true });
    }

    return (
        <AdminLayout header="Demandes de remboursement">
            <Head title="Admin — Remboursements" />

            <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <table className="min-w-full divide-y divide-slate-200 text-sm">
                    <thead className="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th className="px-4 py-3 text-left">Date</th>
                            <th className="px-4 py-3 text-left">Societe</th>
                            <th className="px-4 py-3 text-left">Paiement</th>
                            <th className="px-4 py-3 text-left">Statut</th>
                            <th className="px-4 py-3 text-left">Motif</th>
                            <th className="px-4 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {refundRequests.map((r) => (
                            <tr key={r.id}>
                                <td className="px-4 py-3">{new Date(r.created_at).toLocaleDateString('fr-FR')}</td>
                                <td className="px-4 py-3">{r.company?.raison_sociale ?? '—'}</td>
                                <td className="px-4 py-3">{r.payment?.reference ?? '—'}</td>
                                <td className="px-4 py-3">{r.status}</td>
                                <td className="px-4 py-3">{r.reason}</td>
                                <td className="px-4 py-3 text-right">
                                    <select
                                        className="rounded-md border border-slate-300 px-2 py-1"
                                        value={r.status}
                                        onChange={(e) => updateStatus(r.id, e.target.value)}
                                    >
                                        {[r.status, ...statuses.filter((s) => s !== r.status)].map((status) => (
                                            <option key={status} value={status}>{status}</option>
                                        ))}
                                    </select>
                                </td>
                            </tr>
                        ))}
                        {refundRequests.length === 0 && (
                            <tr><td colSpan={6} className="px-4 py-10 text-center text-slate-500">Aucune demande.</td></tr>
                        )}
                    </tbody>
                </table>
            </div>
        </AdminLayout>
    );
}
