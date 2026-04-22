import { Head, router, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function AutoCounterpartRules({ rules = [], accounts = [] }) {
    const form = useForm({
        name: '',
        trigger_account_id: '',
        trigger_direction: 'debit',
        counterpart_account_id: '',
        counterpart_direction: 'credit',
        priority: 100,
        is_active: true,
    });

    return (
        <AuthenticatedLayout>
            <Head title="Règles de contrepartie auto" />
            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-semibold text-slate-900">Règles de contrepartie auto</h1>
                    <p className="mt-1 text-sm text-slate-600">
                        Génère automatiquement une ligne de contrepartie à montant égal.
                    </p>
                </div>

                <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <form
                        className="grid gap-3 md:grid-cols-3"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post(route('settings.auto-counterpart-rules.store'), {
                                preserveScroll: true,
                                onSuccess: () =>
                                    form.reset(
                                        'name',
                                        'trigger_account_id',
                                        'trigger_direction',
                                        'counterpart_account_id',
                                        'counterpart_direction',
                                        'priority',
                                        'is_active'
                                    ),
                            });
                        }}
                    >
                        <input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} placeholder="Nom de la règle" className="rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                        {form.errors.name && <p className="text-xs text-rose-600 md:col-span-3">{form.errors.name}</p>}
                        <select value={form.data.trigger_account_id} onChange={(e) => form.setData('trigger_account_id', e.target.value)} className="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option value="">Compte déclencheur</option>
                            {accounts.map((a) => <option key={a.id} value={a.id}>{a.code} - {a.label}</option>)}
                        </select>
                        {form.errors.trigger_account_id && <p className="text-xs text-rose-600 md:col-span-3">{form.errors.trigger_account_id}</p>}
                        <select value={form.data.trigger_direction} onChange={(e) => form.setData('trigger_direction', e.target.value)} className="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option value="debit">Débit</option>
                            <option value="credit">Crédit</option>
                        </select>
                        {form.errors.trigger_direction && <p className="text-xs text-rose-600 md:col-span-3">{form.errors.trigger_direction}</p>}
                        <select value={form.data.counterpart_account_id} onChange={(e) => form.setData('counterpart_account_id', e.target.value)} className="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option value="">Compte contrepartie</option>
                            {accounts.map((a) => <option key={a.id} value={a.id}>{a.code} - {a.label}</option>)}
                        </select>
                        {form.errors.counterpart_account_id && <p className="text-xs text-rose-600 md:col-span-3">{form.errors.counterpart_account_id}</p>}
                        <select value={form.data.counterpart_direction} onChange={(e) => form.setData('counterpart_direction', e.target.value)} className="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option value="credit">Crédit</option>
                            <option value="debit">Débit</option>
                        </select>
                        {form.errors.counterpart_direction && <p className="text-xs text-rose-600 md:col-span-3">{form.errors.counterpart_direction}</p>}
                        <input type="number" value={form.data.priority} onChange={(e) => form.setData('priority', Number(e.target.value || 100))} className="rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                        <label className="inline-flex items-center gap-2 text-sm text-slate-700">
                            <input
                                type="checkbox"
                                checked={form.data.is_active}
                                onChange={(e) => form.setData('is_active', e.target.checked)}
                                className="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                            />
                            Active
                        </label>
                        {form.errors.priority && <p className="text-xs text-rose-600 md:col-span-3">{form.errors.priority}</p>}
                        {form.errors.is_active && <p className="text-xs text-rose-600 md:col-span-3">{form.errors.is_active}</p>}
                        <button
                            disabled={form.processing}
                            className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            Créer la règle
                        </button>
                    </form>
                </div>

                <div className="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                        <thead className="bg-slate-50 text-left text-xs uppercase text-slate-500">
                            <tr><th className="px-4 py-3">Nom</th><th className="px-4 py-3">Déclencheur</th><th className="px-4 py-3">Contrepartie</th><th className="px-4 py-3">Priorité</th><th className="px-4 py-3 text-right">Actions</th></tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {rules.length === 0 && (
                                <tr>
                                    <td className="px-4 py-6 text-center text-sm text-slate-500" colSpan={5}>
                                        Aucune règle pour le moment.
                                    </td>
                                </tr>
                            )}
                            {rules.map((r) => (
                                <tr key={r.id}>
                                    <td className="px-4 py-3">{r.name}</td>
                                    <td className="px-4 py-3">{r.trigger_account?.code} ({r.trigger_direction})</td>
                                    <td className="px-4 py-3">{r.counterpart_account?.code} ({r.counterpart_direction})</td>
                                    <td className="px-4 py-3">{r.priority}</td>
                                    <td className="px-4 py-3 text-right">
                                        <button
                                            type="button"
                                            onClick={() => router.delete(route('settings.auto-counterpart-rules.destroy', r.id), { preserveScroll: true })}
                                            className="rounded border border-rose-300 px-2 py-1 text-xs text-rose-700"
                                        >
                                            Supprimer
                                        </button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
