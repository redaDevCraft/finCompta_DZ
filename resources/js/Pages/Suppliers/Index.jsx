import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { ArrowUpDown, Plus, Search, Truck } from 'lucide-react';

function formatDzd(n) {
    if (n == null) return '—';
    return new Intl.NumberFormat('fr-DZ').format(Math.round(n)) + ' DZD';
}

export default function SuppliersIndex({ suppliers, filters = {}, kpis = {} }) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const [sort, setSort] = useState(filters.sort ?? 'display_name');
    const [dir, setDir] = useState(filters.dir ?? 'asc');

    const apply = (patch = {}) => {
        router.get(route('suppliers.index'), {
            search: (patch.search ?? search) || undefined,
            status: (patch.status ?? status) || undefined,
            sort: patch.sort ?? sort,
            dir: patch.dir ?? dir,
        }, { preserveState: true, replace: true });
    };

    const toggleSort = (col) => {
        if (sort === col) {
            const newDir = dir === 'asc' ? 'desc' : 'asc';
            setDir(newDir);
            apply({ dir: newDir });
        } else {
            setSort(col);
            setDir('asc');
            apply({ sort: col, dir: 'asc' });
        }
    };

    const onSearchSubmit = (e) => { e.preventDefault(); apply({ search }); };

    return (
        <AuthenticatedLayout header="Fournisseurs">
            <Head title="Fournisseurs" />
            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="flex items-center gap-2 text-2xl font-semibold text-slate-900">
                            <Truck className="h-6 w-6 text-amber-600" /> Fournisseurs
                        </h1>
                        <p className="mt-1 text-sm text-slate-600">
                            {suppliers.total} fournisseur{suppliers.total > 1 ? 's' : ''} — dettes non lettrées: <strong>{formatDzd(kpis.open_payable)}</strong>
                        </p>
                    </div>
                    <Link
                        href={route('contacts.index', { create: 1, type: 'supplier' })}
                        className="inline-flex items-center gap-2 rounded-xl bg-amber-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-amber-700"
                    >
                        <Plus className="h-4 w-4" /> Nouveau fournisseur
                    </Link>
                </div>

                <div className="flex flex-wrap items-center gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <form onSubmit={onSearchSubmit} className="relative flex-1 min-w-[220px]">
                        <Search className="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                        <input
                            type="search"
                            placeholder="Rechercher (nom, NIF, email, téléphone)…"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="w-full rounded-lg border border-slate-300 py-2 pl-9 pr-3 text-sm"
                        />
                    </form>
                    <select
                        value={status}
                        onChange={(e) => { setStatus(e.target.value); apply({ status: e.target.value }); }}
                        className="rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    >
                        <option value="">Tous les statuts</option>
                        <option value="active">Actif</option>
                        <option value="inactive">Inactif</option>
                    </select>
                </div>

                <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-200 text-sm">
                            <thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th className="px-4 py-3 text-left">
                                        <button onClick={() => toggleSort('display_name')} className="inline-flex items-center gap-1 hover:text-slate-900">
                                            Nom <ArrowUpDown className="h-3 w-3" />
                                        </button>
                                    </th>
                                    <th className="px-4 py-3 text-left">NIF</th>
                                    <th className="px-4 py-3 text-left">Contact</th>
                                    <th className="px-4 py-3 text-right">Dépenses</th>
                                    <th className="px-4 py-3 text-right">Total TTC</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {suppliers.data.length ? suppliers.data.map((s) => (
                                    <tr key={s.id} className="hover:bg-slate-50">
                                        <td className="px-4 py-3">
                                            <Link href={route('suppliers.show', s.id)} className="font-medium text-amber-700 hover:underline">
                                                {s.display_name}
                                            </Link>
                                            {s.raison_sociale && s.raison_sociale !== s.display_name && (
                                                <div className="text-xs text-slate-500">{s.raison_sociale}</div>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 font-mono text-xs">{s.nif || '—'}</td>
                                        <td className="px-4 py-3">
                                            <div>{s.email || '—'}</div>
                                            <div className="text-xs text-slate-400">{s.phone || ''}</div>
                                        </td>
                                        <td className="px-4 py-3 text-right">{s.expenses_count ?? 0}</td>
                                        <td className="px-4 py-3 text-right font-semibold">{formatDzd(s.expenses_total)}</td>
                                    </tr>
                                )) : (
                                    <tr><td colSpan={5} className="px-4 py-10 text-center text-slate-400">Aucun fournisseur.</td></tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {suppliers.links?.length > 0 && (
                        <div className="flex flex-wrap items-center gap-2 border-t border-slate-200 px-4 py-3">
                            {suppliers.links.map((link, i) => (
                                <button
                                    key={i}
                                    type="button"
                                    disabled={!link.url}
                                    onClick={() => link.url && router.visit(link.url, { preserveState: true })}
                                    className={`rounded-lg px-3 py-1.5 text-xs ${
                                        link.active ? 'bg-amber-600 text-white' : 'border border-slate-300 bg-white text-slate-700'
                                    } ${!link.url ? 'cursor-not-allowed opacity-50' : 'hover:bg-slate-50'}`}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
