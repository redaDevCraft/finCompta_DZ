import { Head, useForm } from '@inertiajs/react';
import { Building2, CheckCircle2, Sparkles } from 'lucide-react';

export default function Company({ plans = [], trialDays = 3, presetPlan, presetCycle }) {
    const { data, setData, post, processing, errors } = useForm({
        raison_sociale: '',
        forme_juridique: 'SARL',
        nif: '',
        nis: '',
        rc: '',
        ai: '',
        address_line1: '',
        address_wilaya: '',
        tax_regime: 'IBS',
        vat_registered: true,
        fiscal_year_end: 12,
        currency: 'DZD',
        plan_code: presetPlan ?? (plans[0]?.code ?? ''),
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('onboarding.company.store'));
    };

    return (
        <>
            <Head title="Créer votre entreprise — FinCompta DZ" />
            <div className="min-h-screen bg-slate-50 py-10">
                <div className="mx-auto max-w-5xl px-6">
                    <div className="mb-8 text-center">
                        <div className="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-100 text-indigo-700">
                            <Building2 className="h-7 w-7" />
                        </div>
                        <h1 className="text-3xl font-bold text-slate-900">Créez votre entreprise</h1>
                        <p className="mt-2 text-sm text-slate-600">
                            Dernière étape avant votre <strong>{trialDays} jours d’essai gratuit</strong>.
                        </p>
                    </div>

                    <form onSubmit={submit} className="grid gap-6 lg:grid-cols-3">
                        <div className="space-y-6 lg:col-span-2">
                            <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                                <h2 className="text-sm font-semibold uppercase tracking-wide text-slate-500">
                                    Identification légale
                                </h2>

                                <div className="mt-4 grid gap-4 sm:grid-cols-2">
                                    <Field label="Raison sociale *" error={errors.raison_sociale}>
                                        <input
                                            type="text"
                                            value={data.raison_sociale}
                                            onChange={(e) => setData('raison_sociale', e.target.value)}
                                            required
                                            className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                        />
                                    </Field>

                                    <Field label="Forme juridique *" error={errors.forme_juridique}>
                                        <select
                                            value={data.forme_juridique}
                                            onChange={(e) => setData('forme_juridique', e.target.value)}
                                            className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                        >
                                            {['SARL', 'EURL', 'SPA', 'SNC', 'EI', 'SNCA'].map((f) => (
                                                <option key={f} value={f}>{f}</option>
                                            ))}
                                        </select>
                                    </Field>

                                    <Field label="NIF *" error={errors.nif}>
                                        <input value={data.nif} onChange={(e) => setData('nif', e.target.value)} required className="w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm" />
                                    </Field>
                                    <Field label="NIS *" error={errors.nis}>
                                        <input value={data.nis} onChange={(e) => setData('nis', e.target.value)} required className="w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm" />
                                    </Field>
                                    <Field label="RC *" error={errors.rc}>
                                        <input value={data.rc} onChange={(e) => setData('rc', e.target.value)} required className="w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm" />
                                    </Field>
                                    <Field label="Article d’imposition (AI)" error={errors.ai}>
                                        <input value={data.ai} onChange={(e) => setData('ai', e.target.value)} className="w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm" />
                                    </Field>
                                </div>
                            </div>

                            <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                                <h2 className="text-sm font-semibold uppercase tracking-wide text-slate-500">
                                    Adresse
                                </h2>
                                <div className="mt-4 grid gap-4 sm:grid-cols-2">
                                    <div className="sm:col-span-2">
                                        <Field label="Adresse *" error={errors.address_line1}>
                                            <input value={data.address_line1} onChange={(e) => setData('address_line1', e.target.value)} required className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                                        </Field>
                                    </div>
                                    <Field label="Wilaya *" error={errors.address_wilaya}>
                                        <input value={data.address_wilaya} onChange={(e) => setData('address_wilaya', e.target.value)} required className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                                    </Field>
                                </div>
                            </div>

                            <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                                <h2 className="text-sm font-semibold uppercase tracking-wide text-slate-500">
                                    Fiscalité
                                </h2>
                                <div className="mt-4 grid gap-4 sm:grid-cols-2">
                                    <Field label="Régime fiscal *" error={errors.tax_regime}>
                                        <select
                                            value={data.tax_regime}
                                            onChange={(e) => setData('tax_regime', e.target.value)}
                                            className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                        >
                                            <option value="IBS">IBS — Impôt sur les bénéfices</option>
                                            <option value="IRG">IRG — Impôt sur le revenu global</option>
                                            <option value="IFU">IFU — Impôt forfaitaire unique</option>
                                        </select>
                                    </Field>

                                    <Field label="Assujettissement TVA *" error={errors.vat_registered}>
                                        <select
                                            value={data.vat_registered ? '1' : '0'}
                                            onChange={(e) => setData('vat_registered', e.target.value === '1')}
                                            className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                        >
                                            <option value="1">Oui — assujetti TVA</option>
                                            <option value="0">Non</option>
                                        </select>
                                    </Field>

                                    <Field label="Fin d’exercice (mois) *" error={errors.fiscal_year_end}>
                                        <select
                                            value={data.fiscal_year_end}
                                            onChange={(e) => setData('fiscal_year_end', parseInt(e.target.value))}
                                            className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                        >
                                            {Array.from({ length: 12 }, (_, i) => i + 1).map((m) => (
                                                <option key={m} value={m}>{m}</option>
                                            ))}
                                        </select>
                                    </Field>

                                    <Field label="Devise *" error={errors.currency}>
                                        <select
                                            value={data.currency}
                                            onChange={(e) => setData('currency', e.target.value)}
                                            className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                        >
                                            <option value="DZD">DZD — Dinar algérien</option>
                                        </select>
                                    </Field>
                                </div>
                            </div>
                        </div>

                        <div className="space-y-6">
                            <div className="rounded-2xl border border-indigo-200 bg-indigo-50 p-6">
                                <div className="flex items-center gap-2 text-indigo-700">
                                    <Sparkles className="h-5 w-5" />
                                    <span className="font-semibold">Essai gratuit</span>
                                </div>
                                <p className="mt-2 text-sm text-indigo-900">
                                    Vous disposez de <strong>{trialDays} jours</strong> pour tout tester.
                                    Vous choisirez votre mode de paiement à la fin de l’essai (Edahabia, CIB, ou bon de commande).
                                </p>
                            </div>

                            <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                                <h2 className="text-sm font-semibold uppercase tracking-wide text-slate-500">Plan souhaité</h2>
                                <div className="mt-3 space-y-2">
                                    {plans.map((plan) => (
                                        <button
                                            type="button"
                                            key={plan.id}
                                            onClick={() => setData('plan_code', plan.code)}
                                            className={[
                                                'w-full rounded-xl border p-3 text-left transition',
                                                data.plan_code === plan.code
                                                    ? 'border-indigo-500 bg-indigo-50 ring-1 ring-indigo-400'
                                                    : 'border-slate-200 bg-white hover:bg-slate-50',
                                            ].join(' ')}
                                        >
                                            <div className="flex items-center justify-between">
                                                <div>
                                                    <div className="font-medium text-slate-900">{plan.name}</div>
                                                    <div className="text-xs text-slate-500">{plan.tagline}</div>
                                                </div>
                                                {data.plan_code === plan.code && (
                                                    <CheckCircle2 className="h-5 w-5 text-indigo-600" />
                                                )}
                                            </div>
                                        </button>
                                    ))}
                                </div>
                            </div>

                            <button
                                type="submit"
                                disabled={processing}
                                className="w-full rounded-xl bg-indigo-600 px-5 py-3 text-sm font-medium text-white transition hover:bg-indigo-700 disabled:opacity-60"
                            >
                                {processing ? 'Création...' : 'Créer mon entreprise et démarrer l’essai'}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </>
    );
}

function Field({ label, error, children }) {
    return (
        <div>
            <label className="mb-1 block text-sm font-medium text-slate-700">{label}</label>
            {children}
            {error && <p className="mt-1 text-xs text-rose-600">{error}</p>}
        </div>
    );
}
