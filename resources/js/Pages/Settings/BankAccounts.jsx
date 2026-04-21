import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Plus, Pencil, Trash2, X, Building2 } from 'lucide-react';
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

function BankForm({ bank, glAccounts, onClose }) {
    const isEdit = !!bank;
    const { data, setData, post, put, processing, errors, reset } = useForm({
        bank_name: bank?.bank_name ?? '',
        account_number: bank?.account_number ?? '',
        currency: bank?.currency ?? 'DZD',
        gl_account_id: bank?.gl_account?.id ?? '',
        is_active: bank?.is_active ?? true,
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
            put(`/settings/bank-accounts/${bank.id}`, options);
        } else {
            post('/settings/bank-accounts', options);
        }
    };

    return (
        <form onSubmit={submit} className="space-y-4">
            <div>
                <label className="mb-1 block text-sm font-medium text-slate-700">Nom de la banque</label>
                <input
                    value={data.bank_name}
                    onChange={(e) => setData('bank_name', e.target.value)}
                    placeholder="BNA, BEA, CPA, BADR…"
                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                />
                {errors.bank_name && <p className="mt-1 text-sm text-rose-600">{errors.bank_name}</p>}
            </div>

            <div className="grid grid-cols-2 gap-4">
                <div>
                    <label className="mb-1 block text-sm font-medium text-slate-700">Numéro de compte / RIB</label>
                    <input
                        value={data.account_number ?? ''}
                        onChange={(e) => setData('account_number', e.target.value)}
                        className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-mono"
                    />
                </div>
                <div>
                    <label className="mb-1 block text-sm font-medium text-slate-700">Devise</label>
                    <select
                        value={data.currency}
                        onChange={(e) => setData('currency', e.target.value)}
                        className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    >
                        <option value="DZD">DZD</option>
                        <option value="EUR">EUR</option>
                        <option value="USD">USD</option>
                    </select>
                </div>
            </div>

            <div>
                <label className="mb-1 block text-sm font-medium text-slate-700">Compte comptable (512xxx)</label>
                <select
                    value={data.gl_account_id}
                    onChange={(e) => setData('gl_account_id', e.target.value)}
                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                >
                    <option value="">— Sélectionner —</option>
                    {glAccounts.map((a) => (
                        <option key={a.id} value={a.id}>{a.code} — {a.label}</option>
                    ))}
                </select>
                {errors.gl_account_id && <p className="mt-1 text-sm text-rose-600">{errors.gl_account_id}</p>}
            </div>

            <label className="flex items-center gap-2 text-sm text-slate-700">
                <input
                    type="checkbox"
                    checked={data.is_active}
                    onChange={(e) => setData('is_active', e.target.checked)}
                />
                Compte actif
            </label>

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

export default function BankAccounts({ bankAccounts, glAccounts }) {
    const [modal, setModal] = useState({ open: false, bank: null });
    const { confirm } = useNotification();

    const destroy = async (bank) => {
        const ok = await confirm({
            title: 'Supprimer le compte bancaire',
            message: `Supprimer le compte "${bank.bank_name}" ?`,
            confirmLabel: 'Supprimer',
        });
        if (!ok) return;
        router.delete(`/settings/bank-accounts/${bank.id}`, { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Comptes bancaires" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-slate-900">Comptes bancaires</h1>
                        <p className="mt-1 text-sm text-slate-600">
                            Gérez les comptes bancaires utilisés pour vos rapprochements et imports.
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={() => setModal({ open: true, bank: null })}
                        className="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white"
                    >
                        <Plus className="h-4 w-4" />
                        Nouveau compte
                    </button>
                </div>

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {bankAccounts.length === 0 && (
                        <div className="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500 md:col-span-2 xl:col-span-3">
                            Aucun compte bancaire configuré.
                        </div>
                    )}
                    {bankAccounts.map((bank) => (
                        <div key={bank.id} className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <div className="flex items-start justify-between">
                                <div className="flex items-center gap-3">
                                    <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                                        <Building2 className="h-5 w-5" />
                                    </div>
                                    <div>
                                        <h3 className="font-semibold text-slate-900">{bank.bank_name}</h3>
                                        <p className="text-xs text-slate-500">{bank.currency}</p>
                                    </div>
                                </div>
                                <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${bank.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'}`}>
                                    {bank.is_active ? 'Actif' : 'Inactif'}
                                </span>
                            </div>

                            <dl className="mt-4 space-y-2 text-sm">
                                <div>
                                    <dt className="text-xs font-medium uppercase tracking-wide text-slate-500">N° compte</dt>
                                    <dd className="font-mono text-slate-800">{bank.account_number || '—'}</dd>
                                </div>
                                {bank.gl_account && (
                                    <div>
                                        <dt className="text-xs font-medium uppercase tracking-wide text-slate-500">Compte SCF</dt>
                                        <dd className="text-slate-800">
                                            <span className="font-mono">{bank.gl_account.code}</span> — {bank.gl_account.label}
                                        </dd>
                                    </div>
                                )}
                            </dl>

                            <div className="mt-4 flex gap-2 border-t border-slate-100 pt-4">
                                <button
                                    type="button"
                                    onClick={() => setModal({ open: true, bank })}
                                    className="inline-flex flex-1 items-center justify-center gap-2 rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50"
                                >
                                    <Pencil className="h-4 w-4" />
                                    Modifier
                                </button>
                                <button
                                    type="button"
                                    onClick={() => destroy(bank)}
                                    className="rounded-lg border border-rose-200 px-3 py-2 text-rose-600 hover:bg-rose-50"
                                    title="Supprimer"
                                >
                                    <Trash2 className="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    ))}
                </div>
            </div>

            <Modal
                open={modal.open}
                onClose={() => setModal({ open: false, bank: null })}
                title={modal.bank ? `Modifier ${modal.bank.bank_name}` : 'Nouveau compte bancaire'}
            >
                {modal.open && (
                    <BankForm
                        bank={modal.bank}
                        glAccounts={glAccounts}
                        onClose={() => setModal({ open: false, bank: null })}
                    />
                )}
            </Modal>
        </AuthenticatedLayout>
    );
}
