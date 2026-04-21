import { Head, Link, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { CheckCircle2, Download, FileText, Upload } from 'lucide-react';

function formatDzd(n) {
    return new Intl.NumberFormat('fr-DZ').format(n) + ' DZD';
}

export default function BonDeCommande({ payment, payee, admin_email }) {
    const form = useForm({
        proof: null,
    });

    const submit = (e) => {
        e.preventDefault();
        form.post(route('billing.bon.proof', payment.id), { forceFormData: true });
    };

    return (
        <AuthenticatedLayout header="Bon de commande">
            <Head title="Bon de commande" />

            <div className="mx-auto max-w-3xl space-y-6">
                <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div className="flex items-start justify-between">
                        <div>
                            <h1 className="text-lg font-semibold">Bon de commande #{payment.reference}</h1>
                            <p className="mt-1 text-sm text-slate-500">
                                Plan <strong>{payment.plan?.name}</strong> — facturation {payment.billing_cycle === 'yearly' ? 'annuelle' : 'mensuelle'}
                            </p>
                        </div>
                        <div className="text-right">
                            <div className="text-xs text-slate-500">Montant</div>
                            <div className="text-2xl font-bold">{formatDzd(payment.amount_dzd)}</div>
                        </div>
                    </div>

                    <a
                        href={route('billing.bon.download', payment.id)}
                        className="mt-6 inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                    >
                        <Download className="h-4 w-4" /> Télécharger le bon de commande (PDF)
                    </a>
                </div>

                <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 className="flex items-center gap-2 text-base font-semibold">
                        <FileText className="h-5 w-5 text-slate-700" /> Coordonnées bancaires
                    </h2>
                    <dl className="mt-4 grid gap-2 text-sm sm:grid-cols-2">
                        <InfoRow label="Bénéficiaire" value={payee?.name ?? '—'} />
                        <InfoRow label="Banque" value={payee?.bank_name ?? '—'} />
                        <InfoRow label="RIB" value={payee?.bank_rib ?? '—'} mono />
                        <InfoRow label="SWIFT / BIC" value={payee?.bank_swift ?? '—'} mono />
                        <InfoRow label="Libellé virement" value={payment.reference} mono />
                        <InfoRow label="Contact validation" value={admin_email ?? '—'} />
                    </dl>
                </div>

                <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 className="flex items-center gap-2 text-base font-semibold">
                        <Upload className="h-5 w-5 text-slate-700" /> Déposer le justificatif de virement
                    </h2>
                    <p className="mt-1 text-sm text-slate-500">
                        PDF, JPG ou PNG — 10 Mo max. Votre abonnement sera activé sous 24h après validation.
                    </p>
                    <form onSubmit={submit} className="mt-4 space-y-3">
                        <input
                            type="file"
                            accept="application/pdf,image/jpeg,image/png"
                            onChange={(e) => form.setData('proof', e.target.files?.[0])}
                            className="block w-full text-sm"
                        />
                        {form.errors.proof && (
                            <p className="text-xs text-rose-600">{form.errors.proof}</p>
                        )}
                        <button
                            type="submit"
                            disabled={!form.data.proof || form.processing}
                            className="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                        >
                            {form.processing ? 'Envoi…' : 'Envoyer le justificatif'}
                        </button>
                    </form>

                    {payment.proof_upload_path && (
                        <div className="mt-4 flex items-center gap-2 rounded-xl bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                            <CheckCircle2 className="h-4 w-4" /> Justificatif déjà envoyé — en cours de vérification.
                        </div>
                    )}
                </div>

                <Link href={route('billing.index')} className="inline-block text-sm text-indigo-600 hover:underline">
                    ← Retour à la facturation
                </Link>
            </div>
        </AuthenticatedLayout>
    );
}

function InfoRow({ label, value, mono }) {
    return (
        <div className="rounded-lg border border-slate-100 bg-slate-50 px-3 py-2">
            <div className="text-xs text-slate-500">{label}</div>
            <div className={'text-sm ' + (mono ? 'font-mono' : 'font-medium')}>{value}</div>
        </div>
    );
}
