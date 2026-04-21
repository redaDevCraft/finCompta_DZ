import { Head } from '@inertiajs/react';

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

/**
 * Read-only view of performance logging budgets (config/performance.php).
 * Does not stream live metrics — those live in storage/logs/performance.log
 * when PERF_LOG_ENABLED is true.
 */
export default function Performance({ perf }) {
    return (
        <AuthenticatedLayout header="Performance & observabilité">
            <Head title="Performance" />

            <div className="mx-auto max-w-3xl space-y-6">
                <div>
                    <h2 className="text-xl font-semibold text-gray-900">Budgets & journaux</h2>
                    <p className="mt-1 text-sm text-gray-500">
                        Paramètres issus de <code className="rounded bg-gray-100 px-1">config/performance.php</code> et
                        des variables <code className="rounded bg-gray-100 px-1">PERF_*</code> dans{' '}
                        <code className="rounded bg-gray-100 px-1">.env</code>.
                    </p>
                </div>

                <div className="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                    <dl className="divide-y divide-gray-100">
                        <div className="grid gap-1 px-4 py-3 sm:grid-cols-3 sm:gap-4">
                            <dt className="text-sm font-medium text-gray-500">Journalisation HTTP / SQL</dt>
                            <dd className="text-sm text-gray-900 sm:col-span-2">
                                {perf?.enabled ? (
                                    <span className="font-medium text-emerald-700">Activée</span>
                                ) : (
                                    <span className="font-medium text-gray-600">Désactivée</span>
                                )}
                            </dd>
                        </div>
                        <div className="grid gap-1 px-4 py-3 sm:grid-cols-3 sm:gap-4">
                            <dt className="text-sm font-medium text-gray-500">Seuil requête lente (SQL)</dt>
                            <dd className="text-sm text-gray-900 sm:col-span-2">{perf?.slow_query_ms ?? '—'} ms</dd>
                        </div>
                        <div className="grid gap-1 px-4 py-3 sm:grid-cols-3 sm:gap-4">
                            <dt className="text-sm font-medium text-gray-500">Seuil requête HTTP lente</dt>
                            <dd className="text-sm text-gray-900 sm:col-span-2">{perf?.slow_request_ms ?? '—'} ms</dd>
                        </div>
                        <div className="grid gap-1 px-4 py-3 sm:grid-cols-3 sm:gap-4">
                            <dt className="text-sm font-medium text-gray-500">Fichier de log</dt>
                            <dd className="break-all text-sm text-gray-900 sm:col-span-2">
                                {perf?.log_path ?? '—'}
                            </dd>
                        </div>
                    </dl>
                </div>

                <p className="text-sm text-gray-500">
                    En production, désactivez la journalisation détaillée (PERF_LOG_ENABLED=false) sauf
                    profilage ponctuel. Les agrégats p95 / tableaux de bord externes restent à brancher sur
                    ce fichier ou sur votre stack APM.
                </p>
            </div>
        </AuthenticatedLayout>
    );
}
