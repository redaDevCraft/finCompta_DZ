import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Search } from 'lucide-react';

const STATUS_STYLES = {
    trialing: 'bg-amber-50 text-amber-800',
    active: 'bg-emerald-50 text-emerald-800',
    past_due: 'bg-orange-50 text-orange-800',
    canceled: 'bg-slate-100 text-slate-600',
    paused: 'bg-blue-50 text-blue-800',
};

export default function CompaniesIndex({ companies, filters = {} }) {
    const [search, setSearch] = useState(filters.search || '');

    function submit(e) {
        e.preventDefault();
        router.get(
            route('admin.companies.index'),
            { search: search || undefined },
            { preserveState: true, replace: true },
        );
    }

    return (
        <AdminLayout header="Sociétés">
            <Head title="Admin — Sociétés" />

            <div className="mx-auto max-w-6xl space-y-4">
                <form onSubmit={submit} className="flex max-w-md gap-2">
                    <div className="relative flex-1">
                        <Search className="pointer-events-none absolute left-2.5 top-2.5 h-4 w-4 text-slate-400" />
                        <input
                            type="text"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Nom, NIF, RC…"
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
                                <th className="px-4 py-3 text-left font-semibold">Raison sociale</th>
                                <th className="px-4 py-3 text-left font-semibold">NIF</th>
                                <th className="px-4 py-3 text-left font-semibold">Plan</th>
                                <th className="px-4 py-3 text-left font-semibold">Statut</th>
                                <th className="px-4 py-3 text-right font-semibold">Utilisateurs</th>
                                <th className="px-4 py-3 text-right font-semibold">Factures</th>
                                <th className="px-4 py-3 text-left font-semibold">Créée</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {companies.data.length === 0 && (
                                <tr>
                                    <td colSpan={7} className="px-4 py-10 text-center text-slate-400">
                                        Aucune société.
                                    </td>
                                </tr>
                            )}
                            {companies.data.map((c) => {
                                const sub = c.subscription;
                                const status = sub?.status ?? '—';
                                return (
                                    <tr key={c.id} className="hover:bg-slate-50">
                                        <td className="px-4 py-3">
                                            <Link
                                                href={route('admin.companies.show', c.id)}
                                                className="font-medium text-indigo-700 hover:underline"
                                            >
                                                {c.raison_sociale || '—'}
                                            </Link>
                                            <div className="text-xs text-slate-500">
                                                {c.forme_juridique || ''}{' '}
                                                {c.address_wilaya ? `· ${c.address_wilaya}` : ''}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 font-mono text-xs">{c.nif || '—'}</td>
                                        <td className="px-4 py-3">
                                            <div>{sub?.plan?.name ?? '—'}</div>
                                            <div className="text-xs text-slate-500">
                                                {sub?.billing_cycle || ''}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">
                                            <span
                                                className={`rounded-full px-2 py-0.5 text-xs ${STATUS_STYLES[status] || 'bg-slate-100 text-slate-600'}`}
                                            >
                                                {status}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-right">{c.users_count ?? 0}</td>
                                        <td className="px-4 py-3 text-right">{c.invoices_count ?? 0}</td>
                                        <td className="px-4 py-3 text-xs text-slate-500">
                                            {c.created_at
                                                ? new Date(c.created_at).toLocaleDateString('fr-DZ')
                                                : '—'}
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>

                    {companies?.links?.length > 0 && (
                        <Pagination links={companies.links} />
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}

function Pagination({ links }) {
    return (
        <div className="flex flex-wrap gap-2 border-t border-slate-200 px-4 py-3">
            {links.map((link, idx) => (
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
    );
}
