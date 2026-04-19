import { Head, Link, router, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Badge from '@/Components/UI/Badge';
import {
    AlertCircle,
    ArrowLeft,
    Ban,
    BookOpenCheck,
    FileDown,
    FilePlus2,
    Send,
} from 'lucide-react';

const formatCurrency = (value, currency = 'DZD') =>
    new Intl.NumberFormat('fr-DZ', {
        style: 'currency',
        currency,
        minimumFractionDigits: 2,
    }).format(Number(value ?? 0));

const formatDate = (value) => {
    if (!value) return '—';
    return new Intl.DateTimeFormat('fr-DZ', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).format(new Date(value));
};

const docTypeLabel = (type) =>
    ({
        invoice: 'Facture',
        credit_note: 'Avoir',
        quote: 'Devis',
        delivery_note: 'Bon de livraison',
    })[type] ?? type;

export default function Show({ invoice }) {
    const { flash, errors } = usePage().props;

    if (!invoice) {
        return (
            <AuthenticatedLayout header="Facture">
                <Head title="Facture introuvable" />
                <div className="rounded-xl border border-rose-200 bg-rose-50 p-6 text-rose-800">
                    Facture introuvable.
                </div>
            </AuthenticatedLayout>
        );
    }

    const isDraft = invoice.status === 'draft';
    const canVoid = invoice.status === 'issued' || invoice.status === 'partially_paid';
    const canCredit = invoice.status === 'issued' || invoice.status === 'partially_paid' || invoice.status === 'paid';

    const issueInvoice = () => {
        if (!confirm('Émettre cette facture ? Elle sera numérotée et non-modifiable.')) return;
        router.post(`/invoices/${invoice.id}/issue`, {}, { preserveScroll: true });
    };

    const voidInvoice = () => {
        if (!confirm('Annuler cette facture ?')) return;
        router.post(`/invoices/${invoice.id}/void`, {}, { preserveScroll: true });
    };

    const createCredit = () => {
        if (!confirm('Créer un avoir pour cette facture ?')) return;
        router.post(`/invoices/${invoice.id}/credit`, {}, { preserveScroll: false });
    };

    return (
        <AuthenticatedLayout
            header={`${docTypeLabel(invoice.document_type)} ${invoice.invoice_number ?? '(brouillon)'}`}
        >
            <Head title={`${docTypeLabel(invoice.document_type)} ${invoice.invoice_number ?? ''}`} />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center gap-3">
                    <Link
                        href="/invoices"
                        className="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                    >
                        <ArrowLeft className="h-4 w-4" />
                        Retour à la liste
                    </Link>

                    <div className="ml-auto flex flex-wrap items-center gap-2">
                        {isDraft && (
                            <button
                                type="button"
                                onClick={issueInvoice}
                                className="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-3.5 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                            >
                                <Send className="h-4 w-4" />
                                Émettre
                            </button>
                        )}

                        {invoice.pdf_path && (
                            <a
                                href={`/invoices/${invoice.id}/pdf`}
                                target="_blank"
                                rel="noreferrer"
                                className="inline-flex items-center gap-2 rounded-lg border border-sky-300 bg-white px-3.5 py-2 text-sm font-medium text-sky-700 hover:bg-sky-50"
                            >
                                <FileDown className="h-4 w-4" />
                                Télécharger PDF
                            </a>
                        )}

                        {canCredit && invoice.document_type === 'invoice' && (
                            <button
                                type="button"
                                onClick={createCredit}
                                className="inline-flex items-center gap-2 rounded-lg border border-amber-300 bg-white px-3.5 py-2 text-sm font-medium text-amber-700 hover:bg-amber-50"
                            >
                                <FilePlus2 className="h-4 w-4" />
                                Créer un avoir
                            </button>
                        )}

                        {canVoid && (
                            <button
                                type="button"
                                onClick={voidInvoice}
                                className="inline-flex items-center gap-2 rounded-lg border border-rose-300 bg-white px-3.5 py-2 text-sm font-medium text-rose-700 hover:bg-rose-50"
                            >
                                <Ban className="h-4 w-4" />
                                Annuler
                            </button>
                        )}
                    </div>
                </div>

                {flash?.success && (
                    <div className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                        {flash.success}
                    </div>
                )}
                {flash?.error && (
                    <div className="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                        {flash.error}
                    </div>
                )}
                {errors && Object.keys(errors).length > 0 && (
                    <div className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        <div className="flex items-center gap-2 font-medium">
                            <AlertCircle className="h-4 w-4" />
                            Avertissements
                        </div>
                        <ul className="mt-2 list-disc pl-5">
                            {Object.values(errors).flat().map((e, i) => (
                                <li key={i}>{e}</li>
                            ))}
                        </ul>
                    </div>
                )}

                <div className="grid gap-4 lg:grid-cols-3">
                    <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm lg:col-span-2">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p className="text-xs uppercase tracking-wide text-slate-500">
                                    {docTypeLabel(invoice.document_type)}
                                </p>
                                <h2 className="mt-1 text-2xl font-semibold text-slate-900">
                                    {invoice.invoice_number ?? 'Brouillon'}
                                </h2>
                                <p className="mt-1 text-sm text-slate-600">
                                    Émise le {formatDate(invoice.issue_date)}
                                    {invoice.due_date ? ` · échéance ${formatDate(invoice.due_date)}` : ''}
                                </p>
                            </div>
                            <Badge status={invoice.status} />
                        </div>

                        <div className="mt-4 grid gap-4 sm:grid-cols-2">
                            <div className="rounded-lg bg-slate-50 p-4">
                                <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    Client
                                </p>
                                <p className="mt-1 text-sm font-medium text-slate-900">
                                    {invoice.contact?.display_name ?? 'Client non renseigné'}
                                </p>
                                {invoice.contact?.nif && (
                                    <p className="mt-0.5 text-xs text-slate-500">
                                        NIF : {invoice.contact.nif}
                                    </p>
                                )}
                            </div>
                            <div className="rounded-lg bg-slate-50 p-4">
                                <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    Mode de paiement
                                </p>
                                <p className="mt-1 text-sm font-medium text-slate-900">
                                    {invoice.payment_mode ?? '—'}
                                </p>
                            </div>
                        </div>

                        {invoice.notes && (
                            <div className="mt-4 rounded-lg border border-slate-200 p-4">
                                <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    Notes
                                </p>
                                <p className="mt-1 whitespace-pre-wrap text-sm text-slate-700">
                                    {invoice.notes}
                                </p>
                            </div>
                        )}
                    </div>

                    <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Totaux
                        </p>
                        <dl className="mt-3 space-y-2 text-sm">
                            <div className="flex items-center justify-between">
                                <dt className="text-slate-600">Total HT</dt>
                                <dd className="font-medium text-slate-900">
                                    {formatCurrency(invoice.subtotal_ht, invoice.currency)}
                                </dd>
                            </div>
                            <div className="flex items-center justify-between">
                                <dt className="text-slate-600">TVA</dt>
                                <dd className="font-medium text-slate-900">
                                    {formatCurrency(invoice.total_vat, invoice.currency)}
                                </dd>
                            </div>
                            <div className="mt-2 flex items-center justify-between border-t border-slate-200 pt-2 text-base">
                                <dt className="font-semibold text-slate-900">Total TTC</dt>
                                <dd className="font-bold text-slate-900">
                                    {formatCurrency(invoice.total_ttc, invoice.currency)}
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-200 px-5 py-3">
                        <h3 className="text-sm font-semibold text-slate-900">Lignes</h3>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-200 text-sm">
                            <thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th className="px-4 py-2 text-left font-semibold">Description</th>
                                    <th className="px-4 py-2 text-right font-semibold">Qté</th>
                                    <th className="px-4 py-2 text-right font-semibold">PU HT</th>
                                    <th className="px-4 py-2 text-right font-semibold">TVA</th>
                                    <th className="px-4 py-2 text-right font-semibold">Total HT</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {invoice.lines?.length ? (
                                    invoice.lines.map((line) => (
                                        <tr key={line.id}>
                                            <td className="px-4 py-2 text-slate-800">
                                                {line.description ?? '—'}
                                            </td>
                                            <td className="px-4 py-2 text-right text-slate-700">
                                                {Number(line.quantity ?? 0)}
                                            </td>
                                            <td className="px-4 py-2 text-right text-slate-700">
                                                {formatCurrency(line.unit_price_ht, invoice.currency)}
                                            </td>
                                            <td className="px-4 py-2 text-right text-slate-700">
                                                {line.vat_rate != null ? `${line.vat_rate} %` : '—'}
                                            </td>
                                            <td className="px-4 py-2 text-right font-medium text-slate-900">
                                                {formatCurrency(line.line_total_ht, invoice.currency)}
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td
                                            colSpan={5}
                                            className="px-4 py-8 text-center text-sm text-slate-400"
                                        >
                                            Aucune ligne
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                {invoice.journal_entry && (
                    <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                        <div className="flex items-center justify-between border-b border-slate-200 px-5 py-3">
                            <div className="flex items-center gap-2">
                                <BookOpenCheck className="h-4 w-4 text-slate-500" />
                                <h3 className="text-sm font-semibold text-slate-900">
                                    Écriture comptable associée
                                </h3>
                            </div>
                            <Link
                                href="/ledger/journal"
                                className="text-xs font-medium text-indigo-600 hover:text-indigo-700"
                            >
                                Voir dans le journal →
                            </Link>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-200 text-sm">
                                <thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                                    <tr>
                                        <th className="px-4 py-2 text-left font-semibold">Compte</th>
                                        <th className="px-4 py-2 text-left font-semibold">Libellé</th>
                                        <th className="px-4 py-2 text-right font-semibold">Débit</th>
                                        <th className="px-4 py-2 text-right font-semibold">Crédit</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {invoice.journal_entry.lines?.map((line) => (
                                        <tr key={line.id}>
                                            <td className="px-4 py-2 font-mono text-xs text-slate-700">
                                                {line.account?.code} — {line.account?.label}
                                            </td>
                                            <td className="px-4 py-2 text-slate-700">
                                                {line.description ?? '—'}
                                            </td>
                                            <td className="px-4 py-2 text-right text-slate-900">
                                                {Number(line.debit) > 0
                                                    ? formatCurrency(line.debit, invoice.currency)
                                                    : ''}
                                            </td>
                                            <td className="px-4 py-2 text-right text-slate-900">
                                                {Number(line.credit) > 0
                                                    ? formatCurrency(line.credit, invoice.currency)
                                                    : ''}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
