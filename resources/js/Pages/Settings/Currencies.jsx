import { Head, router, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function Currencies({ baseCurrencyCode, currencies = [], rates = [] }) {
    const currencyForm = useForm({
        code: '',
        name: '',
        decimals: 2,
        is_active: true,
    });

    const rateForm = useForm({
        currency_id: '',
        rate_date: new Date().toISOString().slice(0, 10),
        rate: '',
    });

    return (
        <AuthenticatedLayout header="Devises et taux de change">
            <Head title="Devises et taux de change" />

            <div className="space-y-6">
                <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h1 className="text-xl font-semibold text-slate-900">Devises</h1>
                    <p className="mt-1 text-sm text-slate-600">Devise de base société: {baseCurrencyCode}</p>

                    <form
                        className="mt-4 grid gap-3 md:grid-cols-5"
                        onSubmit={(e) => {
                            e.preventDefault();
                            currencyForm.post(route('settings.currencies.store'));
                        }}
                    >
                        <input className="rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Code (EUR)" maxLength={3} value={currencyForm.data.code} onChange={(e) => currencyForm.setData('code', e.target.value.toUpperCase())} required />
                        <input className="rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Nom" value={currencyForm.data.name} onChange={(e) => currencyForm.setData('name', e.target.value)} required />
                        <input className="rounded-lg border border-slate-300 px-3 py-2 text-sm" type="number" min="0" max="4" value={currencyForm.data.decimals} onChange={(e) => currencyForm.setData('decimals', Number(e.target.value || 2))} required />
                        <label className="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <input type="checkbox" checked={currencyForm.data.is_active} onChange={(e) => currencyForm.setData('is_active', e.target.checked)} />
                            Active
                        </label>
                        <button
                            className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60"
                            type="submit"
                            disabled={currencyForm.processing}
                        >
                            Ajouter
                        </button>
                    </form>

                    <div className="mt-4 space-y-2">
                        {currencies.map((c) => (
                            <div key={c.id} className="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                <div>{c.code} - {c.name} {c.is_base ? '(base)' : ''}</div>
                                <div className="flex items-center gap-2">
                                    <button type="button" className="rounded border border-slate-300 px-2 py-1" onClick={() => router.patch(route('settings.currencies.update', c.id), { name: c.name, decimals: c.decimals, is_active: c.is_active })}>Maj rapide</button>
                                    <button type="button" className="rounded border border-rose-300 px-2 py-1 text-rose-700" onClick={() => router.delete(route('settings.currencies.destroy', c.id))}>Supprimer</button>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>

                <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 className="text-xl font-semibold text-slate-900">Taux de change</h2>
                    <form
                        className="mt-4 grid gap-3 md:grid-cols-4"
                        onSubmit={(e) => {
                            e.preventDefault();
                            rateForm.post(route('settings.exchange-rates.store'));
                        }}
                    >
                        <select className="rounded-lg border border-slate-300 px-3 py-2 text-sm" value={rateForm.data.currency_id} onChange={(e) => rateForm.setData('currency_id', e.target.value)} required>
                            <option value="">Devise</option>
                            {currencies.filter((c) => !c.is_base).map((c) => (
                                <option key={c.id} value={c.id}>{c.code}</option>
                            ))}
                        </select>
                        <input className="rounded-lg border border-slate-300 px-3 py-2 text-sm" type="date" value={rateForm.data.rate_date} onChange={(e) => rateForm.setData('rate_date', e.target.value)} required />
                        <input className="rounded-lg border border-slate-300 px-3 py-2 text-sm" type="number" step="0.00000001" min="0" value={rateForm.data.rate} onChange={(e) => rateForm.setData('rate', e.target.value)} required />
                        <button
                            className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60"
                            type="submit"
                            disabled={rateForm.processing}
                        >
                            Enregistrer taux
                        </button>
                    </form>

                    <div className="mt-4 space-y-2">
                        {rates.map((r) => (
                            <div key={r.id} className="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                <div>{r.rate_date} - {r.currency?.code} = {r.rate} DZD</div>
                                <button type="button" className="rounded border border-rose-300 px-2 py-1 text-rose-700" onClick={() => router.delete(route('settings.exchange-rates.destroy', r.id))}>Supprimer</button>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
