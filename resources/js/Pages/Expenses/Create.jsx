import { Head, Link, useForm } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import AsyncCombobox from '@/Components/UI/AsyncCombobox';

function FieldError({ message }) {
    if (!message) return null;

    return <p className="mt-1 text-sm text-rose-600">{message}</p>;
}

export default function Create({
    accounts = [],
    expenseAccounts = [],
    taxRates = [],
    prefill = {},
    prefillContact = null,
}) {
    const accountOptions = accounts.length > 0 ? accounts : expenseAccounts;
    const [contactPrefill, setContactPrefill] = useState(prefillContact);
    const { data, setData, post, processing, errors } = useForm({
        contact_id: prefill.contact_id || '',
        expense_date: prefill.expense_date || new Date().toISOString().slice(0, 10),
        reference: prefill.reference || '',
        description: prefill.vendor_name ? `Fournisseur: ${prefill.vendor_name}` : '',
        expense_account_id: prefill.expense_account_id || '',
        tax_rate_id: prefill.tax_rate_id || '',
        total_ht: prefill.total_ht ?? '',
        total_vat: prefill.total_vat ?? '',
        total_ttc: prefill.total_ttc ?? '',
        currency: prefill.currency || 'DZD',
        payment_method: prefill.payment_method || 'bank',
        status: 'draft',
        notes: '',
        source_document_id: prefill.source_document_id || '',
    });

    // To avoid triggering effect on init when values are prefilled
    const isFirstRun = useRef(true);

    // Create a map of tax rate percent to taxRate object for de-duplication and fast lookup
    const taxRateById = taxRates.reduce((carry, taxRate) => {
        carry[taxRate.id] = Number(taxRate.rate_percent ?? 0);
        return carry;
    }, {});

    // This effect will update VAT and TTC after both HT and tax_rate_id have been selected
    useEffect(() => {
        // Only auto-prefill when both HT and a VAT rate are chosen and not empty
        const totalHt = Number(data.total_ht);
        const rate = taxRateById[data.tax_rate_id];

        // When mounting, don't make a change if VAT already present (preserve possible prefill)
        if (isFirstRun.current) {
            isFirstRun.current = false;
            return;
        }

        // Only calculate VAT & TTC if both HT and a tax rate are selected, and the VAT and TTC haven't yet been prefilled, or the user changed one of the relevant fields
        if (
            data.total_ht !== '' &&
            !Number.isNaN(totalHt) &&
            data.tax_rate_id &&
            !Number.isNaN(rate)
        ) {
            // Calculate VAT
            const computedVat = ((totalHt * rate) / 100).toFixed(2);

            // Only auto-set if user hasn't typed in a VAT value or it's wrong (stay in sync)
            if (data.total_vat !== computedVat) {
                setData('total_vat', computedVat);
                // Do not immediately set total_ttc to allow effect to rerun after VAT is actually set
                return;
            }

            // Calculate TTC
            const computedTtc = (totalHt + Number(computedVat)).toFixed(2);
            if (data.total_ttc !== computedTtc) {
                setData('total_ttc', computedTtc);
                return;
            }
        }
        // If no VAT rate or HT is selected (resetting), clear vat and ttc fields
        if (
            (data.total_ht === '' || data.tax_rate_id === '' || Number.isNaN(totalHt) || Number.isNaN(rate)) &&
            (data.total_vat !== '' || data.total_ttc !== '')
        ) {
            setData('total_vat', '');
            setData('total_ttc', '');
        }
    // we only react to ht or vat rate change; total_vat and total_ttc will be autofilled and not be input by user (inputs are disabled)
    // eslint-disable-next-line
    }, [data.total_ht, data.tax_rate_id]);

    const submit = (e) => {
        e.preventDefault();
        post('/expenses');
    };

    return (
        <AuthenticatedLayout>
            <Head title="Nouvelle dépense" />

            <div className="space-y-6">
                {data.source_document_id && (
                    <div className="rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-900">
                        Les champs ont été pré-remplis à partir d'un document OCR.
                        Vérifiez chaque valeur avant d'enregistrer.
                    </div>
                )}

                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold text-slate-900">
                            Nouvelle dépense
                        </h1>
                        <p className="mt-1 text-sm text-slate-600">
                            Enregistrez une dépense fournisseur ou interne.
                        </p>
                    </div>

                    <Link
                        href="/expenses"
                        className="inline-flex items-center rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
                    >
                        Retour à la liste
                    </Link>
                </div>

                <form onSubmit={submit} className="space-y-6">
                    <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h2 className="text-lg font-semibold text-slate-900">
                            Informations générales
                        </h2>

                        <div className="mt-5 grid gap-5 md:grid-cols-2">
                            <div>
                                <label className="mb-1 block text-sm font-medium text-slate-700">
                                    Fournisseur
                                </label>
                                <AsyncCombobox
                                    endpoint="/suggest/contacts"
                                    value={data.contact_id}
                                    prefill={contactPrefill}
                                    onChange={(id, option) => {
                                        setData('contact_id', id || '');
                                        setContactPrefill(option ?? null);

                                        if (!data.description && option?.display_name) {
                                            setData('description', `Fournisseur: ${option.display_name}`);
                                        }

                                        if (!data.expense_account_id && option?.default_expense_account_id) {
                                            setData('expense_account_id', option.default_expense_account_id);
                                        }

                                        if (!data.tax_rate_id && option?.default_tax_rate_id) {
                                            setData('tax_rate_id', option.default_tax_rate_id);
                                        }
                                    }}
                                    getLabel={(c) => c.display_name}
                                    placeholder="Rechercher un fournisseur…"
                                    extraParams={{ type: 'supplier' }}
                                    ariaLabel="Fournisseur"
                                />
                                <FieldError message={errors.contact_id} />
                            </div>

                            <div>
                                <label className="mb-1 block text-sm font-medium text-slate-700">
                                    Date de dépense
                                </label>
                                <input
                                    type="date"
                                    value={data.expense_date}
                                    onChange={(e) => setData('expense_date', e.target.value)}
                                    className="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200"
                                />
                                <FieldError message={errors.expense_date} />
                            </div>

                            <div>
                                <label className="mb-1 block text-sm font-medium text-slate-700">
                                    Référence
                                </label>
                                <input
                                    type="text"
                                    value={data.reference}
                                    onChange={(e) => setData('reference', e.target.value)}
                                    placeholder="Laissez vide pour générer automatiquement"
                                    className="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200"
                                />
                                <FieldError message={errors.reference} />
                            </div>

                            <div>
                                <label className="mb-1 block text-sm font-medium text-slate-700">
                                    Statut
                                </label>
                                <select
                                    value={data.status}
                                    onChange={(e) => setData('status', e.target.value)}
                                    className="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200"
                                >
                                    <option value="draft">Brouillon</option>
                                    <option value="confirmed">Confirmée</option>
                                    <option value="paid">Payée</option>
                                </select>
                                <FieldError message={errors.status} />
                            </div>

                            <div className="md:col-span-2">
                                <label className="mb-1 block text-sm font-medium text-slate-700">
                                    Description
                                </label>
                                <input
                                    type="text"
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                    placeholder="Ex: Achat fournitures bureau"
                                    className="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200"
                                />
                                <FieldError message={errors.description} />
                            </div>
                        </div>
                    </div>

                    <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h2 className="text-lg font-semibold text-slate-900">
                            Comptabilisation
                        </h2>

                        <div className="mt-5 grid gap-5 md:grid-cols-2">
                            <div>
                                <label className="mb-1 block text-sm font-medium text-slate-700">
                                    Compte de charge
                                </label>
                                <select
                                    value={data.expense_account_id}
                                    onChange={(e) => setData('expense_account_id', e.target.value)}
                                    className="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200"
                                >
                                    {accountOptions.map((account) => {
                                        const isDefault = account.code === "601";
                                        return (
                                            <option
                                                key={account.id}
                                                value={account.id}
                                            >
                                                {account.code} — {account.label}
                                                {isDefault ? " (par défaut)" : ""}
                                            </option>
                                        );
                                    })}
                               

                                </select>
                                <FieldError message={errors.expense_account_id} />
                            </div>

                            <div>
                                <label className="mb-1 block text-sm font-medium text-slate-700">
                                    Taux de TVA
                                </label>
                                <select
                                    value={data.tax_rate_id}
                                    onChange={e => {
                                        setData('tax_rate_id', e.target.value);
                                    }}
                                    className="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200"
                                >
                                    <option value="">Sélectionner un taux</option>
                                    {[
                                        ...Object.values(
                                            taxRates.reduce((acc, taxRate) => {
                                                const key = Number(taxRate.rate_percent);
                                                if (!(key in acc)) acc[key] = taxRate;
                                                return acc;
                                            }, {})
                                        ),
                                    ].map((taxRate) => (
                                        <option key={taxRate.id} value={taxRate.id}>
                                            {taxRate.label}
                                        </option>
                                    ))}
                                </select>
                                <FieldError message={errors.tax_rate_id} />
                            </div>
                 

                            <div>
                                <label className="mb-1 block text-sm font-medium text-slate-700">
                                    Total HT
                                </label>
                                <input
                                    type="number"
                                    step="0.01"
                                    value={data.total_ht}
                                    onChange={e => {
                                        setData('total_ht', e.target.value);
                                    }}
                                    className="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200"
                                />
                                <FieldError message={errors.total_ht} />
                            </div>

                            <div>
                                <label className="mb-1 block text-sm font-medium text-slate-700">
                                    TVA
                                </label>
                                <input
                                    type="number"
                                    step="0.01"
                                    value={data.total_vat}
                                    disabled
                                    className="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm bg-slate-100 text-slate-900 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200 placeholder:text-slate-400"
                                    placeholder="Auto-calculé après sélection du taux de TVA et du Total HT"
                                />
                                <FieldError message={errors.total_vat} />
                            </div>

                            <div>
                                <label className="mb-1 block text-sm font-medium text-slate-700">
                                    Total TTC
                                </label>
                                <input
                                    type="number"
                                    step="0.01"
                                    value={data.total_ttc}
                                    disabled
                                    className="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm bg-slate-100 text-slate-900 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200 placeholder:text-slate-400"
                                    placeholder="Auto-calculé après HT & TVA"
                                />
                                <FieldError message={errors.total_ttc} />
                            </div>

                            <div>
                                <label className="mb-1 block text-sm font-medium text-slate-700">
                                    Devise
                                </label>
                                <select
                                    value={data.currency}
                                    onChange={(e) => setData('currency', e.target.value)}
                                    className="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200"
                                >
                                    <option value="DZD">DZD</option>
                                    <option value="EUR">EUR</option>
                                    <option value="USD">USD</option>
                                </select>
                                <FieldError message={errors.currency} />
                            </div>

                            <div>
                                <label className="mb-1 block text-sm font-medium text-slate-700">
                                    Mode de paiement
                                </label>
                                <select
                                    value={data.payment_method}
                                    onChange={(e) => setData('payment_method', e.target.value)}
                                    className="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200"
                                >
                                    <option value="bank">Banque</option>
                                    <option value="cash">Caisse</option>
                                    <option value="card">Carte</option>
                                    <option value="other">Autre</option>
                                </select>
                                <FieldError message={errors.payment_method} />
                            </div>

                            <div className="md:col-span-2">
                                <label className="mb-1 block text-sm font-medium text-slate-700">
                                    Notes
                                </label>
                                <textarea
                                    rows="4"
                                    value={data.notes}
                                    onChange={(e) => setData('notes', e.target.value)}
                                    placeholder="Informations complémentaires..."
                                    className="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200"
                                />
                                <FieldError message={errors.notes} />
                            </div>
                        </div>
                    </div>

                    <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                        <Link
                            href="/expenses"
                            className="inline-flex items-center justify-center rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
                        >
                            Annuler
                        </Link>

                        <button
                            type="submit"
                            disabled={processing}
                            className="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {processing ? 'Enregistrement...' : 'Créer la dépense'}
                        </button>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
