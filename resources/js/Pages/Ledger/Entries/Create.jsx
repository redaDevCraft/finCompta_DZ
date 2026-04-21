import { Head, Link, router, useForm } from '@inertiajs/react';
import { useMemo } from 'react';
import { Plus, Trash2 } from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import AsyncCombobox from '@/Components/UI/AsyncCombobox';

function FieldError({ message }) {
    if (!message) return null;

    return <p className="mt-1 text-sm text-rose-600">{message}</p>;
}

function formatMoney(value) {
    return new Intl.NumberFormat('fr-DZ', {
        style: 'currency',
        currency: 'DZD',
        minimumFractionDigits: 2,
    }).format(Number(value ?? 0));
}

function emptyLine() {
    return {
        account_id: '',
        contact_id: '',
        description: '',
        debit: '',
        credit: '',
    };
}

const accountLabel = (a) => `${a.code} — ${a.label}`;
const contactLabel = (c) => c.display_name;

export default function CreateJournalEntry({
    form = {},
    journals = [],
    prefillAccounts = [],
    prefillContacts = [],
    isEdit = false,
}) {
    const { data, setData, processing, errors, reset } = useForm({
        entry_date: form.entry_date || new Date().toISOString().slice(0, 10),
        journal_id: form.journal_id || '',
        reference: form.reference || '',
        description: form.description || '',
        lines: (form.lines && form.lines.length >= 2
            ? form.lines
            : [emptyLine(), emptyLine()]),
        post_immediately: false,
    });

    // Index prefills by id so each line's AsyncCombobox can resolve its
    // initial display label without hitting the wire. Built once per
    // navigation; the combobox itself caches subsequent queries.
    const accountsById = useMemo(() => {
        const map = new Map();
        for (const a of prefillAccounts) map.set(a.id, a);
        return map;
    }, [prefillAccounts]);

    const contactsById = useMemo(() => {
        const map = new Map();
        for (const c of prefillContacts) map.set(c.id, c);
        return map;
    }, [prefillContacts]);

    const totals = useMemo(() => {
        const debit = data.lines.reduce(
            (sum, l) => sum + (parseFloat(l.debit) || 0),
            0
        );
        const credit = data.lines.reduce(
            (sum, l) => sum + (parseFloat(l.credit) || 0),
            0
        );

        return {
            debit,
            credit,
            diff: Math.round((debit - credit) * 100) / 100,
            balanced: Math.abs(debit - credit) < 0.01 && debit > 0,
        };
    }, [data.lines]);

    const setLine = (index, field, value) => {
        const next = data.lines.map((l, i) => (i === index ? { ...l, [field]: value } : l));

        if (field === 'debit' && value !== '' && parseFloat(value) > 0) {
            next[index].credit = '';
        } else if (field === 'credit' && value !== '' && parseFloat(value) > 0) {
            next[index].debit = '';
        }

        setData('lines', next);
    };

    const addLine = () => setData('lines', [...data.lines, emptyLine()]);

    const removeLine = (index) => {
        if (data.lines.length <= 2) return;
        setData('lines', data.lines.filter((_, i) => i !== index));
    };

    const submit = (e) => {
        e.preventDefault();

        const payload = {
            entry_date: data.entry_date,
            journal_id: data.journal_id,
            reference: data.reference || null,
            description: data.description || null,
            post_immediately: data.post_immediately,
            lines: data.lines.map((l) => ({
                account_id: l.account_id || null,
                contact_id: l.contact_id || null,
                description: l.description || null,
                debit: parseFloat(l.debit) || 0,
                credit: parseFloat(l.credit) || 0,
            })),
        };

        if (isEdit && form.id) {
            router.put(route('ledger.entries.update', form.id), payload, {
                preserveScroll: true,
            });
            return;
        }

        router.post(route('ledger.entries.store'), payload, {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    return (
        <AuthenticatedLayout header={isEdit ? 'Modifier une écriture' : 'Nouvelle écriture'}>
            <Head title={isEdit ? 'Modifier l’écriture' : 'Saisie d’écriture'} />

            <div className="space-y-6">
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold text-slate-900">
                            {isEdit ? 'Modifier l’écriture' : 'Saisie d’écriture'}
                        </h1>
                        <p className="mt-1 text-sm text-slate-600">
                            Saisissez une écriture comptable en partie double.
                            Les montants doivent s’équilibrer avant validation.
                        </p>
                    </div>

                    <Link
                        href={route('ledger.journal')}
                        className="inline-flex items-center rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
                    >
                        Retour au journal
                    </Link>
                </div>

                <form onSubmit={submit} className="space-y-6">
                    <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h2 className="text-lg font-semibold text-slate-900">En-tête</h2>

                        <div className="mt-5 grid gap-5 md:grid-cols-4">
                            <div>
                                <label className="mb-1 block text-sm font-medium text-slate-700">
                                    Date de l’écriture *
                                </label>
                                <input
                                    type="date"
                                    value={data.entry_date}
                                    onChange={(e) => setData('entry_date', e.target.value)}
                                    className="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"
                                    required
                                />
                                <FieldError message={errors.entry_date} />
                            </div>

                            <div>
                                <label className="mb-1 block text-sm font-medium text-slate-700">
                                    Journal *
                                </label>
                                <select
                                    value={data.journal_id}
                                    onChange={(e) => setData('journal_id', e.target.value)}
                                    className="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"
                                    required
                                >
                                    <option value="">— Sélectionner —</option>
                                    {journals.map((j) => (
                                        <option key={j.id} value={j.id}>
                                            {j.code} — {j.label}
                                        </option>
                                    ))}
                                </select>
                                <FieldError message={errors.journal_id} />
                            </div>

                            <div>
                                <label className="mb-1 block text-sm font-medium text-slate-700">
                                    Pièce / référence
                                </label>
                                <input
                                    type="text"
                                    value={data.reference}
                                    onChange={(e) => setData('reference', e.target.value)}
                                    placeholder="N° de pièce"
                                    className="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"
                                    maxLength={100}
                                />
                                <FieldError message={errors.reference} />
                            </div>

                            <div>
                                <label className="mb-1 block text-sm font-medium text-slate-700">
                                    Libellé général
                                </label>
                                <input
                                    type="text"
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                    placeholder="Libellé de l’écriture"
                                    className="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"
                                    maxLength={500}
                                />
                                <FieldError message={errors.description} />
                            </div>
                        </div>
                    </div>

                    <div className="rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <div className="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                            <h2 className="text-lg font-semibold text-slate-900">Lignes</h2>
                            <button
                                type="button"
                                onClick={addLine}
                                className="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                            >
                                <Plus className="h-4 w-4" /> Ajouter une ligne
                            </button>
                        </div>

                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-200">
                                <thead className="bg-slate-50">
                                    <tr>
                                        <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 w-[24%]">
                                            Compte
                                        </th>
                                        <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 w-[18%]">
                                            Tiers
                                        </th>
                                        <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                            Libellé
                                        </th>
                                        <th className="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wide text-slate-500 w-[14%]">
                                            Débit
                                        </th>
                                        <th className="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wide text-slate-500 w-[14%]">
                                            Crédit
                                        </th>
                                        <th className="px-3 py-2 w-10"></th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {data.lines.map((line, index) => {
                                        const lineErrorKeys = [
                                            `lines.${index}.account_id`,
                                            `lines.${index}.contact_id`,
                                            `lines.${index}.debit`,
                                            `lines.${index}.credit`,
                                            `lines.${index}.description`,
                                        ];
                                        const lineError = lineErrorKeys
                                            .map((k) => errors[k])
                                            .filter(Boolean)[0];

                                        return (
                                            <tr key={index} className={lineError ? 'bg-rose-50' : ''}>
                                                <td className="px-3 py-2 align-top">
                                                    <AsyncCombobox
                                                        endpoint="/suggest/accounts"
                                                        value={line.account_id}
                                                        prefill={accountsById.get(line.account_id) ?? null}
                                                        onChange={(id) => setLine(index, 'account_id', id || '')}
                                                        getLabel={accountLabel}
                                                        placeholder="Tapez un code…"
                                                        minChars={1}
                                                        required
                                                        ariaLabel={`Compte de la ligne ${index + 1}`}
                                                    />
                                                </td>
                                                <td className="px-3 py-2 align-top">
                                                    <AsyncCombobox
                                                        endpoint="/suggest/contacts"
                                                        value={line.contact_id}
                                                        prefill={contactsById.get(line.contact_id) ?? null}
                                                        onChange={(id) => setLine(index, 'contact_id', id || '')}
                                                        getLabel={contactLabel}
                                                        placeholder="Optionnel"
                                                        ariaLabel={`Tiers de la ligne ${index + 1}`}
                                                    />
                                                </td>
                                                <td className="px-3 py-2 align-top">
                                                    <input
                                                        type="text"
                                                        value={line.description || ''}
                                                        onChange={(e) =>
                                                            setLine(index, 'description', e.target.value)
                                                        }
                                                        placeholder="Libellé de ligne"
                                                        className="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm"
                                                        maxLength={500}
                                                    />
                                                </td>
                                                <td className="px-3 py-2 align-top text-right">
                                                    <input
                                                        type="number"
                                                        step="0.01"
                                                        min="0"
                                                        value={line.debit}
                                                        onChange={(e) =>
                                                            setLine(index, 'debit', e.target.value)
                                                        }
                                                        className="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-right text-sm"
                                                    />
                                                </td>
                                                <td className="px-3 py-2 align-top text-right">
                                                    <input
                                                        type="number"
                                                        step="0.01"
                                                        min="0"
                                                        value={line.credit}
                                                        onChange={(e) =>
                                                            setLine(index, 'credit', e.target.value)
                                                        }
                                                        className="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-right text-sm"
                                                    />
                                                </td>
                                                <td className="px-1 py-2 text-right align-top">
                                                    <button
                                                        type="button"
                                                        onClick={() => removeLine(index)}
                                                        disabled={data.lines.length <= 2}
                                                        className="rounded-lg p-1 text-slate-400 hover:bg-rose-50 hover:text-rose-600 disabled:opacity-30"
                                                        title="Supprimer la ligne"
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </button>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                                <tfoot className="bg-slate-50">
                                    <tr>
                                        <td
                                            className="px-3 py-3 text-right text-sm font-semibold text-slate-700"
                                            colSpan={3}
                                        >
                                            Totaux
                                        </td>
                                        <td className="px-3 py-3 text-right text-sm font-semibold text-slate-900">
                                            {formatMoney(totals.debit)}
                                        </td>
                                        <td className="px-3 py-3 text-right text-sm font-semibold text-slate-900">
                                            {formatMoney(totals.credit)}
                                        </td>
                                        <td />
                                    </tr>
                                    <tr>
                                        <td
                                            className="px-3 py-3 text-right text-sm text-slate-600"
                                            colSpan={3}
                                        >
                                            Écart (Débit − Crédit)
                                        </td>
                                        <td
                                            className="px-3 py-3 text-right text-sm font-semibold"
                                            colSpan={2}
                                        >
                                            <span
                                                className={
                                                    totals.balanced
                                                        ? 'text-emerald-700'
                                                        : 'text-rose-700'
                                                }
                                            >
                                                {formatMoney(totals.diff)}
                                                {totals.balanced && ' ✓ équilibré'}
                                            </span>
                                        </td>
                                        <td />
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        {errors.lines && (
                            <div className="border-t border-rose-200 bg-rose-50 px-6 py-3 text-sm text-rose-700">
                                {errors.lines}
                            </div>
                        )}
                    </div>

                    <div className="flex items-center justify-between rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <label className="flex items-center gap-2 text-sm text-slate-700">
                            <input
                                type="checkbox"
                                checked={data.post_immediately}
                                onChange={(e) =>
                                    setData('post_immediately', e.target.checked)
                                }
                                className="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                            />
                            Valider (comptabiliser) immédiatement après enregistrement
                        </label>

                        <div className="flex items-center gap-3">
                            <Link
                                href={route('ledger.journal')}
                                className="inline-flex items-center rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
                            >
                                Annuler
                            </Link>
                            <button
                                type="submit"
                                disabled={processing || !totals.balanced}
                                className="inline-flex items-center rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                            >
                                {isEdit ? 'Enregistrer les modifications' : 'Enregistrer l’écriture'}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
