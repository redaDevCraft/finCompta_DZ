import { Head, useForm } from '@inertiajs/react';
import { useMemo } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Alert from '@/Components/UI/Alert';

const formatCurrency = (value) =>
    new Intl.NumberFormat('fr-DZ', {
        style: 'currency',
        currency: 'DZD',
    }).format(Number(value ?? 0));

function ContactSelector({ contacts, value, onChange, error }) {
    return (
        <div>
            <label className="mb-1 block text-sm font-medium text-gray-700">
                Client
            </label>

            <select
                value={value}
                onChange={(e) => onChange(e.target.value)}
                className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200"
            >
                <option value="">Sélectionner un client</option>
                {contacts.map((contact) => (
                    <option key={contact.id} value={contact.id}>
                        {contact.display_name}
                    </option>
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
                <label className="mb-1 block text-sm font-medium text-gray-700">
                    Date d'émission
                </label>
                <input
                    type="date"
                    value={data.issue_date}
                    onChange={(e) => setData('issue_date', e.target.value)}
                    className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                />
                {errors.issue_date ? (
                    <p className="mt-1 text-sm text-red-600">{errors.issue_date}</p>
                ) : null}
            </div>

            <div>
                <label className="mb-1 block text-sm font-medium text-gray-700">
                    Date d'échéance
                </label>
                <input
                    type="date"
                    value={data.due_date}
                    onChange={(e) => setData('due_date', e.target.value)}
                    className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                />
                {errors.due_date ? (
                    <p className="mt-1 text-sm text-red-600">{errors.due_date}</p>
                ) : null}
            </div>

            <div>
                <label className="mb-1 block text-sm font-medium text-gray-700">
                    Type de document
                </label>
                <select
                    value={data.document_type}
                    onChange={(e) => setData('document_type', e.target.value)}
                    className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                >
                    <option value="invoice">Facture</option>
                    <option value="credit_note">Avoir</option>
                    <option value="quote">Devis</option>
                    <option value="delivery_note">Bon de livraison</option>
                </select>
                {errors.document_type ? (
                    <p className="mt-1 text-sm text-red-600">{errors.document_type}</p>
                ) : null}
            </div>

            <div>
                <label className="mb-1 block text-sm font-medium text-gray-700">
                    Mode de paiement
                </label>
                <input
                    type="text"
                    value={data.payment_mode}
                    onChange={(e) => setData('payment_mode', e.target.value)}
                    placeholder="Virement, chèque, espèces..."
                    className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                />
                {errors.payment_mode ? (
                    <p className="mt-1 text-sm text-red-600">{errors.payment_mode}</p>
                ) : null}
            </div>

            <div className="md:col-span-2">
                <label className="mb-1 block text-sm font-medium text-gray-700">
                    Notes
                </label>
                <textarea
                    rows={4}
                    value={data.notes}
                    onChange={(e) => setData('notes', e.target.value)}
                    className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                />
                {errors.notes ? (
                    <p className="mt-1 text-sm text-red-600">{errors.notes}</p>
                ) : null}
            </div>
        </div>
    );
}

function InvoiceLineTable({
    lines,
    computedLines,
    taxRates,
    accounts,
    setData,
    errors,
}) {
    const updateLine = (index, key, value) => {
        const next = [...lines];
        next[index] = { ...next[index], [key]: value };
        setData('lines', next);
    };

    const addLine = () => {
        setData('lines', [
            ...lines,
            {
                designation: '',
                quantity: 1,
                unit: '',
                unit_price_ht: 0,
                discount_pct: 0,
                vat_rate_pct: 19,
                tax_rate_id: '',
                account_id: '',
            },
        ]);
    };

    const removeLine = (index) => {
        if (lines.length === 1) return;
        setData(
            'lines',
            lines.filter((_, i) => i !== index)
        );
    };

    const moveLine = (index, direction) => {
        const target = index + direction;
        if (target < 0 || target >= lines.length) return;

        const next = [...lines];
        [next[index], next[target]] = [next[target], next[index]];
        setData('lines', next);
    };

    return (
        <div className="space-y-4">
            <div className="overflow-x-auto rounded-xl border border-gray-200">
                <table className="min-w-full divide-y divide-gray-200 bg-white">
                    <thead className="bg-gray-50">
                        <tr>
                            <th className="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                                Désignation
                            </th>
                            <th className="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                                Qté
                            </th>
                            <th className="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                                Unité
                            </th>
                            <th className="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                                PU HT
                            </th>
                            <th className="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                                Remise %
                            </th>
                            <th className="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                                TVA %
                            </th>
                            <th className="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                                Taxe
                            </th>
                            <th className="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                                Compte
                            </th>
                            <th className="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                                HT
                            </th>
                            <th className="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                                TVA
                            </th>
                            <th className="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                                TTC
                            </th>
                            <th className="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                                Actions
                            </th>
                        </tr>
                    </thead>

                    <tbody className="divide-y divide-gray-100">
                        {lines.map((line, index) => {
                            const computed = computedLines[index];

                            return (
                                <tr key={index}>
                                    <td className="px-3 py-3 align-top">
                                        <input
                                            type="text"
                                            value={line.designation}
                                            onChange={(e) => updateLine(index, 'designation', e.target.value)}
                                            className="w-64 rounded-lg border border-gray-300 px-3 py-2 text-sm"
                                        />
                                        {errors[`lines.${index}.designation`] ? (
                                            <p className="mt-1 text-xs text-red-600">
                                                {errors[`lines.${index}.designation`]}
                                            </p>
                                        ) : null}
                                    </td>

                                    <td className="px-3 py-3 align-top">
                                        <input
                                            type="number"
                                            min="0.0001"
                                            step="0.0001"
                                            value={line.quantity}
                                            onChange={(e) => updateLine(index, 'quantity', Number(e.target.value))}
                                            className="w-24 rounded-lg border border-gray-300 px-3 py-2 text-sm"
                                        />
                                    </td>

                                    <td className="px-3 py-3 align-top">
                                        <input
                                            type="text"
                                            value={line.unit}
                                            onChange={(e) => updateLine(index, 'unit', e.target.value)}
                                            className="w-24 rounded-lg border border-gray-300 px-3 py-2 text-sm"
                                        />
                                    </td>

                                    <td className="px-3 py-3 align-top">
                                        <input
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            value={line.unit_price_ht}
                                            onChange={(e) =>
                                                updateLine(index, 'unit_price_ht', Number(e.target.value))
                                            }
                                            className="w-28 rounded-lg border border-gray-300 px-3 py-2 text-sm"
                                        />
                                    </td>

                                    <td className="px-3 py-3 align-top">
                                        <input
                                            type="number"
                                            min="0"
                                            max="100"
                                            step="0.01"
                                            value={line.discount_pct}
                                            onChange={(e) =>
                                                updateLine(index, 'discount_pct', Number(e.target.value))
                                            }
                                            className="w-24 rounded-lg border border-gray-300 px-3 py-2 text-sm"
                                        />
                                    </td>

                                    <td className="px-3 py-3 align-top">
                                        <select
                                            value={line.vat_rate_pct}
                                            onChange={(e) =>
                                                updateLine(index, 'vat_rate_pct', Number(e.target.value))
                                            }
                                            className="w-24 rounded-lg border border-gray-300 px-3 py-2 text-sm"
                                        >
                                            <option value={0}>0%</option>
                                            <option value={9}>9%</option>
                                            <option value={19}>19%</option>
                                        </select>
                                    </td>

                                    <td className="px-3 py-3 align-top">
                                        <select
                                            value={line.tax_rate_id}
                                            onChange={(e) => updateLine(index, 'tax_rate_id', e.target.value)}
                                            className="w-40 rounded-lg border border-gray-300 px-3 py-2 text-sm"
                                        >
                                            <option value="">Aucune</option>
                                            {taxRates.map((taxRate) => (
                                                <option key={taxRate.id} value={taxRate.id}>
                                                    {taxRate.name ?? `${taxRate.rate_percent ?? line.vat_rate_pct}%`}
                                                </option>
                                            ))}
                                        </select>
                                    </td>

                                    <td className="px-3 py-3 align-top">
                                        <select
                                            value={line.account_id}
                                            onChange={(e) => updateLine(index, 'account_id', e.target.value)}
                                            className="w-48 rounded-lg border border-gray-300 px-3 py-2 text-sm"
                                        >
                                            <option value="">Aucun compte</option>
                                            {accounts.map((account) => (
                                               <option key={account.id} value={account.id}>
                                               {account.code} - {account.label}
                                           </option>
                                            ))}
                                        </select>
                                    </td>

                                    <td className="px-3 py-3 text-sm text-gray-700">
                                        {formatCurrency(computed.line_ht)}
                                    </td>
                                    <td className="px-3 py-3 text-sm text-gray-700">
                                        {formatCurrency(computed.line_vat)}
                                    </td>
                                    <td className="px-3 py-3 text-sm font-medium text-gray-900">
                                        {formatCurrency(computed.line_ttc)}
                                    </td>

                                    <td className="px-3 py-3">
                                        <div className="flex flex-col gap-2">
                                            <button
                                                type="button"
                                                onClick={() => moveLine(index, -1)}
                                                className="rounded-md border border-gray-300 px-2 py-1 text-xs text-gray-700 hover:bg-gray-50"
                                            >
                                                ↑
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => moveLine(index, 1)}
                                                className="rounded-md border border-gray-300 px-2 py-1 text-xs text-gray-700 hover:bg-gray-50"
                                            >
                                                ↓
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => removeLine(index)}
                                                className="rounded-md border border-red-300 px-2 py-1 text-xs text-red-700 hover:bg-red-50"
                                            >
                                                Suppr.
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>

            <button
                type="button"
                onClick={addLine}
                className="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
            >
                Ajouter une ligne
            </button>
        </div>
    );
}

function InvoiceTotals({ computedLines }) {
    const subtotalHt = computedLines.reduce((sum, line) => sum + Number(line.line_ht ?? 0), 0);
    const totalTva = computedLines.reduce((sum, line) => sum + Number(line.line_vat ?? 0), 0);
    const totalTtc = computedLines.reduce((sum, line) => sum + Number(line.line_ttc ?? 0), 0);

    const vatBuckets = computedLines.reduce((acc, line) => {
        const rate = Number(line.vat_rate_pct ?? 0);
        acc[rate] = Math.round(((acc[rate] ?? 0) + Number(line.line_vat ?? 0)) * 100) / 100;
        return acc;
    }, {});

    return (
        <div className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <h3 className="text-base font-semibold text-gray-900">Totaux</h3>

            <div className="mt-4 space-y-3 text-sm">
                <div className="flex items-center justify-between">
                    <span className="text-gray-600">Sous-total HT</span>
                    <span className="font-medium text-gray-900">
                        {formatCurrency(subtotalHt)}
                    </span>
                </div>

                {Object.keys(vatBuckets)
                    .sort((a, b) => Number(a) - Number(b))
                    .map((rate) => (
                        <div key={rate} className="flex items-center justify-between">
                            <span className="text-gray-600">TVA {rate}%</span>
                            <span className="font-medium text-gray-900">
                                {formatCurrency(vatBuckets[rate])}
                            </span>
                        </div>
                    ))}

                <div className="flex items-center justify-between border-t border-gray-200 pt-3">
                    <span className="text-gray-600">Total TTC</span>
                    <span className="text-lg font-bold text-gray-900">
                        {formatCurrency(totalTtc)}
                    </span>
                </div>
            </div>
        </div>
    );
}

export default function Create({ contacts = [], taxRates = [], accounts = [] }) {
    const { data, setData, post, processing, errors } = useForm({
        contact_id: '',
        issue_date: new Date().toISOString().split('T')[0],
        due_date: '',
        document_type: 'invoice',
        payment_mode: '',
        notes: '',
        lines: [
            {
                designation: '',
                quantity: 1,
                unit: '',
                unit_price_ht: 0,
                discount_pct: 0,
                vat_rate_pct: 19,
                tax_rate_id: '',
                account_id: '',
            },
        ],
    });

    const computedLines = useMemo(
        () =>
            data.lines.map((l) => {
                const quantity = Number(l.quantity ?? 0);
                const unitPriceHt = Number(l.unit_price_ht ?? 0);
                const discountPct = Number(l.discount_pct ?? 0);
                const vatRatePct = Number(l.vat_rate_pct ?? 0);

                const lineHt =
                    Math.round(quantity * unitPriceHt * (1 - discountPct / 100) * 100) / 100;
                const lineVat = Math.round((lineHt * vatRatePct) / 100 * 100) / 100;

                return {
                    ...l,
                    line_ht: lineHt,
                    line_vat: lineVat,
                    line_ttc: lineHt + lineVat,
                };
            }),
        [data.lines]
    );

    const submitDraft = (e) => {
        e.preventDefault();
        post('/invoices');
    };

    const submitAndIssue = (e) => {
        e.preventDefault();

        post('/invoices', {
            onSuccess: (page) => {
                const invoiceId = page?.props?.invoice?.id;
                if (invoiceId) {
                    router.post(`/invoices/${invoiceId}/issue`);
                }
            },
        });
    };

    return (
        <AuthenticatedLayout header="Nouvelle facture">
            <Head title="Nouvelle facture" />

            <form onSubmit={submitDraft} className="space-y-6">
                <div>
                    <h2 className="text-xl font-semibold text-gray-900">Créer une facture</h2>
                    <p className="text-sm text-gray-500">
                        Saisissez les informations générales et les lignes de facture
                    </p>
                </div>

                {Object.keys(errors).length > 0 && (
                    <Alert variant="error">
                        Certaines informations sont invalides. Veuillez corriger le formulaire.
                    </Alert>
                )}

                <div className="grid gap-6 xl:grid-cols-3">
                    <div className="space-y-6 xl:col-span-2">
                        <div className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                            <h3 className="mb-4 text-base font-semibold text-gray-900">
                                Client
                            </h3>
                            <ContactSelector
                                contacts={contacts}
                                value={data.contact_id}
                                onChange={(value) => setData('contact_id', value)}
                                error={errors.contact_id}
                            />
                        </div>

                        <div className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                            <h3 className="mb-4 text-base font-semibold text-gray-900">
                                Informations de facture
                            </h3>
                            <InvoiceMeta data={data} setData={setData} errors={errors} />
                        </div>

                        <div className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                            <div className="mb-4">
                                <h3 className="text-base font-semibold text-gray-900">
                                    Lignes de facture
                                </h3>
                            </div>

                            <InvoiceLineTable
                                lines={data.lines}
                                computedLines={computedLines}
                                taxRates={taxRates}
                                accounts={accounts}
                                setData={setData}
                                errors={errors}
                            />
                        </div>
                    </div>

                    <div className="space-y-6">
                        <InvoiceTotals computedLines={computedLines} />

                        <div className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                            <div className="space-y-3">
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="w-full rounded-lg bg-gray-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-black disabled:opacity-50"
                                >
                                    Enregistrer brouillon
                                </button>

                                <button
                                    type="button"
                                    onClick={submitAndIssue}
                                    disabled={processing}
                                    className="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                                >
                                    Enregistrer et émettre
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}