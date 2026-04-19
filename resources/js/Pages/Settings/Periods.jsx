import { Head, router, useForm } from '@inertiajs/react';
import { useMemo } from 'react';
import { Lock, Unlock, Plus } from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

const MONTH_NAMES = [
    'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
    'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre',
];

function formatDate(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleString('fr-FR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export default function Periods({ periods }) {
    const now = new Date();
    const { data, setData, post, processing, errors, reset } = useForm({
        year: now.getFullYear(),
        month: now.getMonth() + 1,
    });

    const grouped = useMemo(() => {
        const map = {};
        periods.forEach((p) => {
            if (!map[p.year]) map[p.year] = [];
            map[p.year].push(p);
        });
        return map;
    }, [periods]);

    const submit = (e) => {
        e.preventDefault();
        post('/settings/periods', {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    const lock = (period) => {
        if (!confirm(`Verrouiller la période ${MONTH_NAMES[period.month - 1]} ${period.year} ?\n\nCette action empêchera toute modification des écritures.`)) return;
        router.post(`/settings/periods/${period.id}/lock`, {}, { preserveScroll: true });
    };

    const reopen = (period) => {
        if (!confirm(`Rouvrir la période ${MONTH_NAMES[period.month - 1]} ${period.year} ?`)) return;
        router.post(`/settings/periods/${period.id}/reopen`, {}, { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Périodes fiscales" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-semibold text-slate-900">Périodes fiscales</h1>
                    <p className="mt-1 text-sm text-slate-600">
                        Verrouillez les périodes passées pour garantir l'intangibilité de votre comptabilité.
                    </p>
                </div>

                <form onSubmit={submit} className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div className="flex flex-wrap items-end gap-3">
                        <div>
                            <label className="mb-1 block text-xs font-medium text-slate-600">Année</label>
                            <input
                                type="number"
                                value={data.year}
                                onChange={(e) => setData('year', parseInt(e.target.value || '0', 10))}
                                min={2000}
                                max={2100}
                                className="w-28 rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            />
                        </div>
                        <div>
                            <label className="mb-1 block text-xs font-medium text-slate-600">Mois</label>
                            <select
                                value={data.month}
                                onChange={(e) => setData('month', parseInt(e.target.value, 10))}
                                className="rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            >
                                {MONTH_NAMES.map((m, i) => (
                                    <option key={i} value={i + 1}>{m}</option>
                                ))}
                            </select>
                        </div>
                        <button
                            type="submit"
                            disabled={processing}
                            className="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-60"
                        >
                            <Plus className="h-4 w-4" />
                            Créer la période
                        </button>
                        {(errors.year || errors.month) && (
                            <p className="text-sm text-rose-600">
                                {errors.year || errors.month}
                            </p>
                        )}
                    </div>
                </form>

                <div className="space-y-4">
                    {Object.keys(grouped).length === 0 && (
                        <div className="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">
                            Aucune période créée pour le moment.
                        </div>
                    )}
                    {Object.entries(grouped)
                        .sort((a, b) => b[0] - a[0])
                        .map(([year, items]) => (
                            <div key={year} className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                                <div className="border-b border-slate-200 bg-slate-50 px-5 py-3">
                                    <h2 className="font-semibold text-slate-900">Exercice {year}</h2>
                                </div>
                                <div className="divide-y divide-slate-100">
                                    {items
                                        .sort((a, b) => a.month - b.month)
                                        .map((period) => {
                                            const locked = period.status === 'locked';
                                            return (
                                                <div key={period.id} className="flex flex-col gap-3 px-5 py-4 md:flex-row md:items-center md:justify-between">
                                                    <div className="flex items-center gap-3">
                                                        <div className={`flex h-10 w-10 items-center justify-center rounded-full ${locked ? 'bg-rose-50 text-rose-600' : 'bg-emerald-50 text-emerald-600'}`}>
                                                            {locked ? <Lock className="h-4 w-4" /> : <Unlock className="h-4 w-4" />}
                                                        </div>
                                                        <div>
                                                            <div className="font-medium text-slate-900">
                                                                {MONTH_NAMES[period.month - 1]} {period.year}
                                                            </div>
                                                            <div className="text-xs text-slate-500">
                                                                {period.entries_count} écriture(s) •
                                                                {period.unposted_count > 0 && (
                                                                    <span className="ml-1 font-medium text-amber-600">
                                                                        {period.unposted_count} brouillon(s)
                                                                    </span>
                                                                )}
                                                                {locked && (
                                                                    <span className="ml-1 text-slate-500">
                                                                        verrouillée le {formatDate(period.locked_at)}
                                                                    </span>
                                                                )}
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div>
                                                        {locked ? (
                                                            <button
                                                                type="button"
                                                                onClick={() => reopen(period)}
                                                                className="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50"
                                                            >
                                                                <Unlock className="h-3.5 w-3.5" />
                                                                Rouvrir
                                                            </button>
                                                        ) : (
                                                            <button
                                                                type="button"
                                                                onClick={() => lock(period)}
                                                                disabled={period.unposted_count > 0}
                                                                className="inline-flex items-center gap-2 rounded-lg border border-rose-200 px-3 py-1.5 text-sm text-rose-600 hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-50"
                                                                title={period.unposted_count > 0 ? 'Validez d\'abord les écritures en brouillon' : 'Verrouiller cette période'}
                                                            >
                                                                <Lock className="h-3.5 w-3.5" />
                                                                Verrouiller
                                                            </button>
                                                        )}
                                                    </div>
                                                </div>
                                            );
                                        })}
                                </div>
                            </div>
                        ))}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
