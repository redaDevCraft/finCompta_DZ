import { Head, router, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

const money = (v) => new Intl.NumberFormat('fr-DZ', { style: 'currency', currency: 'DZD' }).format(Number(v ?? 0));

export default function Predictions({
    enabled,
    predictions = [],
    actualVsBudget = [],
    accounts = [],
    sections = [],
    filters = {},
}) {
    const toggleForm = useForm({ enabled: !!enabled });
    const createForm = useForm({
        account_id: '',
        contact_id: '',
        analytic_section_id: '',
        period_type: 'month',
        period_start_date: filters.from || '',
        period_end_date: filters.to || '',
        amount: '',
        comment: '',
    });

    return (
        <AuthenticatedLayout header="Prévisions de gestion">
            <Head title="Prévisions de gestion" />

            <div className="space-y-6">
                <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div className="flex items-center justify-between">
                        <div>
                            <h2 className="text-lg font-semibold text-slate-900">Activation</h2>
                            <p className="text-sm text-slate-500">Activer/désactiver les prévisions de gestion.</p>
                        </div>
                        <button
                            type="button"
                            disabled={toggleForm.processing}
                            onClick={() => toggleForm.post('/reports/predictions/toggle', {
                                data: { enabled: !toggleForm.data.enabled },
                                preserveScroll: true,
                                onSuccess: () => toggleForm.setData('enabled', !toggleForm.data.enabled),
                            })}
                            className={`rounded-lg px-4 py-2 text-sm font-medium text-white disabled:cursor-not-allowed disabled:opacity-60 ${toggleForm.data.enabled ? 'bg-emerald-600' : 'bg-slate-700'}`}
                        >
                            {toggleForm.data.enabled ? 'Activé' : 'Désactivé'}
                        </button>
                    </div>
                </div>

                <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <h3 className="text-base font-semibold text-slate-900">Nouvelle prévision</h3>
                    <form
                        className="mt-4 grid gap-3 md:grid-cols-4"
                        onSubmit={(e) => {
                            e.preventDefault();
                            createForm.post('/reports/predictions', { preserveScroll: true });
                        }}
                    >
                        <select value={createForm.data.account_id} onChange={(e) => createForm.setData('account_id', e.target.value)} className="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option value="">Compte (optionnel)</option>
                            {accounts.map((a) => <option key={a.id} value={a.id}>{a.code} - {a.label}</option>)}
                        </select>
                        <select value={createForm.data.analytic_section_id} onChange={(e) => createForm.setData('analytic_section_id', e.target.value)} className="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option value="">Section analytique</option>
                            {sections.map((s) => <option key={s.id} value={s.id}>{s.code} - {s.name}</option>)}
                        </select>
                        <select value={createForm.data.period_type} onChange={(e) => createForm.setData('period_type', e.target.value)} className="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option value="month">Mois</option>
                            <option value="quarter">Trimestre</option>
                            <option value="year">Année</option>
                        </select>
                        <input type="number" step="0.01" value={createForm.data.amount} onChange={(e) => createForm.setData('amount', e.target.value)} placeholder="Montant" className="rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                        <input type="date" value={createForm.data.period_start_date} onChange={(e) => createForm.setData('period_start_date', e.target.value)} className="rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                        <input type="date" value={createForm.data.period_end_date} onChange={(e) => createForm.setData('period_end_date', e.target.value)} className="rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                        <input value={createForm.data.comment} onChange={(e) => createForm.setData('comment', e.target.value)} placeholder="Commentaire" className="rounded-lg border border-slate-300 px-3 py-2 text-sm md:col-span-2" />
                        <button
                            disabled={createForm.processing}
                            className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            Ajouter
                        </button>
                    </form>
                </div>

                <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <h3 className="text-base font-semibold text-slate-900">Actual vs Budget</h3>
                    <div className="mt-3 overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-200 text-sm">
                            <thead className="bg-slate-50 text-left text-xs uppercase text-slate-500">
                                <tr><th className="px-3 py-2">Compte</th><th className="px-3 py-2 text-right">Actual</th><th className="px-3 py-2 text-right">Budget</th><th className="px-3 py-2 text-right">Variance</th></tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {actualVsBudget.map((r) => (
                                    <tr key={r.account_id}>
                                        <td className="px-3 py-2">{r.account_code} - {r.account_label}</td>
                                        <td className="px-3 py-2 text-right">{money(r.actual)}</td>
                                        <td className="px-3 py-2 text-right">{money(r.budget)}</td>
                                        <td className={`px-3 py-2 text-right font-medium ${r.variance >= 0 ? 'text-emerald-700' : 'text-rose-700'}`}>{money(r.variance)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <h3 className="text-base font-semibold text-slate-900">Prévisions enregistrées</h3>
                    <div className="mt-3 space-y-2">
                        {predictions.map((p) => (
                            <div key={p.id} className="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                <div>
                                    {(p.account?.code ? `${p.account.code} - ${p.account.label}` : 'Sans compte')} | {money(p.amount)} | {p.period_start_date} → {p.period_end_date}
                                </div>
                                <button
                                    type="button"
                                    onClick={() => router.delete(`/reports/predictions/${p.id}`, { preserveScroll: true })}
                                    className="rounded border border-rose-300 px-2 py-1 text-xs text-rose-700"
                                >
                                    Supprimer
                                </button>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
