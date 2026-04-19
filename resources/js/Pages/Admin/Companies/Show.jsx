import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

function fmtDzd(n) {
    return (Number(n) || 0).toLocaleString('fr-DZ');
}

const STATUS_STYLES = {
    pending: 'bg-amber-50 text-amber-800',
    processing: 'bg-blue-50 text-blue-800',
    paid: 'bg-emerald-50 text-emerald-800',
    failed: 'bg-rose-50 text-rose-800',
    refunded: 'bg-slate-100 text-slate-600',
};

export default function CompaniesShow({ company, payments, stats }) {
    const sub = company.subscription;

    return (
        <AdminLayout header={company.raison_sociale || 'Société'}>
            <Head title={`Admin — ${company.raison_sociale || 'Société'}`} />

            <div className="mx-auto max-w-6xl space-y-6">
                <Link
                    href={route('admin.companies.index')}
                    className="inline-flex items-center gap-1 text-sm text-slate-600 hover:text-slate-900"
                >
                    <ArrowLeft className="h-4 w-4" /> Retour aux sociétés
                </Link>

                <div className="grid gap-4 md:grid-cols-4">
                    <Stat label="Utilisateurs" value={company.users?.length ?? 0} />
                    <Stat label="Factures" value={stats.invoices_count} />
                    <Stat label="Dépenses" value={stats.expenses_count} />
                    <Stat
                        label="Paiements confirmés"
                        value={`${fmtDzd(stats.payments_paid_dzd)} DZD`}
                    />
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 className="mb-3 text-sm font-semibold text-slate-900">Informations</h2>
                        <dl className="space-y-2 text-sm">
                            <Row label="Forme juridique" value={company.forme_juridique} />
                            <Row label="NIF" value={company.nif} mono />
                            <Row label="NIS" value={company.nis} mono />
                            <Row label="RC" value={company.rc} mono />
                            <Row label="AI" value={company.ai} mono />
                            <Row label="Régime fiscal" value={company.tax_regime} />
                            <Row label="TVA" value={company.vat_registered ? 'Assujetti' : 'Non assujetti'} />
                            <Row label="Wilaya" value={company.address_wilaya} />
                            <Row label="Adresse" value={company.address_line1} />
                        </dl>
                    </section>

                    <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 className="mb-3 text-sm font-semibold text-slate-900">Abonnement</h2>
                        {sub ? (
                            <dl className="space-y-2 text-sm">
                                <Row label="Plan" value={sub.plan?.name ?? '—'} />
                                <Row label="Statut" value={sub.status} />
                                <Row label="Cycle" value={sub.billing_cycle} />
                                <Row
                                    label="Essai jusqu'au"
                                    value={fmtDate(sub.trial_ends_at)}
                                />
                                <Row
                                    label="Période en cours"
                                    value={`${fmtDate(sub.current_period_started_at)} → ${fmtDate(sub.current_period_ends_at)}`}
                                />
                                <Row label="Résiliation prévue" value={fmtDate(sub.cancel_at)} />
                            </dl>
                        ) : (
                            <p className="text-sm text-slate-500">Aucun abonnement.</p>
                        )}
                    </section>
                </div>

                <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 className="mb-3 text-sm font-semibold text-slate-900">Utilisateurs rattachés</h2>
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-200 text-sm">
                            <thead className="bg-slate-50 text-xs uppercase text-slate-500">
                                <tr>
                                    <th className="px-3 py-2 text-left font-semibold">Nom</th>
                                    <th className="px-3 py-2 text-left font-semibold">Email</th>
                                    <th className="px-3 py-2 text-left font-semibold">Rôle</th>
                                    <th className="px-3 py-2 text-left font-semibold">Révoqué</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {(company.users ?? []).length === 0 && (
                                    <tr>
                                        <td colSpan={4} className="py-6 text-center text-slate-400">
                                            Aucun utilisateur.
                                        </td>
                                    </tr>
                                )}
                                {(company.users ?? []).map((u) => (
                                    <tr key={u.id}>
                                        <td className="px-3 py-2">{u.name}</td>
                                        <td className="px-3 py-2 text-slate-600">{u.email}</td>
                                        <td className="px-3 py-2 text-xs">{u.pivot?.role ?? '—'}</td>
                                        <td className="px-3 py-2 text-xs text-slate-500">
                                            {u.pivot?.revoked_at ? fmtDate(u.pivot.revoked_at) : '—'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>

                <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 className="mb-3 text-sm font-semibold text-slate-900">Derniers paiements</h2>
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-200 text-sm">
                            <thead className="bg-slate-50 text-xs uppercase text-slate-500">
                                <tr>
                                    <th className="px-3 py-2 text-left font-semibold">Date</th>
                                    <th className="px-3 py-2 text-left font-semibold">Réf.</th>
                                    <th className="px-3 py-2 text-left font-semibold">Plan</th>
                                    <th className="px-3 py-2 text-left font-semibold">Statut</th>
                                    <th className="px-3 py-2 text-right font-semibold">Montant</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {payments.length === 0 && (
                                    <tr>
                                        <td colSpan={5} className="py-6 text-center text-slate-400">
                                            Aucun paiement.
                                        </td>
                                    </tr>
                                )}
                                {payments.map((p) => (
                                    <tr key={p.id}>
                                        <td className="px-3 py-2 text-xs">{fmtDate(p.created_at)}</td>
                                        <td className="px-3 py-2 font-mono text-xs">{p.reference}</td>
                                        <td className="px-3 py-2">{p.plan?.name ?? '—'}</td>
                                        <td className="px-3 py-2">
                                            <span
                                                className={`rounded-full px-2 py-0.5 text-xs ${STATUS_STYLES[p.status] || 'bg-slate-100'}`}
                                            >
                                                {p.status}
                                            </span>
                                        </td>
                                        <td className="px-3 py-2 text-right tabular-nums">
                                            {fmtDzd(p.amount_dzd)} {p.currency}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </AdminLayout>
    );
}

function Row({ label, value, mono = false }) {
    return (
        <div className="flex items-start justify-between gap-4">
            <dt className="text-xs uppercase text-slate-500">{label}</dt>
            <dd className={`text-right text-slate-900 ${mono ? 'font-mono text-xs' : ''}`}>
                {value || '—'}
            </dd>
        </div>
    );
}

function Stat({ label, value }) {
    return (
        <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div className="text-xs uppercase text-slate-500">{label}</div>
            <div className="mt-1 text-2xl font-semibold tabular-nums text-slate-900">{value}</div>
        </div>
    );
}

function fmtDate(d) {
    if (!d) return null;
    try {
        return new Date(d).toLocaleDateString('fr-DZ');
    } catch {
        return d;
    }
}
