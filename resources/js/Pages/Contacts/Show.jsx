import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, FileText, Receipt } from 'lucide-react';

const TYPE_LABELS = {
    client: 'Client',
    supplier: 'Fournisseur',
    both: 'Client & Fournisseur',
};

const INVOICE_STATUS_STYLES = {
    draft: 'bg-slate-100 text-slate-600',
    issued: 'bg-blue-50 text-blue-800',
    partially_paid: 'bg-amber-50 text-amber-800',
    paid: 'bg-emerald-50 text-emerald-800',
    voided: 'bg-rose-50 text-rose-700 line-through',
    overdue: 'bg-orange-50 text-orange-800',
};

const EXPENSE_STATUS_STYLES = {
    draft: 'bg-slate-100 text-slate-600',
    confirmed: 'bg-blue-50 text-blue-800',
    paid: 'bg-emerald-50 text-emerald-800',
    cancelled: 'bg-rose-50 text-rose-700 line-through',
};

function fmt(n) {
    return (Number(n) || 0).toLocaleString('fr-DZ', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

export default function ContactShow({ contact, invoices = [], expenses = [], balances = {} }) {
    const openReceivable = balances['411']?.open ?? 0;
    const openPayable = balances['401']?.open ?? 0;

    return (
        <AuthenticatedLayout header={contact.display_name}>
            <Head title={contact.display_name} />

            <div className="space-y-6">
                <Link
                    href={route('contacts.index')}
                    className="inline-flex items-center gap-1 text-sm text-slate-600 hover:text-slate-900"
                >
                    <ArrowLeft className="h-4 w-4" /> Retour aux contacts
                </Link>

                <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h1 className="text-2xl font-semibold text-slate-900">{contact.display_name}</h1>
                            {contact.raison_sociale && (
                                <p className="text-sm text-slate-600">{contact.raison_sociale}</p>
                            )}
                            <p className="mt-1 text-xs text-slate-500">
                                {TYPE_LABELS[contact.type] ?? contact.type}
                                {contact.entity_type === 'individual' ? ' · Personne physique' : ' · Entreprise'}
                            </p>
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <Stat label="Créances clients (411)" value={fmt(openReceivable)} unit="DZD" positive={openReceivable > 0} />
                            <Stat label="Dettes fournisseurs (401)" value={fmt(openPayable)} unit="DZD" negative />
                        </div>
                    </div>

                    <dl className="mt-6 grid gap-3 border-t border-slate-100 pt-4 text-sm md:grid-cols-3">
                        <Kv label="NIF" value={contact.nif} mono />
                        <Kv label="NIS" value={contact.nis} mono />
                        <Kv label="RC" value={contact.rc} mono />
                        <Kv label="Email" value={contact.email} />
                        <Kv label="Téléphone" value={contact.phone} />
                        <Kv label="Wilaya" value={contact.address_wilaya} />
                        <Kv label="Adresse" value={contact.address_line1} className="md:col-span-3" />
                    </dl>
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div className="mb-3 flex items-center justify-between">
                            <h2 className="flex items-center gap-2 text-sm font-semibold text-slate-900">
                                <FileText className="h-4 w-4" /> Factures récentes
                            </h2>
                            <Link
                                href={route('invoices.index', { search: contact.display_name })}
                                className="text-xs text-indigo-600 hover:underline"
                            >
                                Tout voir →
                            </Link>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-200 text-sm">
                                <thead className="bg-slate-50 text-xs uppercase text-slate-500">
                                    <tr>
                                        <th className="px-3 py-2 text-left font-semibold">Numéro</th>
                                        <th className="px-3 py-2 text-left font-semibold">Date</th>
                                        <th className="px-3 py-2 text-left font-semibold">Statut</th>
                                        <th className="px-3 py-2 text-right font-semibold">TTC</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {invoices.length === 0 && (
                                        <tr>
                                            <td colSpan={4} className="py-6 text-center text-slate-400">
                                                Aucune facture.
                                            </td>
                                        </tr>
                                    )}
                                    {invoices.map((inv) => (
                                        <tr key={inv.id}>
                                            <td className="px-3 py-2">
                                                <Link
                                                    href={route('invoices.show', inv.id)}
                                                    className="font-mono text-xs text-indigo-700 hover:underline"
                                                >
                                                    {inv.invoice_number || '—'}
                                                </Link>
                                            </td>
                                            <td className="px-3 py-2 text-xs">
                                                {inv.issue_date ? new Date(inv.issue_date).toLocaleDateString('fr-DZ') : '—'}
                                            </td>
                                            <td className="px-3 py-2">
                                                <span className={`rounded-full px-2 py-0.5 text-xs ${INVOICE_STATUS_STYLES[inv.status] || 'bg-slate-100'}`}>
                                                    {inv.status}
                                                </span>
                                            </td>
                                            <td className="px-3 py-2 text-right tabular-nums">
                                                {fmt(inv.total_ttc)} {inv.currency || 'DZD'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div className="mb-3 flex items-center justify-between">
                            <h2 className="flex items-center gap-2 text-sm font-semibold text-slate-900">
                                <Receipt className="h-4 w-4" /> Dépenses récentes
                            </h2>
                            <Link
                                href={route('expenses.index', { search: contact.display_name })}
                                className="text-xs text-indigo-600 hover:underline"
                            >
                                Tout voir →
                            </Link>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-200 text-sm">
                                <thead className="bg-slate-50 text-xs uppercase text-slate-500">
                                    <tr>
                                        <th className="px-3 py-2 text-left font-semibold">Réf.</th>
                                        <th className="px-3 py-2 text-left font-semibold">Date</th>
                                        <th className="px-3 py-2 text-left font-semibold">Statut</th>
                                        <th className="px-3 py-2 text-right font-semibold">TTC</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {expenses.length === 0 && (
                                        <tr>
                                            <td colSpan={4} className="py-6 text-center text-slate-400">
                                                Aucune dépense.
                                            </td>
                                        </tr>
                                    )}
                                    {expenses.map((ex) => (
                                        <tr key={ex.id}>
                                            <td className="px-3 py-2">
                                                <Link
                                                    href={route('expenses.show', ex.id)}
                                                    className="font-mono text-xs text-indigo-700 hover:underline"
                                                >
                                                    {ex.reference || ex.id.slice(0, 6)}
                                                </Link>
                                            </td>
                                            <td className="px-3 py-2 text-xs">
                                                {ex.expense_date ? new Date(ex.expense_date).toLocaleDateString('fr-DZ') : '—'}
                                            </td>
                                            <td className="px-3 py-2">
                                                <span className={`rounded-full px-2 py-0.5 text-xs ${EXPENSE_STATUS_STYLES[ex.status] || 'bg-slate-100'}`}>
                                                    {ex.status}
                                                </span>
                                            </td>
                                            <td className="px-3 py-2 text-right tabular-nums">
                                                {fmt(ex.total_ttc)} DZD
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function Kv({ label, value, mono = false, className = '' }) {
    return (
        <div className={className}>
            <dt className="text-xs uppercase text-slate-500">{label}</dt>
            <dd className={`mt-0.5 text-slate-900 ${mono ? 'font-mono text-xs' : ''}`}>{value || '—'}</dd>
        </div>
    );
}

function Stat({ label, value, unit, positive = false, negative = false }) {
    return (
        <div className="min-w-[200px] rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
            <div className="text-xs uppercase text-slate-500">{label}</div>
            <div
                className={`text-lg font-semibold tabular-nums ${
                    positive ? 'text-emerald-700' : negative ? 'text-rose-700' : 'text-slate-900'
                }`}
            >
                {value} <span className="text-xs font-normal text-slate-500">{unit}</span>
            </div>
        </div>
    );
}
