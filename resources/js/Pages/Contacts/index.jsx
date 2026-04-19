import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Eye, Pencil, Plus, Trash2, X } from 'lucide-react';

const TYPE_LABELS = {
    client: 'Client',
    supplier: 'Fournisseur',
    both: 'Client & Fournisseur',
};

const ENTITY_LABELS = {
    individual: 'Personne physique',
    enterprise: 'Entreprise',
};

function emptyForm() {
    return {
        type: 'client',
        entity_type: 'enterprise',
        display_name: '',
        raison_sociale: '',
        nif: '',
        nis: '',
        rc: '',
        email: '',
        phone: '',
        address_line1: '',
        address_wilaya: '',
    };
}

function ContactForm({ initial, onClose, isEdit }) {
    const { data, setData, post, put, processing, errors, reset } = useForm(
        initial ?? emptyForm()
    );

    const submit = (e) => {
        e.preventDefault();
        if (isEdit && initial?.id) {
            put(`/contacts/${initial.id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    onClose();
                    reset();
                },
            });
        } else {
            post('/contacts', {
                preserveScroll: true,
                onSuccess: () => {
                    onClose();
                    reset();
                },
            });
        }
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div className="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white shadow-xl">
                <div className="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                    <h3 className="text-base font-semibold text-slate-900">
                        {isEdit ? 'Modifier le contact' : 'Nouveau contact'}
                    </h3>
                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-md p-2 text-slate-500 hover:bg-slate-100"
                    >
                        <X className="h-5 w-5" />
                    </button>
                </div>

                <form onSubmit={submit} className="space-y-4 px-5 py-5">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">
                                Type *
                            </label>
                            <select
                                value={data.type}
                                onChange={(e) => setData('type', e.target.value)}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            >
                                <option value="client">Client</option>
                                <option value="supplier">Fournisseur</option>
                                <option value="both">Les deux</option>
                            </select>
                            {errors.type && <p className="mt-1 text-xs text-rose-600">{errors.type}</p>}
                        </div>

                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">
                                Forme juridique *
                            </label>
                            <select
                                value={data.entity_type}
                                onChange={(e) => setData('entity_type', e.target.value)}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            >
                                <option value="enterprise">Entreprise</option>
                                <option value="individual">Personne physique</option>
                            </select>
                            {errors.entity_type && <p className="mt-1 text-xs text-rose-600">{errors.entity_type}</p>}
                        </div>
                    </div>

                    <div>
                        <label className="mb-1 block text-sm font-medium text-slate-700">
                            Nom affiché *
                        </label>
                        <input
                            type="text"
                            value={data.display_name}
                            onChange={(e) => setData('display_name', e.target.value)}
                            className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            required
                        />
                        {errors.display_name && <p className="mt-1 text-xs text-rose-600">{errors.display_name}</p>}
                    </div>

                    <div>
                        <label className="mb-1 block text-sm font-medium text-slate-700">
                            Raison sociale
                        </label>
                        <input
                            type="text"
                            value={data.raison_sociale ?? ''}
                            onChange={(e) => setData('raison_sociale', e.target.value)}
                            className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                        />
                    </div>

                    <div className="grid gap-4 sm:grid-cols-3">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">
                                NIF
                            </label>
                            <input
                                type="text"
                                value={data.nif ?? ''}
                                onChange={(e) => setData('nif', e.target.value)}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            />
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">
                                NIS
                            </label>
                            <input
                                type="text"
                                value={data.nis ?? ''}
                                onChange={(e) => setData('nis', e.target.value)}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            />
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">
                                RC
                            </label>
                            <input
                                type="text"
                                value={data.rc ?? ''}
                                onChange={(e) => setData('rc', e.target.value)}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            />
                        </div>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">
                                Email
                            </label>
                            <input
                                type="email"
                                value={data.email ?? ''}
                                onChange={(e) => setData('email', e.target.value)}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            />
                            {errors.email && <p className="mt-1 text-xs text-rose-600">{errors.email}</p>}
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">
                                Téléphone
                            </label>
                            <input
                                type="text"
                                value={data.phone ?? ''}
                                onChange={(e) => setData('phone', e.target.value)}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            />
                        </div>
                    </div>

                    <div>
                        <label className="mb-1 block text-sm font-medium text-slate-700">
                            Adresse
                        </label>
                        <input
                            type="text"
                            value={data.address_line1 ?? ''}
                            onChange={(e) => setData('address_line1', e.target.value)}
                            className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                        />
                    </div>

                    <div>
                        <label className="mb-1 block text-sm font-medium text-slate-700">
                            Wilaya
                        </label>
                        <input
                            type="text"
                            value={data.address_wilaya ?? ''}
                            onChange={(e) => setData('address_wilaya', e.target.value)}
                            className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                        />
                    </div>

                    <div className="flex justify-end gap-2 border-t border-slate-100 pt-4">
                        <button
                            type="button"
                            onClick={onClose}
                            className="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                        >
                            Annuler
                        </button>
                        <button
                            type="submit"
                            disabled={processing}
                            className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                        >
                            {isEdit ? 'Enregistrer' : 'Créer'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

export default function Index({ contacts, filters = {} }) {
    const { flash, errors } = usePage().props;
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const [type, setType] = useState(filters.type ?? '');

    const applyFilter = (value) => {
        setType(value);
        router.get(
            '/contacts',
            { type: value || undefined },
            { preserveState: true, replace: true }
        );
    };

    const openCreate = () => {
        setEditing(null);
        setModalOpen(true);
    };

    const openEdit = (contact) => {
        setEditing(contact);
        setModalOpen(true);
    };

    const deleteContact = (contact) => {
        if (!confirm(`Supprimer le contact « ${contact.display_name} » ?`)) return;
        router.delete(`/contacts/${contact.id}`, { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout header="Tiers">
            <Head title="Tiers" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold text-slate-900">Contacts / Tiers</h1>
                        <p className="mt-1 text-sm text-slate-600">
                            Clients et fournisseurs
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={openCreate}
                        className="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-indigo-700"
                    >
                        <Plus className="h-4 w-4" />
                        Nouveau contact
                    </button>
                </div>

                {flash?.success && (
                    <div className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                        {flash.success}
                    </div>
                )}
                {errors?.delete && (
                    <div className="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                        {errors.delete}
                    </div>
                )}

                <div className="flex flex-wrap gap-2">
                    {[
                        { value: '', label: 'Tous' },
                        { value: 'client', label: 'Clients' },
                        { value: 'supplier', label: 'Fournisseurs' },
                        { value: 'both', label: 'Les deux' },
                    ].map((o) => (
                        <button
                            key={o.value || 'all'}
                            type="button"
                            onClick={() => applyFilter(o.value)}
                            className={`rounded-full px-3 py-1.5 text-xs font-medium transition ${
                                type === o.value
                                    ? 'bg-indigo-600 text-white'
                                    : 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50'
                            }`}
                        >
                            {o.label}
                        </button>
                    ))}
                </div>

                <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-200 text-sm">
                            <thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th className="px-4 py-3 text-left font-semibold">Nom</th>
                                    <th className="px-4 py-3 text-left font-semibold">Type</th>
                                    <th className="px-4 py-3 text-left font-semibold">NIF</th>
                                    <th className="px-4 py-3 text-left font-semibold">Email</th>
                                    <th className="px-4 py-3 text-left font-semibold">Téléphone</th>
                                    <th className="px-4 py-3 text-right font-semibold">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {contacts?.data?.length ? (
                                    contacts.data.map((c) => (
                                        <tr key={c.id} className="hover:bg-slate-50">
                                            <td className="px-4 py-3">
                                                <Link
                                                    href={route('contacts.show', c.id)}
                                                    className="font-medium text-slate-900 hover:text-indigo-700 hover:underline"
                                                >
                                                    {c.display_name}
                                                </Link>
                                                {c.raison_sociale && c.raison_sociale !== c.display_name && (
                                                    <div className="text-xs text-slate-500">
                                                        {c.raison_sociale}
                                                    </div>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-slate-700">
                                                {TYPE_LABELS[c.type] ?? c.type}
                                                <div className="text-xs text-slate-400">
                                                    {ENTITY_LABELS[c.entity_type] ?? ''}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 font-mono text-xs text-slate-600">
                                                {c.nif || '—'}
                                            </td>
                                            <td className="px-4 py-3 text-slate-700">
                                                {c.email || '—'}
                                            </td>
                                            <td className="px-4 py-3 text-slate-700">
                                                {c.phone || '—'}
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <div className="flex items-center justify-end gap-2">
                                                    <Link
                                                        href={route('contacts.show', c.id)}
                                                        className="rounded-md border border-slate-300 px-2 py-1 text-xs text-slate-700 hover:bg-slate-50"
                                                        title="Voir la fiche"
                                                    >
                                                        <Eye className="h-3.5 w-3.5" />
                                                    </Link>
                                                    <button
                                                        type="button"
                                                        onClick={() => openEdit(c)}
                                                        className="rounded-md border border-slate-300 px-2 py-1 text-xs text-slate-700 hover:bg-slate-50"
                                                        title="Modifier"
                                                    >
                                                        <Pencil className="h-3.5 w-3.5" />
                                                    </button>
                                                    <button
                                                        type="button"
                                                        onClick={() => deleteContact(c)}
                                                        className="rounded-md border border-rose-200 px-2 py-1 text-xs text-rose-700 hover:bg-rose-50"
                                                        title="Supprimer"
                                                    >
                                                        <Trash2 className="h-3.5 w-3.5" />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td
                                            colSpan={6}
                                            className="px-4 py-10 text-center text-sm text-slate-400"
                                        >
                                            Aucun contact. Cliquez sur « Nouveau contact » pour commencer.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {contacts?.links?.length > 0 && (
                        <div className="flex flex-wrap items-center gap-2 border-t border-slate-200 px-4 py-4">
                            {contacts.links.map((link, index) => (
                                <button
                                    key={index}
                                    type="button"
                                    disabled={!link.url}
                                    onClick={() => link.url && router.visit(link.url)}
                                    className={`rounded-lg px-3 py-1.5 text-sm ${
                                        link.active
                                            ? 'bg-indigo-600 text-white'
                                            : 'border border-slate-300 bg-white text-slate-700'
                                    } ${!link.url ? 'cursor-not-allowed opacity-50' : 'hover:bg-slate-50'}`}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ))}
                        </div>
                    )}
                </div>
            </div>

            {modalOpen && (
                <ContactForm
                    initial={editing}
                    isEdit={Boolean(editing)}
                    onClose={() => setModalOpen(false)}
                />
            )}
        </AuthenticatedLayout>
    );
}
