import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Search, XCircle, RotateCcw, Timer } from 'lucide-react';
import { useNotification } from '@/Context/NotificationContext';

const STATUS_TABS = [
    { key: '', label: 'Tous' },
    { key: 'trialing', label: 'Essai' },
    { key: 'active', label: 'Actifs' },
    { key: 'past_due', label: 'Impayés' },
    { key: 'canceled', label: 'Résiliés' },
];

const STATUS_STYLES = {
    trialing: 'bg-amber-50 text-amber-800',
    active: 'bg-emerald-50 text-emerald-800',
    past_due: 'bg-orange-50 text-orange-800',
    canceled: 'bg-slate-100 text-slate-600',
    paused: 'bg-blue-50 text-blue-800',
};

export default function SubscriptionsIndex({ subscriptions, filters = {}, counts = {} }) {
    const [search, setSearch] = useState(filters.search || '');
    const { confirm, prompt, warning } = useNotification();

    function apply(params) {
        router.get(
            route('admin.subscriptions.index'),
            { ...filters, ...params, search: params.search ?? search },
            { preserveState: true, replace: true },
        );
    }

    async function cancel(sub, immediate) {
        const msg = immediate
            ? 'Résilier IMMÉDIATEMENT cet abonnement ?'
            : 'Planifier la résiliation à la fin de la période ?';
        const ok = await confirm({
            title: 'Résiliation',
            message: msg,
            confirmLabel: 'Confirmer',
        });
        if (!ok) return;
        router.post(
            route('admin.subscriptions.cancel', sub.id),
            { immediate },
            { preserveScroll: true },
        );
    }

    async function reactivate(sub) {
        const ok = await confirm({
            title: 'Réactivation',
            message: 'Réactiver cet abonnement ?',
            confirmLabel: 'Réactiver',
        });
        if (!ok) return;
        router.post(
            route('admin.subscriptions.reactivate', sub.id),
            {},
            { preserveScroll: true },
        );
    }

    async function extend(sub) {
        const days = await prompt({
            title: 'Prolonger abonnement',
            message: 'Prolonger cet abonnement de combien de jours ?',
            placeholder: 'Ex: 30',
            defaultValue: '30',
            confirmLabel: 'Prolonger',
        });
        if (!days) return;
        if (Number(days) < 1) {
            warning('Le nombre de jours doit être supérieur à 0.');
            return;
        }
        router.post(
            route('admin.subscriptions.extend', sub.id),
            { days: Number(days) },
            { preserveScroll: true },
        );
    }

    return (
        <AdminLayout header="Abonnements">
            <Head title="Admin — Abonnements" />

            <div className="mx-auto max-w-6xl space-y-4">
                <div className="grid gap-2 sm:grid-cols-4">
                    <Counter label="Essai" value={counts.trialing ?? 0} color="amber" />
                    <Counter label="Actifs" value={counts.active ?? 0} color="emerald" />
                    <Counter label="Impayés" value={counts.past_due ?? 0} color="orange" />
                    <Counter label="Résiliés" value={counts.canceled ?? 0} color="slate" />
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    {STATUS_TABS.map((t) => (
                        <button
                            key={t.key || 'all'}
                            type="button"
                            onClick={() => apply({ status: t.key || undefined })}
                            className={`rounded-full px-3 py-1.5 text-xs font-medium ${
                                (filters.status ?? '') === t.key
                                    ? 'bg-slate-900 text-white'
                                    : 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-50'
                            }`}
                        >
                            {t.label}
                        </button>
                    ))}

                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            apply({ search: search || undefined });
                        }}
                        className="ml-auto flex max-w-sm flex-1 gap-2"
                    >
                        <div className="relative flex-1">
                            <Search className="pointer-events-none absolute left-2.5 top-2.5 h-4 w-4 text-slate-400" />
                            <input
                                type="text"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Raison sociale, NIF…"
                                className="w-full rounded-lg border border-slate-300 py-2 pl-8 pr-3 text-sm"
                            />
                        </div>
                        <button
                            type="submit"
                            className="rounded-lg bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800"
                        >
                            OK
                        </button>
                    </form>
                </div>

                <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                        <thead className="bg-slate-50 text-xs uppercase text-slate-500">
                            <tr>
                                <th className="px-4 py-3 text-left font-semibold">Société</th>
                                <th className="px-4 py-3 text-left font-semibold">Plan</th>
                                <th className="px-4 py-3 text-left font-semibold">Cycle</th>
                                <th className="px-4 py-3 text-left font-semibold">Statut</th>
                                <th className="px-4 py-3 text-left font-semibold">Période</th>
                                <th className="px-4 py-3 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {subscriptions.data.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="px-4 py-10 text-center text-slate-400">
                                        Aucun abonnement.
                                    </td>
                                </tr>
                            )}
                            {subscriptions.data.map((s) => (
                                <tr key={s.id} className="hover:bg-slate-50">
                                    <td className="px-4 py-3">
                                        {s.company ? (
                                            <Link
                                                href={route('admin.companies.show', s.company.id)}
                                                className="font-medium text-indigo-700 hover:underline"
                                            >
                                                {s.company.raison_sociale}
                                            </Link>
                                        ) : (
                                            '—'
                                        )}
                                        <div className="font-mono text-xs text-slate-500">{s.company?.nif || ''}</div>
                                    </td>
                                    <td className="px-4 py-3">{s.plan?.name ?? '—'}</td>
                                    <td className="px-4 py-3 text-xs">{s.billing_cycle}</td>
                                    <td className="px-4 py-3">
                                        <span
                                            className={`rounded-full px-2 py-0.5 text-xs ${STATUS_STYLES[s.status] || 'bg-slate-100'}`}
                                        >
                                            {s.status}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-xs text-slate-600">
                                        {fmtDate(s.current_period_started_at)} → {fmtDate(s.current_period_ends_at)}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <div className="flex flex-wrap justify-end gap-1.5">
                                            <button
                                                type="button"
                                                onClick={() => extend(s)}
                                                className="inline-flex items-center gap-1 rounded-md border border-slate-200 px-2 py-1 text-xs text-slate-700 hover:bg-slate-50"
                                            >
                                                <Timer className="h-3 w-3" /> Prolonger
                                            </button>
                                            {s.status !== 'canceled' && (
                                                <button
                                                    type="button"
                                                    onClick={() => cancel(s, false)}
                                                    className="inline-flex items-center gap-1 rounded-md border border-rose-200 px-2 py-1 text-xs text-rose-700 hover:bg-rose-50"
                                                >
                                                    <XCircle className="h-3 w-3" /> Résilier
                                                </button>
                                            )}
                                            {s.status === 'canceled' && (
                                                <button
                                                    type="button"
                                                    onClick={() => reactivate(s)}
                                                    className="inline-flex items-center gap-1 rounded-md border border-emerald-200 px-2 py-1 text-xs text-emerald-700 hover:bg-emerald-50"
                                                >
                                                    <RotateCcw className="h-3 w-3" /> Réactiver
                                                </button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AdminLayout>
    );
}

function Counter({ label, value, color = 'slate' }) {
    const bg = {
        amber: 'bg-amber-50 text-amber-900 border-amber-100',
        emerald: 'bg-emerald-50 text-emerald-900 border-emerald-100',
        orange: 'bg-orange-50 text-orange-900 border-orange-100',
        slate: 'bg-slate-50 text-slate-900 border-slate-100',
    }[color];
    return (
        <div className={`rounded-xl border p-3 ${bg}`}>
            <div className="text-xs uppercase">{label}</div>
            <div className="text-2xl font-semibold tabular-nums">{value}</div>
        </div>
    );
}

function fmtDate(d) {
    if (!d) return '—';
    try {
        return new Date(d).toLocaleDateString('fr-DZ');
    } catch {
        return d;
    }
}
