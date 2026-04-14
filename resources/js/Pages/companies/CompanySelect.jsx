import { Head, useForm } from '@inertiajs/react';
import { Building2, CheckCircle2 } from 'lucide-react';

export default function CompanySelect({ companies = [] }) {
    const { data, setData, post, processing, errors } = useForm({
        company_id: '',
    });

    const submit = (e) => {
        e.preventDefault();

        if (!data.company_id) return;

        post(route('company.switch'));
    };

    return (
        <>
            <Head title="Sélection de l’entreprise" />

            <div className="min-h-screen bg-slate-50 px-4 py-10">
                <div className="mx-auto max-w-4xl">
                    <div className="mb-8 text-center">
                        <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-indigo-100 text-indigo-700">
                            <Building2 className="h-8 w-8" />
                        </div>

                        <h1 className="text-3xl font-bold text-slate-900">
                            Sélectionnez une entreprise
                        </h1>

                        <p className="mt-2 text-sm text-slate-600">
                            Choisissez l’entreprise sur laquelle vous souhaitez travailler.
                        </p>
                    </div>

                    <form onSubmit={submit} className="space-y-6">
                        {errors.company_id && (
                            <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                                {errors.company_id}
                            </div>
                        )}

                        {companies.length === 0 ? (
                            <div className="rounded-2xl border border-slate-200 bg-white p-10 text-center shadow-sm">
                                <p className="text-base font-medium text-slate-800">
                                    Aucune entreprise disponible
                                </p>
                                <p className="mt-2 text-sm text-slate-500">
                                    Votre utilisateur n’est rattaché à aucune entreprise active.
                                </p>
                            </div>
                        ) : (
                            <div className="grid gap-4 md:grid-cols-2">
                                {companies.map((company) => {
                                    const selected = data.company_id === company.id;

                                    return (
                                        <button
                                            key={company.id}
                                            type="button"
                                            onClick={() => setData('company_id', company.id)}
                                            className={[
                                                'rounded-2xl border bg-white p-5 text-left shadow-sm transition',
                                                selected
                                                    ? 'border-indigo-500 ring-2 ring-indigo-200'
                                                    : 'border-slate-200 hover:border-indigo-300 hover:shadow-md',
                                            ].join(' ')}
                                        >
                                            <div className="flex items-start justify-between gap-4">
                                                <div className="min-w-0">
                                                    <h2 className="truncate text-lg font-semibold text-slate-900">
                                                        {company.raison_sociale}
                                                    </h2>

                                                    <div className="mt-3 space-y-1 text-sm text-slate-600">
                                                        <p>
                                                            Régime fiscal :{' '}
                                                            <span className="font-medium text-slate-800">
                                                                {company.tax_regime || '—'}
                                                            </span>
                                                        </p>
                                                        <p>
                                                            TVA :{' '}
                                                            <span className="font-medium text-slate-800">
                                                                {company.vat_registered ? 'Oui' : 'Non'}
                                                            </span>
                                                        </p>
                                                        <p>
                                                            Statut :{' '}
                                                            <span className="font-medium text-slate-800">
                                                                {company.status || '—'}
                                                            </span>
                                                        </p>
                                                    </div>
                                                </div>

                                                {selected && (
                                                    <CheckCircle2 className="h-6 w-6 shrink-0 text-indigo-600" />
                                                )}
                                            </div>
                                        </button>
                                    );
                                })}
                            </div>
                        )}

                        {companies.length > 0 && (
                            <div className="flex justify-end">
                                <button
                                    type="submit"
                                    disabled={!data.company_id || processing}
                                    className="inline-flex items-center rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    {processing ? 'Ouverture...' : 'Continuer'}
                                </button>
                            </div>
                        )}
                    </form>
                </div>
            </div>
        </>
    );
}
