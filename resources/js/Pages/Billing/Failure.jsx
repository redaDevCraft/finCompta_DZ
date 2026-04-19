import { Head, Link } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { XCircle } from 'lucide-react';

export default function Failure({ payment }) {
    return (
        <AuthenticatedLayout header="Paiement échoué">
            <Head title="Paiement échoué" />
            <div className="mx-auto max-w-2xl rounded-2xl border border-rose-200 bg-rose-50 p-8 text-center">
                <XCircle className="mx-auto h-14 w-14 text-rose-600" />
                <h1 className="mt-4 text-2xl font-bold text-rose-900">Le paiement n’a pas abouti</h1>
                <p className="mt-2 text-sm text-rose-800">
                    Votre paiement de {new Intl.NumberFormat('fr-DZ').format(payment.amount_dzd)} DZD n’a pas été validé.
                    Vous pouvez réessayer ou utiliser un bon de commande.
                </p>
                <div className="mt-6 flex justify-center gap-3">
                    <Link
                        href={route('billing.index')}
                        className="rounded-xl bg-rose-600 px-4 py-2 text-sm font-medium text-white hover:bg-rose-700"
                    >
                        Réessayer
                    </Link>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
