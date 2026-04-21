import { Head, Link } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { CheckCircle2, Clock, Download, Loader2, XCircle } from 'lucide-react';

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

/**
 * Mes exports — the user-facing index of queued/completed report artifacts.
 *
 * UX contract:
 *   - Server SSR delivers the initial list (no loading shimmer on first paint).
 *   - While any run is non-terminal (queued or running), the page polls
 *     /reports/runs/{id} every 3 s to refresh just that row. Terminal
 *     rows (ready / failed) are never polled again.
 *   - Polling stops as soon as there's nothing non-terminal left. This
 *     page is safe to leave open.
 */

const POLL_INTERVAL_MS = 3000;

const TYPE_LABELS = {
    bilan_pdf: 'Bilan (PDF)',
    vat_xlsx: 'TVA (Excel)',
};

function formatRunParams(run) {
    if (run.params?.as_of_date) {
        return `au ${run.params.as_of_date}`;
    }
    if (run.type === 'vat_xlsx' && run.params?.year != null) {
        const y = run.params.year;
        if (run.params.month) {
            return `${y} — mois ${run.params.month}`;
        }
        if (run.params.quarter) {
            return `${y} — T${run.params.quarter}`;
        }
        return String(y);
    }
    return '—';
}

const STATUS_META = {
    queued: { label: 'En file', tone: 'bg-slate-100 text-slate-700', Icon: Clock },
    running: { label: 'En cours', tone: 'bg-indigo-100 text-indigo-700', Icon: Loader2 },
    ready: { label: 'Prêt', tone: 'bg-emerald-100 text-emerald-700', Icon: CheckCircle2 },
    failed: { label: 'Échec', tone: 'bg-rose-100 text-rose-700', Icon: XCircle },
};

const formatBytes = (bytes) => {
    if (!bytes) return '—';
    if (bytes < 1024) return `${bytes} o`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} Ko`;
    return `${(bytes / 1024 / 1024).toFixed(2)} Mo`;
};

const formatDateTime = (iso) => {
    if (!iso) return '—';
    const d = new Date(iso);
    return new Intl.DateTimeFormat('fr-DZ', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    }).format(d);
};

const isTerminal = (status) => status === 'ready' || status === 'failed';

export default function Exports({ runs = [] }) {
    const [rows, setRows] = useState(runs);

    const pending = useMemo(() => rows.filter((r) => !isTerminal(r.status)), [rows]);

    // Cooldown is set when the server throttles us (429). Polling is
    // paused until Date.now() passes this timestamp — honoring the
    // server's Retry-After is mandatory under the Phase-8 rate limits
    // or the limiter just keeps re-denying forever.
    const cooldownUntilRef = useRef(0);

    const refreshRow = useCallback(async (id) => {
        if (Date.now() < cooldownUntilRef.current) return;

        try {
            const res = await fetch(`/reports/runs/${id}`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            if (res.status === 429) {
                const retryAfter = Number(res.headers.get('Retry-After')) || 30;
                cooldownUntilRef.current = Date.now() + retryAfter * 1000;
                return;
            }

            if (!res.ok) return;

            const next = await res.json();
            setRows((prev) => prev.map((r) => (r.id === id ? next : r)));
        } catch {
            // Network blips are non-fatal — we'll retry on the next tick.
        }
    }, []);

    useEffect(() => {
        if (pending.length === 0) return undefined;

        const timer = setInterval(() => {
            pending.forEach((row) => refreshRow(row.id));
        }, POLL_INTERVAL_MS);

        return () => clearInterval(timer);
    }, [pending, refreshRow]);

    return (
        <AuthenticatedLayout header="Mes exports">
            <Head title="Mes exports" />

            <div className="space-y-6">
                <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div className="mb-4 flex items-start justify-between">
                        <div>
                            <h2 className="text-lg font-semibold text-slate-900">Exports récents</h2>
                            <p className="mt-1 text-sm text-slate-500">
                                Les exports lourds (bilan, grands livres, etc.) s'exécutent en
                                arrière-plan. Laissez cette page ouverte — elle se met à jour
                                automatiquement.
                            </p>
                        </div>
                        <Link
                            href="/reports/bilan"
                            className="inline-flex items-center rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                        >
                            Retour aux rapports
                        </Link>
                    </div>

                    <div className="overflow-hidden rounded-xl border border-slate-200">
                        <table className="min-w-full divide-y divide-slate-200 text-sm">
                            <thead className="bg-slate-50 text-left text-xs font-medium uppercase tracking-wider text-slate-500">
                                <tr>
                                    <th className="px-4 py-3">Type</th>
                                    <th className="px-4 py-3">Paramètres</th>
                                    <th className="px-4 py-3">Statut</th>
                                    <th className="px-4 py-3">Taille</th>
                                    <th className="px-4 py-3">Créé</th>
                                    <th className="px-4 py-3">Terminé</th>
                                    <th className="px-4 py-3 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 bg-white">
                                {rows.length === 0 && (
                                    <tr>
                                        <td colSpan={7} className="px-4 py-10 text-center text-slate-500">
                                            Aucun export pour le moment.
                                        </td>
                                    </tr>
                                )}

                                {rows.map((run) => {
                                    const status = STATUS_META[run.status] ?? STATUS_META.queued;
                                    const Icon = status.Icon;

                                    return (
                                        <tr key={run.id} className="align-top">
                                            <td className="px-4 py-3 font-medium text-slate-900">
                                                {TYPE_LABELS[run.type] ?? run.type}
                                            </td>
                                            <td className="px-4 py-3 text-slate-600">{formatRunParams(run)}</td>
                                            <td className="px-4 py-3">
                                                <span
                                                    className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium ${status.tone}`}
                                                >
                                                    <Icon
                                                        className={`h-3.5 w-3.5 ${
                                                            run.status === 'running' ? 'animate-spin' : ''
                                                        }`}
                                                    />
                                                    {status.label}
                                                </span>
                                                {run.status === 'failed' && run.error_message && (
                                                    <p className="mt-1 max-w-xs truncate text-xs text-rose-600" title={run.error_message}>
                                                        {run.error_message}
                                                    </p>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-slate-600">
                                                {formatBytes(run.artifact_bytes)}
                                            </td>
                                            <td className="px-4 py-3 text-slate-600">
                                                {formatDateTime(run.created_at)}
                                            </td>
                                            <td className="px-4 py-3 text-slate-600">
                                                {formatDateTime(run.completed_at)}
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                {run.download_url ? (
                                                    <a
                                                        href={run.download_url}
                                                        className="inline-flex items-center gap-1.5 rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-medium text-white hover:bg-slate-800"
                                                    >
                                                        <Download className="h-3.5 w-3.5" />
                                                        Télécharger
                                                    </a>
                                                ) : (
                                                    <span className="text-xs text-slate-400">—</span>
                                                )}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
