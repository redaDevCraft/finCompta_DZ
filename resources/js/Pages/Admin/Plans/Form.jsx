import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { Plus, Save, Trash2 } from 'lucide-react';

function defaultValues(plan) {
    if (!plan) {
        return {
            code: '',
            segment: 'sme',
            name: '',
            tagline: '',
            monthly_price_dzd: 0,
            yearly_price_dzd: 0,
            trial_days: 3,
            max_companies: 1,
            max_users: '',
            max_invoices_per_month: '',
            max_documents_per_month: '',
            features: [''],
            sort_order: 100,
            is_active: true,
            is_default: false,
        };
    }

    return {
        code: plan.code ?? '',
        segment: plan.segment ?? 'sme',
        name: plan.name ?? '',
        tagline: plan.tagline ?? '',
        monthly_price_dzd: plan.monthly_price_dzd ?? 0,
        yearly_price_dzd: plan.yearly_price_dzd ?? 0,
        trial_days: plan.trial_days ?? 3,
        max_companies: plan.max_companies ?? '',
        max_users: plan.max_users ?? '',
        max_invoices_per_month: plan.max_invoices_per_month ?? '',
        max_documents_per_month: plan.max_documents_per_month ?? '',
        features: Array.isArray(plan.features) && plan.features.length ? plan.features : [''],
        sort_order: plan.sort_order ?? 100,
        is_active: Boolean(plan.is_active),
        is_default: Boolean(plan.is_default),
    };
}

export default function PlanForm({ plan }) {
    const isEdit = Boolean(plan);

    const { data, setData, post, put, processing, errors } = useForm(defaultValues(plan));

    function submit(e) {
        e.preventDefault();

        const payload = {
            ...data,
            max_companies: data.max_companies === '' ? null : Number(data.max_companies),
            max_users: data.max_users === '' ? null : Number(data.max_users),
            max_invoices_per_month:
                data.max_invoices_per_month === '' ? null : Number(data.max_invoices_per_month),
            max_documents_per_month:
                data.max_documents_per_month === '' ? null : Number(data.max_documents_per_month),
            features: (data.features || []).filter((f) => f && f.trim() !== ''),
        };

        if (isEdit) {
            put(route('admin.plans.update', plan.id), { data: payload });
        } else {
            post(route('admin.plans.store'), { data: payload });
        }
    }

    function updateFeature(idx, value) {
        const next = [...data.features];
        next[idx] = value;
        setData('features', next);
    }

    function addFeature() {
        setData('features', [...(data.features || []), '']);
    }

    function removeFeature(idx) {
        const next = [...data.features];
        next.splice(idx, 1);
        setData('features', next.length ? next : ['']);
    }

    return (
        <AdminLayout header={isEdit ? `Modifier ${plan.name}` : 'Nouveau plan'}>
            <Head title={isEdit ? `Admin — ${plan.name}` : 'Admin — Nouveau plan'} />

            <form onSubmit={submit} className="mx-auto max-w-3xl space-y-6">
                <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 className="mb-4 text-sm font-semibold text-slate-900">Général</h2>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Code *" error={errors.code}>
                            <input
                                type="text"
                                value={data.code}
                                onChange={(e) => setData('code', e.target.value)}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                placeholder="starter, pro, ent…"
                            />
                        </Field>
                        <Field label="Nom *" error={errors.name}>
                            <input
                                type="text"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            />
                        </Field>
                        <Field label="Segment *" error={errors.segment}>
                            <select
                                value={data.segment}
                                onChange={(e) => setData('segment', e.target.value)}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            >
                                <option value="solo">Solo (Auto-entrepreneur / TPE)</option>
                                <option value="sme">SME (PME / SARL)</option>
                                <option value="firm">Firm (Cabinet comptable)</option>
                            </select>
                        </Field>
                        <Field label="Slogan" error={errors.tagline} className="sm:col-span-2">
                            <input
                                type="text"
                                value={data.tagline ?? ''}
                                onChange={(e) => setData('tagline', e.target.value)}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            />
                        </Field>
                    </div>
                </div>

                <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 className="mb-4 text-sm font-semibold text-slate-900">Tarification (DZD)</h2>
                    <div className="grid gap-4 sm:grid-cols-3">
                        <Field label="Prix mensuel *" error={errors.monthly_price_dzd}>
                            <input
                                type="number"
                                min="0"
                                value={data.monthly_price_dzd}
                                onChange={(e) => setData('monthly_price_dzd', Number(e.target.value))}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            />
                        </Field>
                        <Field label="Prix annuel *" error={errors.yearly_price_dzd}>
                            <input
                                type="number"
                                min="0"
                                value={data.yearly_price_dzd}
                                onChange={(e) => setData('yearly_price_dzd', Number(e.target.value))}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            />
                        </Field>
                        <Field label="Essai (jours) *" error={errors.trial_days}>
                            <input
                                type="number"
                                min="0"
                                value={data.trial_days}
                                onChange={(e) => setData('trial_days', Number(e.target.value))}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            />
                        </Field>
                    </div>
                </div>

                <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 className="mb-4 text-sm font-semibold text-slate-900">
                        Limites (vide = illimité)
                    </h2>
                    <div className="grid gap-4 sm:grid-cols-3">
                        <Field label="Max sociétés" error={errors.max_companies}>
                            <input
                                type="number"
                                min="1"
                                value={data.max_companies ?? ''}
                                onChange={(e) => setData('max_companies', e.target.value)}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            />
                        </Field>
                        <Field label="Max utilisateurs" error={errors.max_users}>
                            <input
                                type="number"
                                min="1"
                                value={data.max_users ?? ''}
                                onChange={(e) => setData('max_users', e.target.value)}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            />
                        </Field>
                        <Field label="Max factures / mois" error={errors.max_invoices_per_month}>
                            <input
                                type="number"
                                min="0"
                                value={data.max_invoices_per_month ?? ''}
                                onChange={(e) => setData('max_invoices_per_month', e.target.value)}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            />
                        </Field>
                        <Field label="Max documents / mois" error={errors.max_documents_per_month}>
                            <input
                                type="number"
                                min="0"
                                value={data.max_documents_per_month ?? ''}
                                onChange={(e) => setData('max_documents_per_month', e.target.value)}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            />
                        </Field>
                    </div>
                </div>

                <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 className="mb-4 text-sm font-semibold text-slate-900">Fonctionnalités (bullet list)</h2>
                    <div className="space-y-2">
                        {data.features.map((f, idx) => (
                            <div key={idx} className="flex gap-2">
                                <input
                                    type="text"
                                    value={f}
                                    onChange={(e) => updateFeature(idx, e.target.value)}
                                    className="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                    placeholder="Ex: Rapports TVA G50"
                                />
                                <button
                                    type="button"
                                    onClick={() => removeFeature(idx)}
                                    className="rounded-md border border-rose-200 px-2 text-rose-600 hover:bg-rose-50"
                                >
                                    <Trash2 className="h-4 w-4" />
                                </button>
                            </div>
                        ))}
                    </div>
                    <button
                        type="button"
                        onClick={addFeature}
                        className="mt-3 inline-flex items-center gap-1 text-xs font-medium text-indigo-600 hover:text-indigo-700"
                    >
                        <Plus className="h-3.5 w-3.5" /> Ajouter une fonctionnalité
                    </button>
                </div>

                <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 className="mb-4 text-sm font-semibold text-slate-900">Affichage</h2>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Ordre de tri *" error={errors.sort_order}>
                            <input
                                type="number"
                                value={data.sort_order}
                                onChange={(e) => setData('sort_order', Number(e.target.value))}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            />
                        </Field>
                        <label className="mt-6 flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                checked={data.is_active}
                                onChange={(e) => setData('is_active', e.target.checked)}
                                className="h-4 w-4 rounded"
                            />
                            Plan actif (visible dans l'app)
                        </label>
                        <label className="mt-6 flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                checked={data.is_default}
                                onChange={(e) => setData('is_default', e.target.checked)}
                                className="h-4 w-4 rounded"
                            />
                            Plan recommandé par défaut
                        </label>
                    </div>
                </div>

                <div className="flex justify-end gap-2">
                    <Link
                        href={route('admin.plans.index')}
                        className="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                    >
                        Annuler
                    </Link>
                    <button
                        type="submit"
                        disabled={processing}
                        className="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                    >
                        <Save className="h-4 w-4" />
                        {isEdit ? 'Enregistrer' : 'Créer le plan'}
                    </button>
                </div>
            </form>
        </AdminLayout>
    );
}

function Field({ label, children, error, className = '' }) {
    return (
        <div className={className}>
            <label className="mb-1 block text-xs font-medium text-slate-600">{label}</label>
            {children}
            {error && <p className="mt-1 text-xs text-rose-600">{error}</p>}
        </div>
    );
}
