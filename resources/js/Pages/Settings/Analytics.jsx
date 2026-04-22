import { Head, router, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

function FormCard({ title, children }) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 className="text-lg font-semibold text-slate-900">{title}</h2>
            <div className="mt-4">{children}</div>
        </div>
    );
}

export default function Analytics({ axes = [], sections = [] }) {
    const axisForm = useForm({
        code: '',
        name: '',
        is_active: true,
    });

    const sectionForm = useForm({
        analytic_axis_id: '',
        code: '',
        name: '',
        is_active: true,
    });

    return (
        <AuthenticatedLayout>
            <Head title="Comptabilité analytique" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-semibold text-slate-900">Comptabilité analytique</h1>
                    <p className="mt-1 text-sm text-slate-600">
                        Configurez les axes analytiques et sections (centres de coûts/projets).
                    </p>
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <FormCard title="Nouvel axe">
                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                axisForm.post('/settings/analytics/axes', { preserveScroll: true });
                            }}
                            className="space-y-3"
                        >
                            <input
                                value={axisForm.data.code}
                                onChange={(e) => axisForm.setData('code', e.target.value.toUpperCase())}
                                placeholder="Code (ex: COST)"
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            />
                            <input
                                value={axisForm.data.name}
                                onChange={(e) => axisForm.setData('name', e.target.value)}
                                placeholder="Nom de l'axe"
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            />
                            <label className="inline-flex items-center gap-2 text-sm text-slate-700">
                                <input
                                    type="checkbox"
                                    checked={axisForm.data.is_active}
                                    onChange={(e) => axisForm.setData('is_active', e.target.checked)}
                                />
                                Actif
                            </label>
                            <button
                                type="submit"
                                disabled={axisForm.processing}
                                className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white"
                            >
                                Ajouter l'axe
                            </button>
                        </form>
                    </FormCard>

                    <FormCard title="Nouvelle section">
                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                sectionForm.post('/settings/analytics/sections', { preserveScroll: true });
                            }}
                            className="space-y-3"
                        >
                            <select
                                value={sectionForm.data.analytic_axis_id}
                                onChange={(e) => sectionForm.setData('analytic_axis_id', e.target.value)}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            >
                                <option value="">Choisir un axe</option>
                                {axes.map((axis) => (
                                    <option key={axis.id} value={axis.id}>
                                        {axis.code} - {axis.name}
                                    </option>
                                ))}
                            </select>
                            <input
                                value={sectionForm.data.code}
                                onChange={(e) => sectionForm.setData('code', e.target.value.toUpperCase())}
                                placeholder="Code section (ex: CC001)"
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            />
                            <input
                                value={sectionForm.data.name}
                                onChange={(e) => sectionForm.setData('name', e.target.value)}
                                placeholder="Nom section"
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            />
                            <label className="inline-flex items-center gap-2 text-sm text-slate-700">
                                <input
                                    type="checkbox"
                                    checked={sectionForm.data.is_active}
                                    onChange={(e) => sectionForm.setData('is_active', e.target.checked)}
                                />
                                Active
                            </label>
                            <button
                                type="submit"
                                disabled={sectionForm.processing}
                                className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white"
                            >
                                Ajouter la section
                            </button>
                        </form>
                    </FormCard>
                </div>

                <FormCard title="Axes configurés">
                    <div className="space-y-2 text-sm text-slate-700">
                        {axes.length === 0 && <p>Aucun axe analytique configuré.</p>}
                        {axes.map((axis) => (
                            <div key={axis.id} className="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2">
                                <div>
                                    <span className="font-mono font-semibold">{axis.code}</span> - {axis.name}
                                </div>
                                <div className="flex items-center gap-2">
                                    <button
                                        type="button"
                                        onClick={() => {
                                            router.put(`/settings/analytics/axes/${axis.id}`, {
                                                code: axis.code,
                                                name: axis.name,
                                                is_active: !axis.is_active,
                                            }, { preserveScroll: true });
                                        }}
                                        className="rounded border border-slate-300 px-2 py-1 text-xs"
                                    >
                                        {axis.is_active ? 'Désactiver' : 'Activer'}
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => router.delete(`/settings/analytics/axes/${axis.id}`, { preserveScroll: true })}
                                        className="rounded border border-rose-300 px-2 py-1 text-xs text-rose-700"
                                    >
                                        Supprimer
                                    </button>
                                </div>
                            </div>
                        ))}
                    </div>
                </FormCard>

                <FormCard title="Sections configurées">
                    <div className="space-y-2 text-sm text-slate-700">
                        {sections.length === 0 && <p>Aucune section analytique configurée.</p>}
                        {sections.map((section) => (
                            <div key={section.id} className="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2">
                                <div>
                                    <span className="font-mono font-semibold">{section.code}</span> - {section.name}
                                    {' '}({section.axis?.code ?? 'N/A'})
                                </div>
                                <div className="flex items-center gap-2">
                                    <button
                                        type="button"
                                        onClick={() => {
                                            router.put(`/settings/analytics/sections/${section.id}`, {
                                                analytic_axis_id: section.analytic_axis_id,
                                                code: section.code,
                                                name: section.name,
                                                is_active: !section.is_active,
                                            }, { preserveScroll: true });
                                        }}
                                        className="rounded border border-slate-300 px-2 py-1 text-xs"
                                    >
                                        {section.is_active ? 'Désactiver' : 'Activer'}
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => router.delete(`/settings/analytics/sections/${section.id}`, { preserveScroll: true })}
                                        className="rounded border border-rose-300 px-2 py-1 text-xs text-rose-700"
                                    >
                                        Supprimer
                                    </button>
                                </div>
                            </div>
                        ))}
                    </div>
                </FormCard>
            </div>
        </AuthenticatedLayout>
    );
}
