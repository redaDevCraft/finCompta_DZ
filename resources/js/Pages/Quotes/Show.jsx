import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Badge from '@/Components/UI/Badge';

const formatCurrency = (value) =>
    new Intl.NumberFormat('fr-DZ', { style: 'currency', currency: 'DZD' }).format(Number(value ?? 0));

const formatDate = (value) => {
    if (!value) return '—';
    return new Intl.DateTimeFormat('fr-DZ', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).format(new Date(value));
};

export default function Show({ quote }) {
    const canConvert = ['draft', 'sent', 'accepted'].includes(quote.status) && !quote.invoice_id;
    const [isSubmittingAction, setIsSubmittingAction] = useState(false);

    const submitAction = (url) => {
        if (isSubmittingAction) return;

        setIsSubmittingAction(true);
        router.post(url, {}, {
            preserveScroll: true,
            onFinish: () => setIsSubmittingAction(false),
        });
    };

    return (
        <AuthenticatedLayout header={`Devis ${quote.number}`}>
            <Head title={`Devis ${quote.number}`} />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center gap-2">
                    <Link href="/quotes" className="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
                        Retour
                    </Link>
                    {quote.status === 'draft' && (
                        <Link href={`/quotes/${quote.id}/edit`} className="rounded-lg border border-indigo-300 bg-white px-3 py-2 text-sm text-indigo-700 hover:bg-indigo-50">
                            Modifier
                        </Link>
                    )}
                    {quote.status === 'draft' && (
                        <button type="button" onClick={() => submitAction(`/quotes/${quote.id}/send`)} disabled={isSubmittingAction} className="rounded-lg bg-indigo-600 px-3 py-2 text-sm text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60">
                            Envoyer
                        </button>
                    )}
                    {quote.status === 'sent' && (
                        <>
                            <button type="button" onClick={() => submitAction(`/quotes/${quote.id}/accept`)} disabled={isSubmittingAction} className="rounded-lg border border-emerald-300 bg-white px-3 py-2 text-sm text-emerald-700 hover:bg-emerald-50 disabled:cursor-not-allowed disabled:opacity-60">
                                Accepter
                            </button>
                            <button type="button" onClick={() => submitAction(`/quotes/${quote.id}/reject`)} disabled={isSubmittingAction} className="rounded-lg border border-rose-300 bg-white px-3 py-2 text-sm text-rose-700 hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-60">
                                Rejeter
                            </button>
                        </>
                    )}
                    {canConvert && (
                        <button type="button" onClick={() => submitAction(`/quotes/${quote.id}/convert-to-invoice`)} disabled={isSubmittingAction} className="rounded-lg bg-gray-900 px-3 py-2 text-sm text-white hover:bg-black disabled:cursor-not-allowed disabled:opacity-60">
                            Convertir en facture
                        </button>
                    )}
                    <a href={`/quotes/${quote.id}/pdf`} target="_blank" rel="noreferrer" className="rounded-lg border border-blue-300 bg-white px-3 py-2 text-sm text-blue-700 hover:bg-blue-50">
                        PDF
                    </a>
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm lg:col-span-2">
                        <div className="flex items-start justify-between">
                            <div>
                                <p className="text-xs uppercase tracking-wide text-slate-500">Devis</p>
                                <h2 className="text-2xl font-semibold text-slate-900">{quote.number}</h2>
                                <p className="mt-1 text-sm text-slate-600">Date {formatDate(quote.issue_date)} · Expiration {formatDate(quote.expiry_date)}</p>
                            </div>
                            <Badge status={quote.status} />
                        </div>
                        <div className="mt-4 rounded-lg bg-slate-50 p-4">
                            <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Client</p>
                            <p className="mt-1 text-sm font-medium text-slate-900">{quote.contact?.display_name ?? '—'}</p>
                        </div>
                        {quote.reference && (
                            <p className="mt-3 text-sm text-slate-700"><span className="font-medium">Référence:</span> {quote.reference}</p>
                        )}
                        {quote.notes && (
                            <div className="mt-4 rounded-lg border border-slate-200 p-4">
                                <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Notes</p>
                                <p className="mt-1 whitespace-pre-wrap text-sm text-slate-700">{quote.notes}</p>
                            </div>
                        )}
                    </div>

                    <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Totaux</p>
                        <dl className="mt-3 space-y-2 text-sm">
                            <div className="flex justify-between"><dt>Total HT</dt><dd className="font-medium">{formatCurrency(quote.subtotal)}</dd></div>
                            <div className="flex justify-between"><dt>TVA</dt><dd className="font-medium">{formatCurrency(quote.tax_total)}</dd></div>
                            <div className="flex justify-between border-t border-slate-200 pt-2 text-base font-semibold"><dt>Total TTC</dt><dd>{formatCurrency(quote.total)}</dd></div>
                        </dl>
                        {quote.invoice_id && (
                            <Link href={`/invoices/${quote.invoice_id}`} className="mt-4 inline-block text-sm font-medium text-indigo-600 hover:text-indigo-700">
                                Voir la facture liée →
                            </Link>
                        )}
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
                                    <th className="px-4 py-2 text-left">Description</th>
                                    <th className="px-4 py-2 text-right">Qté</th>
                                    <th className="px-4 py-2 text-right">PU HT</th>
                                    <th className="px-4 py-2 text-right">TVA</th>
                                    <th className="px-4 py-2 text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {quote.lines?.map((line) => (
                                    <tr key={line.id}>
                                        <td className="px-4 py-2">{line.description}</td>
                                        <td className="px-4 py-2 text-right">{Number(line.quantity ?? 0)}</td>
                                        <td className="px-4 py-2 text-right">{formatCurrency(line.unit_price)}</td>
                                        <td className="px-4 py-2 text-right">{line.vat_rate}%</td>
                                        <td className="px-4 py-2 text-right font-medium">{formatCurrency(line.line_total)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
