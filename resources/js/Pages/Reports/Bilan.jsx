import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

function formatMoney(value) {
    return new Intl.NumberFormat('fr-DZ', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(value ?? 0));
}

export default function Bilan({
    as_of_date,
    company,
    actif = [],
    passif = [],
    totals,
}) {
    const [asOfDate, setAsOfDate] = useState(as_of_date);

    const applyFilter = (e) => {
        e.preventDefault();
        router.get(
            route('reports.bilan'),
            { as_of_date: asOfDate },
            { preserveState: true, replace: true }
        );
    };

    return (
        <AuthenticatedLayout header="Bilan comptable">
            <Head title="Bilan" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold text-slate-900">Bilan comptable</h1>
                        <p className="mt-1 text-sm text-slate-600">
                            Situation patrimoniale de l’entreprise à une date d’arrêté — classes 1 à 5
                            du plan comptable SCF.
                        </p>
                    </div>
                    <a
                        href={route('reports.bilan.pdf', { as_of_date: asOfDate })}
                        className="inline-flex items-center rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-slate-800"
                    >
                        Exporter PDF
                    </a>
                </div>

                <form
                    onSubmit={applyFilter}
                    className="flex flex-wrap items-end gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
                >
                    <div>
                        <label className="mb-1 block text-sm font-medium text-slate-700">
                            Date d’arrêté
                        </label>
                        <input
                            type="date"
                            value={asOfDate}
                            onChange={(e) => setAsOfDate(e.target.value)}
                            className="rounded-xl border border-slate-300 px-3 py-2.5 text-sm"
                        />
                    </div>
                    <button
                        type="submit"
                        className="rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-indigo-700"
                    >
                        Recalculer
                    </button>
                    <div className="ml-auto text-right text-sm text-slate-500">
                        {company?.raison_sociale && (
                            <div className="font-semibold text-slate-900">
                                {company.raison_sociale}
                            </div>
                        )}
                        <div>Devise : {company?.currency ?? 'DZD'}</div>
                    </div>
                </form>

                {Math.abs(totals?.difference ?? 0) > 0.01 && (
                    <div className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        <strong>Écart actif / passif :</strong>{' '}
                        {formatMoney(totals.difference)} — des écritures non soldées peuvent
                        expliquer cette différence. Passez en revue le journal et la balance.
                    </div>
                )}

                <div className="grid gap-4 lg:grid-cols-2">
                    <BilanColumn title="ACTIF" sections={actif} total={totals.actif} />
                    <BilanColumn title="PASSIF" sections={passif} total={totals.passif} />
                </div>

                <div className="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                    <div className="font-semibold text-slate-900">Résultat net calculé</div>
                    <div className="mt-1 text-slate-600">
                        Produits (classe 7) − Charges (classe 6) ={' '}
                        <strong
                            className={
                                (totals.resultat_net ?? 0) >= 0
                                    ? 'text-emerald-700'
                                    : 'text-rose-700'
                            }
                        >
                            {formatMoney(totals.resultat_net)}
                        </strong>{' '}
                        — reporté dans les Capitaux propres.
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function BilanColumn({ title, sections, total }) {
    return (
        <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div className="bg-slate-900 px-6 py-3 text-sm font-semibold uppercase tracking-wide text-white">
                {title}
            </div>
            <div className="divide-y divide-slate-100">
                {sections.map((section) => (
                    <div key={section.key} className="px-6 py-4">
                        <div className="mb-2 flex items-center justify-between">
                            <h3 className="text-sm font-semibold text-slate-900">
                                {section.label}
                            </h3>
                            <span className="text-sm font-semibold text-slate-700">
                                {formatMoney(section.total)}
                            </span>
                        </div>
                        {section.rubriques.length === 0 ? (
                            <div className="text-xs italic text-slate-400">
                                Aucun mouvement sur la période.
                            </div>
                        ) : (
                            <div className="space-y-3">
                                {section.rubriques.map((r, idx) => (
                                    <div key={idx}>
                                        <div className="flex items-center justify-between text-xs font-medium text-slate-600">
                                            <span>{r.label}</span>
                                            <span>{formatMoney(r.total)}</span>
                                        </div>
                                        {r.lines.length > 0 && (
                                            <table className="mt-1 w-full text-xs">
                                                <tbody className="divide-y divide-slate-50">
                                                    {r.lines.map((line) => (
                                                        <tr key={line.id}>
                                                            <td className="py-1 pl-4 text-slate-600">
                                                                <span className="mr-2 font-mono text-xs text-slate-400">
                                                                    {line.code}
                                                                </span>
                                                                {line.label}
                                                            </td>
                                                            <td className="py-1 text-right text-slate-700">
                                                                {formatMoney(line.amount)}
                                                            </td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        )}
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                ))}
            </div>
            <div className="flex items-center justify-between border-t-2 border-slate-900 bg-slate-50 px-6 py-3 text-base font-bold">
                <span>TOTAL {title}</span>
                <span>{formatMoney(total)}</span>
            </div>
        </div>
    );
}
