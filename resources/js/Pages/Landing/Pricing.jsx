import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { ArrowLeft, CheckCircle2, Sparkles } from 'lucide-react';

function formatDzd(n) {
    return new Intl.NumberFormat('fr-DZ').format(n) + ' DZD';
}

export default function Pricing({ plans = [], trialDays = 3 }) {
    const [cycle, setCycle] = useState('monthly');

    return (
        <>
            <Head title="Tarifs — FinCompta DZ" />
            <div className="min-h-screen bg-slate-50 py-16">
                <div className="mx-auto max-w-6xl px-6">
                    <Link href="/" className="inline-flex items-center gap-2 text-sm text-slate-600 hover:text-slate-900">
                        <ArrowLeft className="h-4 w-4" /> Accueil
                    </Link>

                    <h1 className="mt-6 text-center text-4xl font-bold text-slate-900">Tarifs FinCompta DZ</h1>
                    <p className="mx-auto mt-3 max-w-2xl text-center text-slate-600">
                        {trialDays} jours d’essai gratuit sur tous les plans. Sans carte bancaire.
                    </p>

                    <div className="mt-8 flex justify-center">
                        <div className="inline-flex rounded-full border border-slate-200 bg-white p-1 text-sm">
                            {['monthly', 'yearly'].map((c) => (
                                <button
                                    key={c}
                                    type="button"
                                    onClick={() => setCycle(c)}
                                    className={`rounded-full px-4 py-1.5 transition ${
                                        cycle === c ? 'bg-indigo-600 text-white' : 'text-slate-600'
                                    }`}
                                >
                                    {c === 'monthly' ? 'Mensuel' : 'Annuel'}
                                </button>
                            ))}
                        </div>
                    </div>

                    <div className="mt-10 grid gap-6 md:grid-cols-3">
                        {plans.map((plan, idx) => {
                            const price = cycle === 'yearly' ? plan.yearly_price_dzd : plan.monthly_price_dzd;
                            const featured = idx === 1;

                            return (
                                <div
                                    key={plan.id}
                                    className={[
                                        'flex flex-col rounded-2xl border bg-white p-6 shadow-sm',
                                        featured ? 'border-indigo-500 ring-2 ring-indigo-200' : 'border-slate-200',
                                    ].join(' ')}
                                >
                                    {featured && (
                                        <div className="mb-3 inline-flex w-fit items-center gap-1 rounded-full bg-indigo-100 px-2.5 py-0.5 text-xs font-semibold text-indigo-700">
                                            <Sparkles className="h-3 w-3" /> Le plus populaire
                                        </div>
                                    )}
                                    <h3 className="text-xl font-semibold">{plan.name}</h3>
                                    <p className="mt-1 text-sm text-slate-500">{plan.tagline}</p>
                                    <div className="mt-6 text-4xl font-bold">{formatDzd(price)}</div>
                                    <div className="text-sm text-slate-500">{cycle === 'yearly' ? '/an' : '/mois'}</div>

                                    <ul className="mt-6 flex-1 space-y-2">
                                        {(plan.features ?? []).map((f) => (
                                            <li key={f} className="flex items-start gap-2 text-sm text-slate-700">
                                                <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0 text-emerald-500" />
                                                <span>{f}</span>
                                            </li>
                                        ))}
                                    </ul>

                                    <Link
                                        href={`/start-trial?plan=${plan.code}&cycle=${cycle}`}
                                        className={[
                                            'mt-8 inline-flex items-center justify-center rounded-xl px-4 py-2.5 text-sm font-medium transition',
                                            featured
                                                ? 'bg-indigo-600 text-white hover:bg-indigo-700'
                                                : 'border border-slate-300 bg-white hover:bg-slate-50',
                                        ].join(' ')}
                                    >
                                        Commencer l’essai
                                    </Link>
                                </div>
                            );
                        })}
                    </div>
                </div>
            </div>
        </>
    );
}
