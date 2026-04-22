import AdminLayout from '@/Layouts/AdminLayout';
import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Check, X } from 'lucide-react';
import { useNotification } from '@/Context/NotificationContext';

export default function AdminPaymentsIndex({ payments }) {
    const { props } = usePage();
    const errors = props.errors ?? {};
    const { confirm } = useNotification();

    const [rejectReason, setRejectReason] = useState('');

    async function confirmPayment(id) {
        const ok = await confirm({
            title: 'Confirmer le paiement',
            message: 'Marquer ce paiement comme payé et activer / prolonger l’abonnement ?',
            confirmLabel: 'Oui, confirmer',
        });
        if (!ok) return;
        router.post(route('admin.payments.confirm', id), {}, { preserveScroll: true });
    }

    async function rejectPayment(id) {
        const ok = await confirm({
            title: 'Rejeter le paiement',
            message: 'Voulez-vous vraiment rejeter ce paiement ?',
            confirmLabel: 'Oui, rejeter',
        });
        if (!ok) return;
        router.post(
            route('admin.payments.reject', id),
            { reason: rejectReason },
            { preserveScroll: true, onSuccess: () => setRejectReason('') },
        );
    }

    return (
        <AdminLayout header="Paiements à confirmer">
            <Head title="Admin — Paiements" />

            <div className="mx-auto max-w-6xl space-y-4">
                {errors.payment && (
                    <div className="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                        {errors.payment}
                    </div>
                )}

                <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-100 px-6 py-4">
                        <p className="text-sm text-slate-600">
                            Bons de commande, virements et autres paiements en attente de validation manuelle.
                        </p>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-200 text-sm">
                            <thead className="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                                <tr>
                                    <th className="px-4 py-3">Réf.</th>
                                    <th className="px-4 py-3">Société</th>
                                    <th className="px-4 py-3">Plan</th>
                                    <th className="px-4 py-3">Passerelle</th>
                                    <th className="px-4 py-3">Statut</th>
                                    <th className="px-4 py-3">Validation</th>
                                    <th className="px-4 py-3 text-right">Montant</th>
                                    <th className="px-4 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {payments.length === 0 && (
                                    <tr>
                                        <td colSpan={8} className="px-6 py-10 text-center text-slate-500">
                                            Aucun paiement en attente.
                                        </td>
                                    </tr>
                                )}
                                {payments.map((p) => (
                                    <tr key={p.id} className="align-top hover:bg-slate-50/80">
                                        <td className="px-4 py-3 font-mono text-xs text-slate-800">{p.reference}</td>
                                        <td className="px-4 py-3 text-slate-700">{p.company?.raison_sociale ?? '—'}</td>
                                        <td className="px-4 py-3 text-slate-700">{p.plan?.name ?? '—'}</td>
                                        <td className="px-4 py-3">
                                            <span className="text-xs text-slate-600">
                                                {p.gateway} / {p.method}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3">
                                            <span className="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-900">
                                                {p.status}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-xs text-slate-600">
                                            {p.approval_status ?? 'none'}
                                            {p.gateway === 'bon_de_commande' && !p.proof_upload_path && (
                                                <div className="text-rose-700">Justificatif manquant</div>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-right font-medium tabular-nums text-slate-900">
                                            {(p.amount_dzd ?? 0).toLocaleString('fr-DZ')} {p.currency}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <div className="flex flex-wrap justify-end gap-2">
                                                <button
                                                    type="button"
                                                    onClick={() => confirmPayment(p.id)}
                                                    className="inline-flex items-center gap-1 rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700"
                                                >
                                                    <Check className="h-3.5 w-3.5" />
                                                    Confirmer
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() => rejectPayment(p.id)}
                                                    className="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                                >
                                                    <X className="h-3.5 w-3.5" />
                                                    Rejeter
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                <p className="text-xs text-slate-500">
                    Motif de rejet (optionnel) — utilisé pour le prochain clic sur « Rejeter ».
                </p>
                <textarea
                    className="w-full max-w-xl rounded-lg border border-slate-200 px-3 py-2 text-sm"
                    rows={2}
                    placeholder="Motif de rejet (optionnel)…"
                    value={rejectReason}
                    onChange={(e) => setRejectReason(e.target.value)}
                />
            </div>
        </AdminLayout>
    );
}
