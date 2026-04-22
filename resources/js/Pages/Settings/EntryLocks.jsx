import { Head, router, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function EntryLocks({ lock }) {
    const dateForm = useForm({
        locked_until_date: lock?.locked_until_date || '',
        password: '',
    });

    const passwordForm = useForm({
        password: '',
    });

    return (
        <AuthenticatedLayout>
            <Head title="Verrouillage des écritures" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-semibold text-slate-900">Verrouillage des écritures</h1>
                    <p className="mt-1 text-sm text-slate-600">
                        Bloquez la modification/suppression des écritures jusqu&apos;à une date donnée.
                    </p>
                </div>

                <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 className="text-lg font-semibold text-slate-900">Mot de passe de verrouillage</h2>
                    <form
                        className="mt-4 flex max-w-md items-end gap-3"
                        onSubmit={(e) => {
                            e.preventDefault();
                            passwordForm.post('/settings/entry-locks/password', { preserveScroll: true });
                        }}
                    >
                        <div className="flex-1">
                            <label className="mb-1 block text-sm text-slate-700">Nouveau mot de passe</label>
                            <input
                                type="password"
                                value={passwordForm.data.password}
                                onChange={(e) => passwordForm.setData('password', e.target.value)}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            />
                        </div>
                        <button className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white">
                            Enregistrer
                        </button>
                    </form>
                </div>

                <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 className="text-lg font-semibold text-slate-900">Verrouillage par date</h2>
                    <p className="mt-1 text-sm text-slate-600">
                        Date actuelle verrouillée: <strong>{lock?.locked_until_date || 'Aucune'}</strong>
                    </p>

                    <form
                        className="mt-4 grid max-w-3xl gap-4 md:grid-cols-3"
                        onSubmit={(e) => {
                            e.preventDefault();
                            dateForm.post('/settings/entry-locks/date', { preserveScroll: true });
                        }}
                    >
                        <div>
                            <label className="mb-1 block text-sm text-slate-700">Verrouiller jusqu&apos;au</label>
                            <input
                                type="date"
                                value={dateForm.data.locked_until_date}
                                onChange={(e) => dateForm.setData('locked_until_date', e.target.value)}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            />
                        </div>
                        {lock?.has_password && (
                            <div>
                                <label className="mb-1 block text-sm text-slate-700">Mot de passe</label>
                                <input
                                    type="password"
                                    value={dateForm.data.password}
                                    onChange={(e) => dateForm.setData('password', e.target.value)}
                                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                />
                            </div>
                        )}
                        <div className="flex items-end gap-2">
                            <button className="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white">
                                Appliquer
                            </button>
                            <button
                                type="button"
                                onClick={() => {
                                    router.post('/settings/entry-locks/date/clear', {
                                        password: dateForm.data.password || undefined,
                                    }, { preserveScroll: true });
                                }}
                                className="rounded-lg border border-rose-300 px-4 py-2 text-sm font-medium text-rose-700"
                            >
                                Retirer
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
