import { Head, useForm, Link } from '@inertiajs/react';
import { useMemo } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Alert from '@/Components/UI/Alert';
import { ArrowLeft } from 'lucide-react';

const formatCurrency = (value) =>
    new Intl.NumberFormat('fr-DZ', { style: 'currency', currency: 'DZD' }).format(Number(value ?? 0));

function ContactSelector({ contacts, value, onChange, error }) {
    return (
        <div>
            <label className="mb-1 block text-sm font-medium text-gray-700">Client</label>
            <select
                value={value}
                onChange={(e) => onChange(e.target.value)}
                className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
            >
                <option value="">Sélectionner un client</option>
                {contacts.map((c) => (
                    <option key={c.id} value={c.id}>{c.display_name}</option>
                ))}
            </select>
            {error ? <p className="mt-1 text-sm text-red-600">{error}</p> : null}
        </div>
    );
}

function InvoiceMeta({ data, setData, errors }) {
    return (
        <div className="grid gap-4 md:grid-cols-2">
            <div>
                <label className="mb-1 block text-sm font-medium text-gray-700">Date d'émission</label>
                <input type="date" value={data.issue_date} onChange={(e) => setData('issue_date', e.target.value)}
                    className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" />
                {errors.issue_date && <p className="mt-1 text-sm text-red-600">{errors.issue_date}</p>}
            </div>
            <div>
                <label className="mb-1 block text-sm font-medium text-gray-700">Date d'échéance</label>
                <input type="date" value={data.due_date ?? ''} onChange={(e) => setData('due_date', e.target.value)}
                    className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" />
                {errors.due_date && <p className="mt-1 text-sm text-red-600">{errors.due_date}</p>}
            </div>
            <div>
                <label className="mb-1 block text-sm font-medium text-gray-700">Type de document</label>
                <select value={data.document_type} onChange={(e) => setData('document_type', e.target.value)}
                    className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                    <option value="invoice">Facture</option>
                    <option value="credit_note">Avoir</option>
                    <option value="quote">Devis</option>
                    <option value="delivery_note">Bon de livraison</option>
                </select>
            </div>
            <div>
                <label className="mb-1 block text-sm font-medium text-gray-700">Mode de paiement</label>
                <input type="text" value={data.payment_mode ?? ''} onChange={(e) => setData('payment_mode', e.target.value)}
                    placeholder="Virement, chèque, espèces..."
                    className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" />
            </div>
            <div className="md:col-span-2">
                <label className="mb-1 block text-sm font-medium text-gray-700">Notes</label>
                <textarea rows={3} value={data.notes ?? ''} onChange={(e) => setData('notes', e.target.value)}
                    className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" />
            </div>
        </div>
    );
}

function LineTable({ lines, computed, taxRates, accounts, setData, errors }) {
    const update = (i, k, v) => { const next = [...lines]; next[i] = { ...next[i], [k]: v }; setData('lines', next); };
    const add = () => setData('lines', [...lines, {
        designation: '', quantity: 1, unit: '', unit_price_ht: 0, discount_pct: 0,
        vat_rate_pct: 19, tax_rate_id: '', account_id: '',
    }]);
    const remove = (i) => { if (lines.length === 1) return; setData('lines', lines.filter((_, idx) => idx !== i)); };

    return (
        <div className="space-y-3">
            <div className="overflow-x-auto rounded-xl border border-gray-200">
                <table className="min-w-full divide-y divide-gray-200 bg-white text-sm">
                    <thead className="bg-gray-50 text-xs uppercase tracking-wide text-gray-600">
                        <tr>
                            <th className="px-3 py-3 text-left">Désignation</th>
                            <th className="px-3 py-3 text-left">Qté</th>
                            <th className="px-3 py-3 text-left">Unité</th>
                            <th className="px-3 py-3 text-left">PU HT</th>
                            <th className="px-3 py-3 text-left">Remise %</th>
                            <th className="px-3 py-3 text-left">TVA %</th>
                            <th className="px-3 py-3 text-left">Compte</th>
                            <th className="px-3 py-3 text-right">HT</th>
                            <th className="px-3 py-3 text-right">TTC</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                        {lines.map((l, i) => (
                            <tr key={i}>
                                <td className="px-3 py-3">
                                    <input type="text" value={l.designation ?? ''} onChange={(e) => update(i, 'designation', e.target.value)}
                                        className="w-56 rounded-lg border border-gray-300 px-3 py-2 text-sm" />
                                    {errors[`lines.${i}.designation`] && <p className="mt-1 text-xs text-red-600">{errors[`lines.${i}.designation`]}</p>}
                                </td>
                                <td className="px-3 py-3"><input type="number" step="0.0001" value={l.quantity} onChange={(e) => update(i, 'quantity', Number(e.target.value))} className="w-24 rounded-lg border border-gray-300 px-3 py-2 text-sm" /></td>
                                <td className="px-3 py-3"><input type="text" value={l.unit ?? ''} onChange={(e) => update(i, 'unit', e.target.value)} className="w-20 rounded-lg border border-gray-300 px-3 py-2 text-sm" /></td>
                                <td className="px-3 py-3"><input type="number" step="0.01" value={l.unit_price_ht} onChange={(e) => update(i, 'unit_price_ht', Number(e.target.value))} className="w-28 rounded-lg border border-gray-300 px-3 py-2 text-sm" /></td>
                                <td className="px-3 py-3"><input type="number" step="0.01" value={l.discount_pct} onChange={(e) => update(i, 'discount_pct', Number(e.target.value))} className="w-20 rounded-lg border border-gray-300 px-3 py-2 text-sm" /></td>
                                <td className="px-3 py-3">
                                    <select value={l.vat_rate_pct} onChange={(e) => update(i, 'vat_rate_pct', Number(e.target.value))}
                                        className="w-20 rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                        <option value={0}>0%</option>
                                        <option value={9}>9%</option>
                                        <option value={19}>19%</option>
                                    </select>
                                </td>
                                <td className="px-3 py-3">
                                    <select value={l.account_id ?? ''} onChange={(e) => update(i, 'account_id', e.target.value)}
                                        className="w-44 rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                        <option value="">—</option>
                                        {accounts.map((a) => <option key={a.id} value={a.id}>{a.code} - {a.label}</option>)}
                                    </select>
                                </td>
                                <td className="px-3 py-3 text-right text-gray-700">{formatCurrency(computed[i]?.line_ht ?? 0)}</td>
                                <td className="px-3 py-3 text-right font-medium">{formatCurrency(computed[i]?.line_ttc ?? 0)}</td>
                                <td className="px-3 py-3"><button type="button" onClick={() => remove(i)} className="rounded-md border border-red-300 px-2 py-1 text-xs text-red-700 hover:bg-red-50">Suppr.</button></td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <button type="button" onClick={add} className="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                Ajouter une ligne
            </button>
        </div>
    );
}

export default function Edit({ invoice, contacts = [], taxRates = [], accounts = [] }) {
    const initialLines = (invoice.lines?.length ? invoice.lines : [{}]).map((l) => ({
        id: l.id ?? null,
        designation: l.designation ?? '',
        quantity: Number(l.quantity ?? 1),
        unit: l.unit ?? '',
        unit_price_ht: Number(l.unit_price_ht ?? 0),
        discount_pct: Number(l.discount_pct ?? 0),
        vat_rate_pct: Number(l.vat_rate_pct ?? 19),
        tax_rate_id: l.tax_rate_id ?? '',
        account_id: l.account_id ?? '',
    }));

    const { data, setData, put, processing, errors } = useForm({
        contact_id: invoice.contact_id ?? '',
        issue_date: invoice.issue_date ?? '',
        due_date: invoice.due_date ?? '',
        document_type: invoice.document_type ?? 'invoice',
        payment_mode: invoice.payment_mode ?? '',
        notes: invoice.notes ?? '',
        lines: initialLines,
    });

    const computed = useMemo(() => data.lines.map((l) => {
        const q = Number(l.quantity ?? 0);
        const pu = Number(l.unit_price_ht ?? 0);
        const disc = Number(l.discount_pct ?? 0);
        const vatR = Number(l.vat_rate_pct ?? 0);
        const ht = Math.round(q * pu * (1 - disc / 100) * 100) / 100;
        const vat = Math.round(ht * vatR) / 100;
        return { line_ht: ht, line_vat: vat, line_ttc: ht + vat };
    }), [data.lines]);

    const totals = computed.reduce((acc, c) => {
        acc.ht += c.line_ht; acc.vat += c.line_vat; acc.ttc += c.line_ttc; return acc;
    }, { ht: 0, vat: 0, ttc: 0 });

    const submit = (e) => { e.preventDefault(); put(route('invoices.update', invoice.id)); };

    return (
        <AuthenticatedLayout header={`Modifier facture ${invoice.invoice_number ?? ''}`}>
            <Head title={`Modifier ${invoice.invoice_number ?? 'facture'}`} />

            <form onSubmit={submit} className="space-y-6">
                <Link href={route('invoices.show', invoice.id)} className="inline-flex items-center gap-2 text-sm text-slate-600 hover:text-slate-900">
                    <ArrowLeft className="h-4 w-4" /> Retour à la facture
                </Link>

                {Object.keys(errors).length > 0 && (
                    <Alert variant="error">Certaines informations sont invalides.</Alert>
                )}

                <div className="grid gap-6 xl:grid-cols-3">
                    <div className="space-y-6 xl:col-span-2">
                        <div className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                            <h3 className="mb-4 text-base font-semibold">Client</h3>
                            <ContactSelector contacts={contacts} value={data.contact_id} onChange={(v) => setData('contact_id', v)} error={errors.contact_id} />
                        </div>
                        <div className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                            <h3 className="mb-4 text-base font-semibold">Informations</h3>
                            <InvoiceMeta data={data} setData={setData} errors={errors} />
                        </div>
                        <div className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                            <h3 className="mb-4 text-base font-semibold">Lignes</h3>
                            <LineTable lines={data.lines} computed={computed} taxRates={taxRates} accounts={accounts} setData={setData} errors={errors} />
                        </div>
                    </div>
                    <div className="space-y-6">
                        <div className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                            <h3 className="text-base font-semibold">Totaux</h3>
                            <dl className="mt-4 space-y-2 text-sm">
                                <div className="flex justify-between"><dt className="text-gray-600">Sous-total HT</dt><dd className="font-medium">{formatCurrency(totals.ht)}</dd></div>
                                <div className="flex justify-between"><dt className="text-gray-600">TVA</dt><dd className="font-medium">{formatCurrency(totals.vat)}</dd></div>
                                <div className="flex justify-between border-t border-gray-200 pt-2"><dt className="text-gray-600">Total TTC</dt><dd className="text-lg font-bold">{formatCurrency(totals.ttc)}</dd></div>
                            </dl>
                        </div>
                        <div className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                            <button type="submit" disabled={processing} className="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                                Enregistrer les modifications
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
