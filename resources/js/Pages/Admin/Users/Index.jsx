import AdminLayout from '@/Layouts/AdminLayout';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { Search, Shield, ShieldOff } from 'lucide-react';

export default function AdminUsersIndex({ users, filters = {} }) {
    const [search, setSearch] = useState(filters.search || '');

    function submit(e) {
        e.preventDefault();
        router.get(
            route('admin.users.index'),
            { search: search || undefined },
            { preserveState: true, replace: true },
        );
    }

    function toggleAdmin(user) {
        const msg = user.is_admin
            ? `Retirer le rôle admin à ${user.email} ?`
            : `Attribuer le rôle admin à ${user.email} ?`;
        if (!confirm(msg)) return;
        router.post(
            route('admin.users.toggle-admin', user.id),
            {},
            { preserveScroll: true },
        );
    }

    return (
        <AdminLayout header="Utilisateurs">
            <Head title="Admin — Utilisateurs" />

            <div className="mx-auto max-w-6xl space-y-4">
                <form onSubmit={submit} className="flex max-w-md gap-2">
                    <div className="relative flex-1">
                        <Search className="pointer-events-none absolute left-2.5 top-2.5 h-4 w-4 text-slate-400" />
                        <input
                            type="text"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Nom ou email…"
                            className="w-full rounded-lg border border-slate-300 py-2 pl-8 pr-3 text-sm"
                        />
                    </div>
                    <button
                        type="submit"
                        className="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800"
                    >
                        Rechercher
                    </button>
                </form>

                <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                        <thead className="bg-slate-50 text-xs uppercase text-slate-500">
                            <tr>
                                <th className="px-4 py-3 text-left font-semibold">Utilisateur</th>
                                <th className="px-4 py-3 text-left font-semibold">Email</th>
                                <th className="px-4 py-3 text-right font-semibold">Sociétés</th>
                                <th className="px-4 py-3 text-left font-semibold">Rôles</th>
                                <th className="px-4 py-3 text-left font-semibold">Dernière connexion</th>
                                <th className="px-4 py-3 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {users.data.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="px-4 py-10 text-center text-slate-400">
                                        Aucun utilisateur.
                                    </td>
                                </tr>
                            )}
                            {users.data.map((u) => (
                                <tr key={u.id} className="hover:bg-slate-50">
                                    <td className="px-4 py-3">
                                        <div className="font-medium text-slate-900">{u.name || '—'}</div>
                                        <div className="text-xs text-slate-500">
                                            {u.has_google ? 'Compte Google' : 'Email + mot de passe'}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 text-slate-700">{u.email}</td>
                                    <td className="px-4 py-3 text-right">{u.companies_count}</td>
                                    <td className="px-4 py-3">
                                        <div className="flex flex-wrap gap-1">
                                            {u.roles.length === 0 && (
                                                <span className="text-xs text-slate-400">—</span>
                                            )}
                                            {u.roles.map((r) => (
                                                <span
                                                    key={r}
                                                    className={`rounded-full px-2 py-0.5 text-xs ${
                                                        r === 'admin'
                                                            ? 'bg-amber-100 text-amber-800'
                                                            : 'bg-slate-100 text-slate-700'
                                                    }`}
                                                >
                                                    {r}
                                                </span>
                                            ))}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 text-xs text-slate-500">
                                        {u.last_login_at
                                            ? new Date(u.last_login_at).toLocaleString('fr-DZ')
                                            : '—'}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <button
                                            type="button"
                                            onClick={() => toggleAdmin(u)}
                                            className={`inline-flex items-center gap-1 rounded-md border px-3 py-1 text-xs font-medium ${
                                                u.is_admin
                                                    ? 'border-rose-200 text-rose-700 hover:bg-rose-50'
                                                    : 'border-amber-300 text-amber-800 hover:bg-amber-50'
                                            }`}
                                        >
                                            {u.is_admin ? (
                                                <>
                                                    <ShieldOff className="h-3.5 w-3.5" /> Retirer admin
                                                </>
                                            ) : (
                                                <>
                                                    <Shield className="h-3.5 w-3.5" /> Promouvoir admin
                                                </>
                                            )}
                                        </button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>

                    {users?.links?.length > 0 && (
                        <div className="flex flex-wrap gap-2 border-t border-slate-200 px-4 py-3">
                            {users.links.map((link, idx) => (
                                <button
                                    key={idx}
                                    type="button"
                                    disabled={!link.url}
                                    onClick={() => link.url && router.visit(link.url)}
                                    className={`rounded-md px-2.5 py-1 text-xs ${
                                        link.active
                                            ? 'bg-slate-900 text-white'
                                            : 'border border-slate-200 bg-white text-slate-600'
                                    } ${!link.url ? 'opacity-50' : ''}`}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}
