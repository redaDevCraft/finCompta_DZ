import AdminLayout from '@/Layouts/AdminLayout';
import { Head, useForm } from '@inertiajs/react';

export default function PlanFeatures({ plans = [], catalogue = [], limitDefinitions = {} }) {
    return (
        <AdminLayout header="Fonctionnalités par plan">
            <Head title="Admin — Fonctionnalités par plan" />

            <div className="space-y-4">
                {plans.map((plan) => (
                    <PlanCard key={plan.id} plan={plan} catalogue={catalogue} limitDefinitions={limitDefinitions} />
                ))}
            </div>
        </AdminLayout>
    );
}

function PlanCard({ plan, catalogue, limitDefinitions }) {
    const { data, setData, processing, put } = useForm({
        features: Array.isArray(plan.features) ? plan.features : [],
        limits: plan.limits ?? {},
    });

    const toggle = (key) => {
        const current = new Set(data.features || []);
        if (current.has(key)) {
            current.delete(key);
        } else {
            current.add(key);
        }
        setData('features', Array.from(current));
    };

    const submit = (e) => {
        e.preventDefault();
        put(route('admin.plan-features.update', plan.id), {
            preserveScroll: true,
        });
    };

    const setLimit = (featureKey, rawValue) => {
        setData('limits', {
            ...(data.limits ?? {}),
            [featureKey]: {
                ...((data.limits ?? {})[featureKey] ?? {}),
                max: rawValue === '' ? null : Number(rawValue),
            },
        });
    };

    return (
        <form onSubmit={submit} className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="mb-4 flex items-center justify-between gap-3">
                <div>
                    <h3 className="text-base font-semibold text-slate-900">{plan.name}</h3>
                    <p className="text-xs text-slate-500">Code: {plan.code}</p>
                </div>
                <button
                    type="submit"
                    disabled={processing}
                    className="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                >
                    Enregistrer
                </button>
            </div>

            <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                {catalogue.map((item) => (
                    <div key={item.key} className="rounded-md border border-slate-200 px-3 py-2 text-sm">
                        <label className="inline-flex items-center gap-2">
                            <input
                                type="checkbox"
                                checked={(data.features || []).includes(item.key)}
                                onChange={() => toggle(item.key)}
                                className="h-4 w-4 rounded"
                            />
                            <span>{item.label}</span>
                        </label>
                        {limitDefinitions[item.key] && (data.features || []).includes(item.key) && (
                            <div className="mt-2">
                                <label className="mb-1 block text-xs text-slate-500">
                                    {limitDefinitions[item.key].label}
                                </label>
                                <input
                                    type="number"
                                    min={1}
                                    value={data.limits?.[item.key]?.max ?? ''}
                                    onChange={(e) => setLimit(item.key, e.target.value)}
                                    placeholder="Illimité si vide"
                                    className="w-full rounded-md border border-slate-300 px-2 py-1.5 text-xs"
                                />
                            </div>
                        )}
                    </div>
                ))}
            </div>
        </form>
    );
}

