import { Head, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import InputError from '@/Components/InputError';
import { CreditCard, FileText, Shield } from 'lucide-react';

function formatDzd(n) {
    return new Intl.NumberFormat('fr-DZ').format(n) + ' DZD';
}

export default function Checkout({ plan, cycle, amount_dzd, subscription, chargily_ready }) {
    const [method, setMethod] = useState('edahabia');
    const { csrf_token, errors } = usePage().props;

    const bon = useForm({
        plan_code: plan.code,
        cycle,
    });

    const payBon = (e) => {
        e.preventDefault();
        bon.post(route('billing.bon.start'));
    };

    return (
        <AuthenticatedLayout header="Finaliser votre abonnement">
            <Head title="Paiement" />

            <div className="mx-auto grid max-w-5xl gap-6 lg:grid-cols-3">
                <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-1">
                    <h2 className="text-lg font-semibold">{plan.name}</h2>
                    <p className="mt-1 text-sm text-slate-500">{plan.tagline}</p>
                    <div className="mt-4 text-3xl font-bold">{formatDzd(amount_dzd)}</div>
                    <div className="text-sm text-slate-500">
                        Facturation {cycle === 'yearly' ? 'annuelle' : 'mensuelle'}
                    </div>

                    {subscription?.is_on_trial && (
                        <div className="mt-4 rounded-lg bg-indigo-50 p-3 text-xs text-indigo-800">
                            Essai en cours — jours restants: <strong>{subscription.days_remaining}</strong>.
                            Votre abonnement démarrera à la fin de l’essai.
                        </div>
                    )}
                </div>

                <div className="space-y-6 lg:col-span-2">
                    {/* Chargily — classic POST so the browser follows redirects to Chargily (Inertia XHR cannot). */}
                    <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div className="flex items-start justify-between">
                            <div>
                                <h3 className="flex items-center gap-2 text-base font-semibold">
                                    <CreditCard className="h-5 w-5 text-indigo-600" /> Carte bancaire (Edahabia / CIB)
                                </h3>
                                <p className="mt-1 text-sm text-slate-500">
                                    Paiement sécurisé via Chargily — activation immédiate après paiement.
                                </p>
                            </div>
                            <span className="flex items-center gap-1 text-xs text-emerald-700">
                                <Shield className="h-3 w-3" /> Mode test
                            </span>
                        </div>

                        <form method="post" action={route('billing.chargily.start')} className="mt-4">
                            <input type="hidden" name="_token" value={csrf_token} />
                            <input type="hidden" name="plan_code" value={plan.code} />
                            <input type="hidden" name="cycle" value={cycle} />
                            <input type="hidden" name="method" value={method} />

                            <div className="grid gap-3 sm:grid-cols-2">
                                <button
                                    type="button"
                                    onClick={() => setMethod('edahabia')}
                                    className={[
                                        'rounded-xl border px-4 py-3 text-left transition',
                                        method === 'edahabia' ? 'border-indigo-500 bg-indigo-50 ring-1 ring-indigo-400' : 'border-slate-200 bg-white hover:bg-slate-50',
                                    ].join(' ')}
                                >
                                    <div className="text-sm font-semibold">Edahabia</div>
                                    <div className="text-xs text-slate-500">Carte Algérie Poste</div>
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setMethod('cib')}
                                    className={[
                                        'rounded-xl border px-4 py-3 text-left transition',
                                        method === 'cib' ? 'border-indigo-500 bg-indigo-50 ring-1 ring-indigo-400' : 'border-slate-200 bg-white hover:bg-slate-50',
                                    ].join(' ')}
                                >
                                    <div className="text-sm font-semibold">CIB</div>
                                    <div className="text-xs text-slate-500">Carte interbancaire</div>
                                </button>
                            </div>

                            <button
                                type="submit"
                                disabled={!chargily_ready}
                                className="mt-4 w-full rounded-xl bg-indigo-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-indigo-700 disabled:opacity-50"
                            >
                                {`Payer ${formatDzd(amount_dzd)}`}
                            </button>

                            <InputError message={errors.chargily} className="mt-3" />
                            <InputError message={errors.plan_code} className="mt-1" />
                            <InputError message={errors.cycle} className="mt-1" />
                            <InputError message={errors.method} className="mt-1" />

                            {!chargily_ready && (
                                <p className="mt-3 text-xs text-amber-700">
                                    Chargily non configuré — utilisez le bon de commande ci-dessous.
                                </p>
                            )}
                        </form>
                    </div>

                    {/* Bon de commande */}
                    <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h3 className="flex items-center gap-2 text-base font-semibold">
                            <FileText className="h-5 w-5 text-slate-700" /> Bon de commande (virement bancaire)
                        </h3>
                        <p className="mt-1 text-sm text-slate-500">
                            Générez un bon de commande PDF, effectuez le virement, déposez le justificatif — activation sous 24h.
                        </p>
                        <form onSubmit={payBon} className="mt-4">
                            <button
                                type="submit"
                                disabled={bon.processing}
                                className="w-full rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-800 transition hover:bg-slate-50 disabled:opacity-50"
                            >
                                {bon.processing ? 'Génération…' : 'Générer un bon de commande'}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
