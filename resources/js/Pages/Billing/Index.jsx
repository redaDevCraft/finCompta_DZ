import { Head, Link } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { CreditCard, FileText, Sparkles } from 'lucide-react';

function formatDzd(n) {
    return new Intl.NumberFormat('fr-DZ').format(n) + ' DZD';
}

const STATUS_BADGE = {
    trialing:  { label: 'Essai gratuit', cls: 'bg-indigo-100 text-indigo-700' },
    active:    { label: 'Actif',         cls: 'bg-emerald-100 text-emerald-700' },
    past_due:  { label: 'Impayé',        cls: 'bg-amber-100 text-amber-700' },
    canceled:  { label: 'Résilié',       cls: 'bg-slate-200 text-slate-700' },
    incomplete:{ label: 'Incomplet',     cls: 'bg-rose-100 text-rose-700' },
};

const PAY_BADGE = {
    pending:    { label: 'En attente',   cls: 'bg-amber-100 text-amber-700' },
    processing: { label: 'En cours',     cls: 'bg-blue-100 text-blue-700' },
    paid:       { label: 'Réglé',        cls: 'bg-emerald-100 text-emerald-700' },
    failed:     { label: 'Échoué',       cls: 'bg-rose-100 text-rose-700' },
    canceled:   { label: 'Annulé',       cls: 'bg-slate-200 text-slate-700' },
    expired:    { label: 'Expiré',       cls: 'bg-slate-200 text-slate-700' },
};

export default function BillingIndex({ subscription, plans = [], payments = [], chargily_ready }) {
    return (
        <AuthenticatedLayout header="Facturation">
            <Head title="Facturation" />

            <div className="space-y-8">
                {/* ── Subscription card ─────────────────────────── */}
                <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 className="text-lg font-semibold">Votre abonnement</h2>
                            <p className="text-sm text-slate-500">
                                {subscription?.plan?.name
                                    ? <>Plan <strong>{subscription.plan.name}</strong> · facturation {subscription.billing_cycle === 'yearly' ? 'annuelle' : 'mensuelle'}</>
                                    : 'Aucun plan sélectionné'
                                }
                            </p>
                        </div>
                        {subscription?.status && (() => {
                            const s = STATUS_BADGE[subscription.status] ?? STATUS_BADGE.incomplete;
                            return (
                                <span className={`rounded-full px-3 py-1 text-xs font-semibold ${s.cls}`}>
                                    {s.label}
                                </span>
                            );
                        })()}
                    </div>

                    <div className="mt-4 grid gap-4 sm:grid-cols-3">
                        <InfoCard
                            icon={Sparkles}
                            label={subscription?.is_on_trial ? 'Fin de l’essai' : 'Fin de période'}
                            value={
                                subscription?.is_on_trial
                                    ? (subscription.trial_ends_at ? new Date(subscription.trial_ends_at).toLocaleDateString('fr-FR') : '—')
                                    : (subscription?.current_period_ends_at ? new Date(subscription.current_period_ends_at).toLocaleDateString('fr-FR') : '—')
                            }
                        />
                        <InfoCard
                            icon={CreditCard}
                            label="Jours restants"
                            value={subscription?.days_remaining ?? 0}
                        />
                        <InfoCard
                            icon={FileText}
                            label="Dernier mode de paiement"
                            value={subscription?.last_payment_method ?? '—'}
                        />
                    </div>
                </div>

                {/* ── Plan selector ─────────────────────────────── */}
                <div>
                    <h2 className="text-lg font-semibold">Choisir un plan</h2>
                    <div className="mt-4 grid gap-4 md:grid-cols-3">
                        {plans.map((plan) => (
                            <div key={plan.id} className="flex flex-col rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                                <h3 className="text-base font-semibold">{plan.name}</h3>
                                <p className="text-xs text-slate-500">{plan.tagline}</p>

                                <div className="mt-4 grid gap-2">
                                    <Link
                                        href={`/billing/checkout?plan=${plan.code}&cycle=monthly`}
                                        className="flex items-center justify-between rounded-lg border border-slate-300 px-3 py-2 text-sm hover:border-indigo-500 hover:bg-indigo-50"
                                    >
                                        <span>Mensuel</span>
                                        <span className="font-semibold">{formatDzd(plan.monthly_price_dzd)}</span>
                                    </Link>
                                    <Link
                                        href={`/billing/checkout?plan=${plan.code}&cycle=yearly`}
                                        className="flex items-center justify-between rounded-lg border border-slate-300 px-3 py-2 text-sm hover:border-indigo-500 hover:bg-indigo-50"
                                    >
                                        <span>Annuel</span>
                                        <span className="font-semibold">{formatDzd(plan.yearly_price_dzd)}</span>
                                    </Link>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>

                {/* ── Payment history ───────────────────────────── */}
                <div>
                    <h2 className="text-lg font-semibold">Historique des paiements</h2>
                    <div className="mt-3 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <table className="min-w-full divide-y divide-slate-200 text-sm">
                            <thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th className="px-4 py-3 text-left">Date</th>
                                    <th className="px-4 py-3 text-left">Référence</th>
                                    <th className="px-4 py-3 text-left">Plan</th>
                                    <th className="px-4 py-3 text-left">Mode</th>
                                    <th className="px-4 py-3 text-right">Montant</th>
                                    <th className="px-4 py-3 text-center">Statut</th>
                                    <th className="px-4 py-3 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {payments?.length ? payments.map((p) => {
                                    const s = PAY_BADGE[p.status] ?? { label: p.status, cls: 'bg-slate-100 text-slate-700' };
                                    return (
                                        <tr key={p.id} className="hover:bg-slate-50">
                                            <td className="px-4 py-3 text-slate-700">{new Date(p.created_at).toLocaleDateString('fr-FR')}</td>
                                            <td className="px-4 py-3 font-mono text-xs">{p.reference}</td>
                                            <td className="px-4 py-3">{p.plan?.name ?? '—'}</td>
                                            <td className="px-4 py-3">
                                                {p.gateway === 'bon_de_commande' ? 'Bon de commande' : p.method ?? p.gateway}
                                            </td>
                                            <td className="px-4 py-3 text-right font-semibold">{formatDzd(p.amount_dzd)}</td>
                                            <td className="px-4 py-3 text-center">
                                                <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${s.cls}`}>{s.label}</span>
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                {p.gateway === 'bon_de_commande' ? (
                                                    <Link
                                                        href={route('billing.bon.show', p.id)}
                                                        className="text-xs text-indigo-600 hover:underline"
                                                    >
                                                        Gérer
                                                    </Link>
                                                ) : p.checkout_url && p.status === 'processing' ? (
                                                    <a href={p.checkout_url} target="_blank" rel="noreferrer" className="text-xs text-indigo-600 hover:underline">
                                                        Poursuivre
                                                    </a>
                                                ) : '—'}
                                            </td>
                                        </tr>
                                    );
                                }) : (
                                    <tr>
                                        <td colSpan={7} className="px-4 py-10 text-center text-sm text-slate-400">
                                            Aucun paiement pour le moment.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                {!chargily_ready && (
                    <div className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        Le paiement Chargily n’est pas encore configuré. Renseignez les clés <code>CHARGILY_*</code> dans <code>.env</code> pour activer Edahabia/CIB.
                        Le paiement par bon de commande reste disponible.
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}

function InfoCard({ icon: Icon, label, value }) {
    return (
        <div className="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
            <div className="flex items-center gap-2 text-xs text-slate-500">
                <Icon className="h-4 w-4" />
                {label}
            </div>
            <div className="mt-1 text-base font-semibold text-slate-900">{value}</div>
        </div>
    );
}
