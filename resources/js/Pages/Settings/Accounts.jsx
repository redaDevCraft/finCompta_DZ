import { Head } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { ChevronDown, ChevronRight, Plus, Search } from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

const CLASS_LABELS = {
    1: 'Classe 1 — Capitaux',
    2: 'Classe 2 — Immobilisations',
    3: 'Classe 3 — Stocks',
    4: 'Classe 4 — Tiers',
    5: 'Classe 5 — Trésorerie',
    6: 'Classe 6 — Charges',
    7: 'Classe 7 — Produits',
};

export default function Accounts({ accountsByClass }) {
    const [query, setQuery] = useState('');
    const [openClasses, setOpenClasses] = useState({
        1: true,
        2: true,
        3: false,
        4: true,
        5: true,
        6: false,
        7: false,
    });

    const filtered = useMemo(() => {
        const q = query.trim().toLowerCase();

        if (!q) return accountsByClass;

        const result = {};

        Object.entries(accountsByClass).forEach(([classKey, accounts]) => {
            const matches = accounts.filter((account) => {
                return (
                    account.code.toLowerCase().includes(q) ||
                    account.label.toLowerCase().includes(q)
                );
            });

            if (matches.length > 0) {
                result[classKey] = matches;
            }
        });

        return result;
    }, [accountsByClass, query]);

    const toggleClass = (classKey) => {
        setOpenClasses((prev) => ({
            ...prev,
            [classKey]: !prev[classKey],
        }));
    };

    return (
        <AuthenticatedLayout>
            <Head title="Plan comptable" />

            <div className="space-y-6">
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-slate-900">Plan comptable SCF</h1>
                        <p className="mt-1 text-sm text-slate-600">
                            Consultez les comptes système et ajoutez vos comptes personnalisés.
                        </p>
                    </div>

                    <button className="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white">
                        <Plus className="h-4 w-4" />
                        Ajouter un compte
                    </button>
                </div>

                <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div className="relative">
                        <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input
                            type="text"
                            placeholder="Rechercher par code ou libellé"
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            className="w-full rounded-xl border border-slate-300 py-2 pl-10 pr-3 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200"
                        />
                    </div>
                </div>

                <div className="space-y-4">
                    {Object.entries(CLASS_LABELS).map(([classKey, title]) => {
                        const accounts = filtered[classKey] ?? [];
                        const isOpen = !!openClasses[classKey];

                        return (
                            <div key={classKey} className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                                <button
                                    type="button"
                                    onClick={() => toggleClass(classKey)}
                                    className="flex w-full items-center justify-between px-5 py-4 text-left"
                                >
                                    <div>
                                        <h2 className="font-semibold text-slate-900">{title}</h2>
                                        <p className="text-sm text-slate-500">{accounts.length} compte(s)</p>
                                    </div>
                                    {isOpen ? (
                                        <ChevronDown className="h-5 w-5 text-slate-500" />
                                    ) : (
                                        <ChevronRight className="h-5 w-5 text-slate-500" />
                                    )}
                                </button>

                                {isOpen && (
                                    <div className="border-t">
                                        {accounts.length === 0 ? (
                                            <div className="px-5 py-4 text-sm text-slate-500">
                                                Aucun compte dans cette classe.
                                            </div>
                                        ) : (
                                            accounts.map((account) => (
                                                <div
                                                    key={account.id}
                                                    className="flex flex-col gap-3 border-b px-5 py-4 last:border-b-0 md:flex-row md:items-center md:justify-between"
                                                >
                                                    <div className="min-w-0">
                                                        <div className="flex flex-wrap items-center gap-2">
                                                            <span className="font-mono text-sm font-semibold text-slate-900">
                                                                {account.code}
                                                            </span>
                                                            {account.is_system && (
                                                                <span className="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700">
                                                                    système
                                                                </span>
                                                            )}
                                                            <span className="rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700">
                                                                {account.type}
                                                            </span>
                                                        </div>
                                                        <p className="mt-1 text-sm text-slate-700">{account.label}</p>
                                                    </div>

                                                    <div className="flex items-center gap-3">
                                                        <label className="flex items-center gap-2 text-sm text-slate-600">
                                                            <span>Actif</span>
                                                            <input
                                                                type="checkbox"
                                                                checked={!!account.is_active}
                                                                disabled={!!account.is_system}
                                                                readOnly
                                                            />
                                                        </label>

                                                        {!account.is_system && (
                                                            <button className="rounded-lg border border-slate-300 px-3 py-1.5 text-sm text-slate-700">
                                                                Modifier
                                                            </button>
                                                        )}
                                                    </div>
                                                </div>
                                            ))
                                        )}
                                    </div>
                                )}
                            </div>
                        );
                    })}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
