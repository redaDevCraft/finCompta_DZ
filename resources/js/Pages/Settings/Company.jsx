import { Head, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function Company({ company }) {
    const { data, setData, put, processing, errors } = useForm({
        raison_sociale: company.raison_sociale ?? '',
        forme_juridique: company.forme_juridique ?? '',
        nif: company.nif ?? '',
        nis: company.nis ?? '',
        rc: company.rc ?? '',
        ai: company.ai ?? '',
        address_line1: company.address_line1 ?? '',
        address_line2: company.address_line2 ?? '',
        address_wilaya: company.address_wilaya ?? '',
        address_postal_code: company.address_postal_code ?? '',
        tax_regime: company.tax_regime ?? '',
        vat_registered: !!company.vat_registered,
        currency: company.currency ?? 'DZD',
    });

    const missingCompliance = !data.nif || !data.nis || !data.rc;
    const nifWarning = data.nif && data.nif.length !== 15;
    const nisWarning = data.nis && data.nis.length !== 15;

    const submit = (e) => {
        e.preventDefault();
        put('/settings/company');
    };

    const inputClass =
        'mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200';

    return (
        <AuthenticatedLayout>
            <Head title="Paramètres société" />

            <div className="mx-auto max-w-4xl space-y-6">
                <div>
                    <h1 className="text-2xl font-semibold text-slate-900">Paramètres société</h1>
                    <p className="mt-1 text-sm text-slate-600">
                        Mettez à jour les informations légales et fiscales de l’entreprise.
                    </p>
                </div>

                {missingCompliance && (
                    <div className="rounded-2xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        Des informations fiscales manquantes empêcheront l'émission de factures
                    </div>
                )}

                <form onSubmit={submit} className="space-y-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div className="grid gap-4 md:grid-cols-2">
                        <Field label="Raison sociale" error={errors.raison_sociale}>
                            <input
                                className={inputClass}
                                value={data.raison_sociale}
                                onChange={(e) => setData('raison_sociale', e.target.value)}
                            />
                        </Field>

                        <Field label="Forme juridique" error={errors.forme_juridique}>
                            <select
                                className={inputClass}
                                value={data.forme_juridique}
                                onChange={(e) => setData('forme_juridique', e.target.value)}
                            >
                                <option value="">Sélectionner</option>
                                {['SARL', 'EURL', 'SPA', 'SNC', 'EI', 'SNCA'].map((option) => (
                                    <option key={option} value={option}>
                                        {option}
                                    </option>
                                ))}
                            </select>
                        </Field>

                        <Field
                            label="NIF"
                            requiredHint
                            error={errors.nif || (nifWarning ? 'Le NIF devrait contenir exactement 15 caractères.' : null)}
                        >
                            <input
                                className={`${inputClass} border-amber-300 bg-amber-50`}
                                value={data.nif}
                                onChange={(e) => setData('nif', e.target.value)}
                            />
                        </Field>

                        <Field
                            label="NIS"
                            requiredHint
                            error={errors.nis || (nisWarning ? 'Le NIS devrait contenir exactement 15 caractères.' : null)}
                        >
                            <input
                                className={`${inputClass} border-amber-300 bg-amber-50`}
                                value={data.nis}
                                onChange={(e) => setData('nis', e.target.value)}
                            />
                        </Field>

                        <Field label="RC" requiredHint error={errors.rc}>
                            <input
                                className={`${inputClass} border-amber-300 bg-amber-50`}
                                value={data.rc}
                                onChange={(e) => setData('rc', e.target.value)}
                            />
                        </Field>

                        <Field label="AI" error={errors.ai}>
                            <input
                                className={inputClass}
                                value={data.ai}
                                onChange={(e) => setData('ai', e.target.value)}
                            />
                        </Field>

                        <Field label="Adresse" error={errors.address_line1}>
                            <input
                                className={inputClass}
                                value={data.address_line1}
                                onChange={(e) => setData('address_line1', e.target.value)}
                            />
                        </Field>

                        <Field label="Complément d’adresse" error={errors.address_line2}>
                            <input
                                className={inputClass}
                                value={data.address_line2}
                                onChange={(e) => setData('address_line2', e.target.value)}
                            />
                        </Field>

                        <Field label="Wilaya" error={errors.address_wilaya}>
                            <input
                                className={inputClass}
                                value={data.address_wilaya}
                                onChange={(e) => setData('address_wilaya', e.target.value)}
                            />
                        </Field>

                        <Field label="Code postal" error={errors.address_postal_code}>
                            <input
                                className={inputClass}
                                value={data.address_postal_code}
                                onChange={(e) => setData('address_postal_code', e.target.value)}
                            />
                        </Field>

                        <Field label="Régime fiscal" error={errors.tax_regime}>
                            <input
                                className={inputClass}
                                value={data.tax_regime}
                                onChange={(e) => setData('tax_regime', e.target.value)}
                            />
                        </Field>

                        <Field label="Devise" error={errors.currency}>
                            <input
                                className={inputClass}
                                value={data.currency}
                                onChange={(e) => setData('currency', e.target.value.toUpperCase())}
                            />
                        </Field>
                    </div>

                    <label className="flex items-center gap-3 rounded-xl border border-slate-200 px-4 py-3">
                        <input
                            type="checkbox"
                            checked={data.vat_registered}
                            onChange={(e) => setData('vat_registered', e.target.checked)}
                        />
                        <span className="text-sm text-slate-700">Entreprise assujettie à la TVA</span>
                    </label>

                    <div className="flex justify-end">
                        <button
                            type="submit"
                            disabled={processing}
                            className="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                        >
                            Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}

function Field({ label, children, error, requiredHint = false }) {
    return (
        <div>
            <label className="text-sm font-medium text-slate-700">
                {label}
                {requiredHint && (
                    <span className="ml-2 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">
                        conformité facture
                    </span>
                )}
            </label>
            {children}
            {error && <p className="mt-1 text-sm text-amber-700">{error}</p>}
        </div>
    );
}
