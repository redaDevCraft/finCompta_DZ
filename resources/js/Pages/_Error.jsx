import { Head, Link } from '@inertiajs/react';

const content = {
    403: {
        title: 'Accès refusé',
        description: "Vous n'êtes pas autorisé à effectuer cette action.",
    },
    404: {
        title: 'Ressource introuvable',
        description: "La page ou la ressource demandée est introuvable.",
    },
    422: {
        title: 'Action impossible',
        description: "L'action n'a pas pu être exécutée. Vérifiez les informations fournies.",
    },
    500: {
        title: 'Erreur serveur',
        description: "Une erreur interne est survenue. Veuillez réessayer plus tard.",
    },
};

export default function Error({ status = 500, message = null }) {
    const page = content[status] ?? content[500];

    return (
        <>
            <Head title={`${page.title} | FinCompta DZ`} />

            <div className="flex min-h-screen items-center justify-center bg-slate-50 px-4">
                <div className="w-full max-w-xl rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
                    <div className="mb-4 inline-flex rounded-full bg-slate-100 px-3 py-1 text-sm font-medium text-slate-600">
                        Erreur {status}
                    </div>

                    <h1 className="text-3xl font-semibold text-slate-900">
                        {page.title}
                    </h1>

                    <p className="mt-3 text-sm leading-6 text-slate-600">
                        {message ?? page.description}
                    </p>

                    <div className="mt-6">
                        <Link
                            href="/dashboard"
                            className="inline-flex items-center rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-indigo-700"
                        >
                            Retour au tableau de bord
                        </Link>
                    </div>
                </div>
            </div>
        </>
    );
}
