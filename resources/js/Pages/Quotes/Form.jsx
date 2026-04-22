import { Head, Link, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Alert from '@/Components/UI/Alert';
import AsyncCombobox from '@/Components/UI/AsyncCombobox';

const formatCurrency = (value) =>
    new Intl.NumberFormat('fr-DZ', { style: 'currency', currency: 'DZD' }).format(Number(value ?? 0));

export default function Form({ quote = null, prefillContact = null, currencies = [] }) {
    const [contactPrefill, setContactPrefill] = useState(prefillContact);

    const initialLines = (quote?.lines?.length ? quote.lines : [{}]).map((line) => ({
        description: line.description ?? '',
        quantity: Number(line.quantity ?? 1),
        unit_price: Number(line.unit_price ?? 0),
        vat_rate: Number(line.vat_rate ?? 19),
    }));

    const { data, setData, post, put, processing, errors } = useForm({
        contact_id: quote?.contact_id ?? '',
        issue_date: quote?.issue_date ?? new Date().toISOString().split('T')[0],
        expiry_date: quote?.expiry_date ?? '',
        currency_id: quote?.currency_id ?? '',
        reference: quote?.reference ?? '',
        notes: quote?.notes ?? '',
        lines: initialLines,
    });

    const computed = useMemo(() => {
        return data.lines.map((line) => {
            const ht = Math.round(Number(line.quantity ?? 0) * Number(line.unit_price ?? 0) * 100) / 100;
            const vat = Math.round((ht * Number(line.vat_rate ?? 0)) / 100 * 100) / 100;
            return {
                line_ht: ht,
                line_vat: vat,
                line_total: ht + vat,
            };
        });
    }, [data.lines]);

    const totals = computed.reduce(
        (acc, line) => ({
            subtotal: acc.subtotal + line.line_ht,
            tax: acc.tax + line.line_vat,
            total: acc.total + line.line_total,
        }),
        { subtotal: 0, tax: 0, total: 0 }
    );

    const updateLine = (index, key, value) => {
        const next = [...data.lines];
        next[index] = { ...next[index], [key]: value };
        setData('lines', next);
    };

    const addLine = () => {
        setData('lines', [...data.lines, { description: '', quantity: 1, unit_price: 0, vat_rate: 19 }]);
    };

    const removeLine = (index) => {
        if (data.lines.length === 1) return;
        setData(
            'lines',
            data.lines.filter((_, idx) => idx !== index)
        );
    };

    const submit = (e) => {
        e.preventDefault();
        if (quote) {
            put(`/quotes/${quote.id}`);
            return;
        }
        post('/quotes');
    };

    return (
        <AuthenticatedLayout header={quote ? `Modifier devis ${quote.number}` : 'Nouveau devis'}>
            <Head title={quote ? 'Modifier devis' : 'Nouveau devis'} />

            <form onSubmit={submit} className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h2 className="text-xl font-semibold text-gray-900">
                            {quote ? 'Modifier le devis' : 'Créer un devis'}
                        </h2>
                        <p className="text-sm text-gray-500">Renseignez les informations client et lignes</p>
                    </div>
                    <Link href="/quotes" className="text-sm text-gray-600 hover:text-gray-900">
                        Retour aux devis
                    </Link>
                </div>

                {Object.keys(errors).length > 0 && (
                    <Alert variant="error">Certaines informations sont invalides.</Alert>
                )}

                <div className="grid gap-6 xl:grid-cols-3">
                    <div className="space-y-6 xl:col-span-2">
                        <div className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                            <h3 className="mb-3 text-base font-semibold text-gray-900">Client</h3>
                            <AsyncCombobox
                                endpoint="/suggest/contacts"
                                value={data.contact_id}
                                prefill={contactPrefill}
                                onChange={(id, option) => {
                                    setData('contact_id', id || '');
                                    setContactPrefill(option ?? null);
                                }}
                                getLabel={(contact) => contact.display_name}
                                placeholder="Rechercher un client…"
                                extraParams={{ type: 'client' }}
                                ariaLabel="Client"
                            />
                            {errors.contact_id && <p className="mt-1 text-sm text-red-600">{errors.contact_id}</p>}
                        </div>

                        <div className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                            <h3 className="mb-3 text-base font-semibold text-gray-900">Informations</h3>
                            <div className="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Date</label>
                                    <input type="date" value={data.issue_date} onChange={(e) => setData('issue_date', e.target.value)} className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" />
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Expiration</label>
                                    <input type="date" value={data.expiry_date} onChange={(e) => setData('expiry_date', e.target.value)} className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" />
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Devise</label>
                                    <select value={data.currency_id} onChange={(e) => setData('currency_id', e.target.value)} className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                        <option value="">Devise société (DZD)</option>
                                        {currencies.map((currency) => (
                                            <option key={currency.id} value={currency.id}>{currency.code} - {currency.name}</option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Référence</label>
                                    <input type="text" value={data.reference} onChange={(e) => setData('reference', e.target.value)} className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" />
                                </div>
                                <div className="md:col-span-2">
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Notes</label>
                                    <textarea rows={3} value={data.notes} onChange={(e) => setData('notes', e.target.value)} className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" />
                                </div>
                            </div>
                        </div>

                        <div className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                            <h3 className="mb-3 text-base font-semibold text-gray-900">Lignes</h3>
                            <div className="overflow-x-auto rounded-xl border border-gray-200">
                                <table className="min-w-full divide-y divide-gray-200 bg-white text-sm">
                                    <thead className="bg-gray-50 text-xs uppercase tracking-wide text-gray-600">
                                        <tr>
                                            <th className="px-3 py-3 text-left">Description</th>
                                            <th className="px-3 py-3 text-left">Qté</th>
                                            <th className="px-3 py-3 text-left">PU HT</th>
                                            <th className="px-3 py-3 text-left">TVA %</th>
                                            <th className="px-3 py-3 text-right">Total</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-100">
                                        {data.lines.map((line, index) => (
                                            <tr key={index}>
                                                <td className="px-3 py-3">
                                                    <input
                                                        type="text"
                                                        value={line.description}
                                                        onChange={(e) => updateLine(index, 'description', e.target.value)}
                                                        className="w-72 rounded-lg border border-gray-300 px-3 py-2 text-sm"
                                                    />
                                                </td>
                                                <td className="px-3 py-3">
                                                    <input type="number" step="0.0001" value={line.quantity} onChange={(e) => updateLine(index, 'quantity', Number(e.target.value))} className="w-24 rounded-lg border border-gray-300 px-3 py-2 text-sm" />
                                                </td>
                                                <td className="px-3 py-3">
                                                    <input type="number" step="0.01" value={line.unit_price} onChange={(e) => updateLine(index, 'unit_price', Number(e.target.value))} className="w-28 rounded-lg border border-gray-300 px-3 py-2 text-sm" />
                                                </td>
                                                <td className="px-3 py-3">
                                                    <select value={line.vat_rate} onChange={(e) => updateLine(index, 'vat_rate', Number(e.target.value))} className="w-24 rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                                        <option value={0}>0%</option>
                                                        <option value={9}>9%</option>
                                                        <option value={19}>19%</option>
                                                    </select>
                                                </td>
                                                <td className="px-3 py-3 text-right font-medium text-gray-900">{formatCurrency(computed[index]?.line_total ?? 0)}</td>
                                                <td className="px-3 py-3">
                                                    <button type="button" onClick={() => removeLine(index)} className="rounded-md border border-red-300 px-2 py-1 text-xs text-red-700 hover:bg-red-50">
                                                        Suppr.
                                                    </button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            <button type="button" onClick={addLine} className="mt-3 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                Ajouter une ligne
                            </button>
                        </div>
                    </div>

                    <div className="space-y-6">
                        <div className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                            <h3 className="text-base font-semibold text-gray-900">Totaux</h3>
                            <dl className="mt-4 space-y-2 text-sm">
                                <div className="flex justify-between"><dt>Sous-total</dt><dd>{formatCurrency(totals.subtotal)}</dd></div>
                                <div className="flex justify-between"><dt>TVA</dt><dd>{formatCurrency(totals.tax)}</dd></div>
                                <div className="flex justify-between border-t border-gray-200 pt-2 font-semibold"><dt>Total</dt><dd>{formatCurrency(totals.total)}</dd></div>
                            </dl>
                        </div>
                        <div className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                            <button type="submit" disabled={processing} className="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                                {quote ? 'Enregistrer le devis' : 'Créer le devis'}
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
