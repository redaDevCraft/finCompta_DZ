import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Plus, Pencil, Trash2, X, Lock } from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useNotification } from '@/Context/NotificationContext';

function Modal({ open, onClose, title, children }) {
    if (!open) return null;
    return (
        <div className="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/50 px-4">
            <div className="w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-xl">
                <div className="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                    <h2 className="text-lg font-semibold text-slate-900">{title}</h2>
                    <button type="button" onClick={onClose} className="rounded-lg p-1 text-slate-500 hover:bg-slate-100">
                        <X className="h-5 w-5" />
                    </button>
                </div>
                <div className="p-6">{children}</div>
            </div>
        </div>
    );
}

function JournalForm({ journal, counterpartAccounts, types, onClose }) {
    const isEdit = !!journal;
    const { data, setData, post, put, processing, errors, reset } = useForm({
        code: journal?.code ?? '',
        label: journal?.label ?? '',
        label_ar: journal?.label_ar ?? '',
        type: journal?.type ?? 'misc',
        counterpart_account_id: journal?.counterpart_account_id ?? '',
        is_active: journal?.is_active ?? true,
        position: journal?.position ?? 0,
    });

    const submit = (e) => {
        e.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onClose();
            },
        };
        if (isEdit) {
            put(`/settings/journals/${journal.id}`, options);
        } else {
            post('/settings/journals', options);
        }
    };

    return (
        <form onSubmit={submit} className="space-y-4">
            <div className="grid grid-cols-2 gap-4">
                <div>
                    <label className="mb-1 block text-sm font-medium text-slate-700">Code</label>
                    <input
                        value={data.code}
                        onChange={(e) => setData('code', e.target.value.toUpperCase())}
                        disabled={isEdit && journal.is_system}
                        className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm uppercase disabled:bg-slate-100"
                        maxLength={10}
                    />
                    {errors.code && <p className="mt-1 text-sm text-rose-600">{errors.code}</p>}
                </div>
                <div>
                    <label className="mb-1 block text-sm font-medium text-slate-700">Type</label>
                    <select
                        value={data.type}
                        onChange={(e) => setData('type', e.target.value)}
                        disabled={isEdit && journal.is_system}
                        className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm disabled:bg-slate-100"
                    >
                        {types.map((t) => (
                            <option key={t.value} value={t.value}>{t.label}</option>
                        ))}
                    </select>
                </div>
            </div>

            <div>
                <label className="mb-1 block text-sm font-medium text-slate-700">Libellé</label>
                <input
                    value={data.label}
                    onChange={(e) => setData('label', e.target.value)}
                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                />
                {errors.label && <p className="mt-1 text-sm text-rose-600">{errors.label}</p>}
            </div>

            <div>
                <label className="mb-1 block text-sm font-medium text-slate-700">Libellé (arabe)</label>
                <input
                    value={data.label_ar ?? ''}
                    onChange={(e) => setData('label_ar', e.target.value)}
                    dir="rtl"
                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                />
            </div>

            <div>
                <label className="mb-1 block text-sm font-medium text-slate-700">
                    Compte de contrepartie (banque / caisse)
                </label>
                <select
                    value={data.counterpart_account_id ?? ''}
                    onChange={(e) => setData('counterpart_account_id', e.target.value || null)}
                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                >
                    <option value="">— Aucun —</option>
                    {counterpartAccounts.map((a) => (
                        <option key={a.id} value={a.id}>{a.code} — {a.label}</option>
                    ))}
                </select>
            </div>

            <div className="grid grid-cols-2 gap-4">
                <div>
                    <label className="mb-1 block text-sm font-medium text-slate-700">Position</label>
                    <input
                        type="number"
                        value={data.position}
                        onChange={(e) => setData('position', parseInt(e.target.value || '0', 10))}
                        min={0}
                        className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    />
                </div>
                <label className="flex items-end gap-2 text-sm text-slate-700">
                    <input
                        type="checkbox"
                        checked={data.is_active}
                        onChange={(e) => setData('is_active', e.target.checked)}
                    />
                    Actif
                </label>
            </div>

            <div className="flex justify-end gap-2 pt-2">
                <button type="button" onClick={onClose} className="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700">
                    Annuler
                </button>
                <button
                    type="submit"
                    disabled={processing}
                    className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-60"
                >
                    {isEdit ? 'Enregistrer' : 'Créer'}
                </button>
            </div>
        </form>
    );
}

export default function Journals({ journals, counterpartAccounts, types }) {
    const [modal, setModal] = useState({ open: false, journal: null });
    const { confirm } = useNotification();

    const destroy = async (journal) => {
        const ok = await confirm({
            title: 'Supprimer le journal',
            message: `Supprimer le journal ${journal.code} ?`,
            confirmLabel: 'Supprimer',
        });
        if (!ok) return;
        router.delete(`/settings/journals/${journal.id}`, { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Journaux comptables" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-slate-900">Journaux comptables</h1>
                        <p className="mt-1 text-sm text-slate-600">
                            Configurez vos journaux de ventes, achats, banque, caisse et opérations diverses.
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={() => setModal({ open: true, journal: null })}
                        className="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white"
                    >
                        <Plus className="h-4 w-4" />
                        Nouveau journal
                    </button>
                </div>

                <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                        <thead className="bg-slate-50 text-left text-xs font-medium uppercase text-slate-500">
                            <tr>
                                <th className="px-4 py-3">Code</th>
                                <th className="px-4 py-3">Libellé</th>
                                <th className="px-4 py-3">Type</th>
                                <th className="px-4 py-3">Écritures</th>
                                <th className="px-4 py-3">Statut</th>
                                <th className="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-200">
                            {journals.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="px-4 py-8 text-center text-slate-500">
                                        Aucun journal configuré.
                                    </td>
                                </tr>
                            )}
                            {journals.map((j) => (
                                <tr key={j.id}>
                                    <td className="px-4 py-3 font-mono font-semibold text-slate-900">
                                        <div className="flex items-center gap-2">
                                            {j.code}
                                            {j.is_system && <Lock className="h-3.5 w-3.5 text-slate-400" title="Journal système" />}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 text-slate-700">{j.label}</td>
                                    <td className="px-4 py-3">
                                        <span className="rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700">
                                            {j.type}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-slate-600">{j.entries_count}</td>
                                    <td className="px-4 py-3">
                                        <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${j.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'}`}>
                                            {j.is_active ? 'Actif' : 'Inactif'}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <div className="inline-flex gap-2">
                                            <button
                                                type="button"
                                                onClick={() => setModal({ open: true, journal: j })}
                                                className="rounded-lg border border-slate-300 p-1.5 text-slate-600 hover:bg-slate-50"
                                                title="Modifier"
                                            >
                                                <Pencil className="h-4 w-4" />
                                            </button>
                                            {!j.is_system && (
                                                <button
                                                    type="button"
                                                    onClick={() => destroy(j)}
                                                    disabled={j.entries_count > 0}
                                                    className="rounded-lg border border-rose-200 p-1.5 text-rose-600 hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-40"
                                                    title="Supprimer"
                                                >
                                                    <Trash2 className="h-4 w-4" />
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

            <Modal
                open={modal.open}
                onClose={() => setModal({ open: false, journal: null })}
                title={modal.journal ? `Modifier ${modal.journal.code}` : 'Nouveau journal'}
            >
                {modal.open && (
                    <JournalForm
                        journal={modal.journal}
                        counterpartAccounts={counterpartAccounts}
                        types={types}
                        onClose={() => setModal({ open: false, journal: null })}
                    />
                )}
            </Modal>
        </AuthenticatedLayout>
    );
}
