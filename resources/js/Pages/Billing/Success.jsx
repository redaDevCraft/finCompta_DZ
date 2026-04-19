import { Head, Link } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { CheckCircle2 } from 'lucide-react';

export default function Success({ payment }) {
    return (
        <AuthenticatedLayout header="Paiement réussi">
            <Head title="Paiement réussi" />
            <div className="mx-auto max-w-2xl rounded-2xl border border-emerald-200 bg-emerald-50 p-8 text-center">
                <CheckCircle2 className="mx-auto h-14 w-14 text-emerald-600" />
                <h1 className="mt-4 text-2xl font-bold text-emerald-900">Merci — paiement en cours de validation</h1>
                <p className="mt-2 text-sm text-emerald-800">
                    Votre paiement de <strong>{new Intl.NumberFormat('fr-DZ').format(payment.amount_dzd)} DZD</strong>
                    {' '}a été reçu par Chargily. Votre abonnement sera activé dès confirmation (quelques secondes).
                </p>
                <div className="mt-6 flex justify-center gap-3">
                    <Link
                        href={route('billing.index')}
                        className="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700"
                    >
                        Voir mon abonnement
                    </Link>
                    <Link
                        href={route('dashboard')}
                        className="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                    >
                        Aller au tableau de bord
                    </Link>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
