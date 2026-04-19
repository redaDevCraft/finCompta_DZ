import { Head, Link } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { ArrowLeft, FileText, User } from 'lucide-react';

function formatDzd(n) {
    if (n == null) return '—';
    return new Intl.NumberFormat('fr-DZ').format(Math.round(n)) + ' DZD';
}

export default function ClientShow({ contact, invoices = [], ledger, aging }) {
    return (
        <AuthenticatedLayout header={`Client — ${contact.display_name}`}>
            <Head title={contact.display_name} />

            <div className="space-y-6">
                <Link href={route('clients.index')} className="inline-flex items-center gap-2 text-sm text-slate-600 hover:text-slate-900">
                    <ArrowLeft className="h-4 w-4" /> Retour aux clients
                </Link>

                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-1">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-100 text-indigo-700">
                                <User className="h-5 w-5" />
                            </div>
                            <div>
                                <div className="font-semibold text-slate-900">{contact.display_name}</div>
                                <div className="text-xs text-slate-500">{contact.raison_sociale ?? ''}</div>
                            </div>
                        </div>
                        <dl className="mt-5 space-y-2 text-sm">
                            <Row label="NIF" value={contact.nif} mono />
                            <Row label="NIS" value={contact.nis} mono />
                            <Row label="RC" value={contact.rc} mono />
                            <Row label="Email" value={contact.email} />
                            <Row label="Téléphone" value={contact.phone} />
                            <Row label="Adresse" value={contact.address_line1} />
                            <Row label="Wilaya" value={contact.address_wilaya} />
                        </dl>
                    </div>

                    <div className="space-y-6 lg:col-span-2">
                        <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                            <h2 className="text-base font-semibold">Solde & balance âgée</h2>
                            <div className="mt-4 grid gap-3 sm:grid-cols-4">
                                <Kpi label="0–30 j"    value={formatDzd(aging?.['0_30'])} />
                                <Kpi label="31–60 j"   value={formatDzd(aging?.['31_60'])} />
                                <Kpi label="61–90 j"   value={formatDzd(aging?.['61_90'])} />
                                <Kpi label="> 90 j"    value={formatDzd(aging?.over90)} danger />
                            </div>

                            <div className="mt-5 flex items-center justify-between rounded-xl bg-slate-50 px-4 py-3">
                                <div className="text-sm">Solde compte client (411)</div>
                                <div className="text-lg font-bold text-indigo-700">{formatDzd(ledger?.totals?.balance)}</div>
                            </div>
                        </div>

                        <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                            <h2 className="flex items-center gap-2 text-base font-semibold">
                                <FileText className="h-4 w-4 text-slate-600" /> Dernières factures ({invoices.length})
                            </h2>
                            <div className="mt-3 overflow-hidden rounded-xl border border-slate-200">
                                <table className="min-w-full divide-y divide-slate-200 text-sm">
                                    <thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                                        <tr>
                                            <th className="px-3 py-2 text-left">Numéro</th>
                                            <th className="px-3 py-2 text-left">Date</th>
                                            <th className="px-3 py-2 text-left">Statut</th>
                                            <th className="px-3 py-2 text-right">Montant</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {invoices.length ? invoices.map((inv) => (
                                            <tr key={inv.id} className="hover:bg-slate-50">
                                                <td className="px-3 py-2">
                                                    <Link className="font-mono text-xs text-indigo-700 hover:underline" href={route('invoices.show', inv.id)}>
                                                        {inv.invoice_number ?? '—'}
                                                    </Link>
                                                </td>
                                                <td className="px-3 py-2">{inv.issue_date}</td>
                                                <td className="px-3 py-2">{inv.status}</td>
                                                <td className="px-3 py-2 text-right">{formatDzd(inv.total_ttc)}</td>
                                            </tr>
                                        )) : (
                                            <tr><td colSpan={4} className="px-3 py-6 text-center text-slate-400">Aucune facture.</td></tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 className="text-base font-semibold">Grand livre du tiers (411)</h2>
                    <div className="mt-3 overflow-hidden rounded-xl border border-slate-200">
                        <table className="min-w-full divide-y divide-slate-200 text-sm">
                            <thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th className="px-3 py-2 text-left">Date</th>
                                    <th className="px-3 py-2 text-left">Réf.</th>
                                    <th className="px-3 py-2 text-left">Libellé</th>
                                    <th className="px-3 py-2 text-right">Débit</th>
                                    <th className="px-3 py-2 text-right">Crédit</th>
                                    <th className="px-3 py-2 text-center">Lettré</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {ledger?.lines?.length ? ledger.lines.map((l) => (
                                    <tr key={l.id}>
                                        <td className="px-3 py-2">{l.entry_date}</td>
                                        <td className="px-3 py-2 font-mono text-xs">{l.reference ?? ''}</td>
                                        <td className="px-3 py-2 text-slate-700">{l.description ?? ''}</td>
                                        <td className="px-3 py-2 text-right">{l.debit > 0 ? formatDzd(l.debit) : ''}</td>
                                        <td className="px-3 py-2 text-right">{l.credit > 0 ? formatDzd(l.credit) : ''}</td>
                                        <td className="px-3 py-2 text-center text-xs">{l.lettering_id ? '✓' : '—'}</td>
                                    </tr>
                                )) : (
                                    <tr><td colSpan={6} className="px-3 py-6 text-center text-slate-400">Aucune écriture.</td></tr>
                                )}
                            </tbody>
                            {ledger?.lines?.length > 0 && (
                                <tfoot className="bg-slate-50 font-semibold">
                                    <tr>
                                        <td colSpan={3} className="px-3 py-2 text-right">Totaux</td>
                                        <td className="px-3 py-2 text-right">{formatDzd(ledger.totals.debit)}</td>
                                        <td className="px-3 py-2 text-right">{formatDzd(ledger.totals.credit)}</td>
                                        <td className="px-3 py-2 text-right">{formatDzd(ledger.totals.balance)}</td>
                                    </tr>
                                </tfoot>
                            )}
                        </table>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function Row({ label, value, mono }) {
    return (
        <div className="flex justify-between gap-2">
            <span className="text-slate-500">{label}</span>
            <span className={mono ? 'font-mono text-xs text-slate-800' : 'text-slate-800'}>{value || '—'}</span>
        </div>
    );
}

function Kpi({ label, value, danger }) {
    return (
        <div className={`rounded-xl border px-3 py-2 ${danger ? 'border-rose-200 bg-rose-50' : 'border-slate-200 bg-slate-50'}`}>
            <div className="text-xs text-slate-500">{label}</div>
            <div className={`text-sm font-bold ${danger ? 'text-rose-700' : 'text-slate-900'}`}>{value}</div>
        </div>
    );
}
