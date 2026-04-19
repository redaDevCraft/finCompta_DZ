import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Check, Pencil, Plus, Power, Trash2 } from 'lucide-react';

function fmtDzd(n) {
    return (Number(n) || 0).toLocaleString('fr-DZ');
}

export default function PlansIndex({ plans }) {
    const { props } = usePage();
    const errors = props.errors ?? {};

    function toggle(plan) {
        router.post(
            route('admin.plans.toggle', plan.id),
            {},
            { preserveScroll: true },
        );
    }

    function destroy(plan) {
        if (!confirm(`Supprimer le plan « ${plan.name} » ?`)) return;
        router.delete(route('admin.plans.destroy', plan.id), {
            preserveScroll: true,
        });
    }

    return (
        <AdminLayout header="Plans tarifaires">
            <Head title="Admin — Plans" />
            <div className="mx-auto max-w-6xl space-y-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold text-slate-900">Plans</h1>
                        <p className="text-sm text-slate-500">
                            Gérer les plans exposés sur la page pricing et dans l'assistant d'abonnement.
                        </p>
                    </div>
                    <Link
                        href={route('admin.plans.create')}
                        className="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                    >
                        <Plus className="h-4 w-4" />
                        Nouveau plan
                    </Link>
                </div>

                {errors.plan && (
                    <div className="rounded-lg border border-rose-200 bg-rose-50 px-4 py-2 text-sm text-rose-800">
                        {errors.plan}
                    </div>
                )}

                <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                        <thead className="bg-slate-50 text-xs uppercase text-slate-500">
                            <tr>
                                <th className="px-4 py-3 text-left font-semibold">Plan</th>
                                <th className="px-4 py-3 text-left font-semibold">Code</th>
                                <th className="px-4 py-3 text-right font-semibold">Mensuel</th>
                                <th className="px-4 py-3 text-right font-semibold">Annuel</th>
                                <th className="px-4 py-3 text-right font-semibold">Essai</th>
                                <th className="px-4 py-3 text-right font-semibold">Abos actifs</th>
                                <th className="px-4 py-3 text-center font-semibold">Actif</th>
                                <th className="px-4 py-3 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {plans.length === 0 && (
                                <tr>
                                    <td colSpan={8} className="px-4 py-10 text-center text-slate-400">
                                        Aucun plan. Créez-en un pour démarrer.
                                    </td>
                                </tr>
                            )}
                            {plans.map((plan) => (
                                <tr key={plan.id} className="hover:bg-slate-50">
                                    <td className="px-4 py-3">
                                        <div className="font-medium text-slate-900">{plan.name}</div>
                                        <div className="text-xs text-slate-500">{plan.tagline || '—'}</div>
                                    </td>
                                    <td className="px-4 py-3 font-mono text-xs">{plan.code}</td>
                                    <td className="px-4 py-3 text-right tabular-nums">
                                        {fmtDzd(plan.monthly_price_dzd)} DZD
                                    </td>
                                    <td className="px-4 py-3 text-right tabular-nums">
                                        {fmtDzd(plan.yearly_price_dzd)} DZD
                                    </td>
                                    <td className="px-4 py-3 text-right">{plan.trial_days} j</td>
                                    <td className="px-4 py-3 text-right">{plan.active_subscriptions_count ?? 0}</td>
                                    <td className="px-4 py-3 text-center">
                                        {plan.is_active ? (
                                            <span className="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-800">
                                                <Check className="h-3 w-3" />
                                                Actif
                                            </span>
                                        ) : (
                                            <span className="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">
                                                Inactif
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex justify-end gap-1.5">
                                            <Link
                                                href={route('admin.plans.edit', plan.id)}
                                                className="rounded-md border border-slate-200 p-1.5 text-slate-600 hover:bg-slate-50"
                                                title="Modifier"
                                            >
                                                <Pencil className="h-3.5 w-3.5" />
                                            </Link>
                                            <button
                                                type="button"
                                                onClick={() => toggle(plan)}
                                                className="rounded-md border border-slate-200 p-1.5 text-slate-600 hover:bg-slate-50"
                                                title={plan.is_active ? 'Désactiver' : 'Activer'}
                                            >
                                                <Power className="h-3.5 w-3.5" />
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => destroy(plan)}
                                                className="rounded-md border border-rose-200 p-1.5 text-rose-600 hover:bg-rose-50"
                                                title="Supprimer"
                                            >
                                                <Trash2 className="h-3.5 w-3.5" />
                                            </button>
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
