import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useNotification } from '@/Context/NotificationContext';

function formatMoney(value) {
    return new Intl.NumberFormat('fr-DZ', {
        style: 'currency',
        currency: 'DZD',
        minimumFractionDigits: 2,
    }).format(Number(value ?? 0));
}

function formatDate(value) {
    if (!value) return '—';
    return new Intl.DateTimeFormat('fr-FR', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).format(new Date(value));
}

const matchTypeLabels = {
    manual: 'Manuel',
    auto_reference: 'Auto (pièce)',
    auto_amount: 'Auto (montant)',
};

export default function Lettering({
    accounts = [],
    contacts = [],
    selectedAccountId = '',
    selectedContactId = '',
    account = null,
    unletteredLines = [],
    letterings = [],
    openBalance = 0,
}) {
    const { confirm } = useNotification();

    const [accountId, setAccountId] = useState(selectedAccountId || '');
    const [contactId, setContactId] = useState(selectedContactId || '');
    const [selected, setSelected] = useState(new Set());
    const [expandedLettering, setExpandedLettering] = useState(null);

    const matchForm = useForm({
        account_id: account?.id ?? '',
        contact_id: selectedContactId || '',
        line_ids: [],
        notes: '',
    });

    const autoForm = useForm({
        account_id: account?.id ?? '',
        contact_id: selectedContactId || '',
        mode: 'reference',
    });

    const applyFilters = (e) => {
        e.preventDefault();
        setSelected(new Set());
        router.get(
            route('ledger.lettering'),
            {
                account_id: accountId || undefined,
                contact_id: contactId || undefined,
            },
            { preserveState: true, replace: true }
        );
    };

    const toggleLine = (id) => {
        const next = new Set(selected);
        if (next.has(id)) next.delete(id);
        else next.add(id);
        setSelected(next);
    };

    const selectedLines = useMemo(
        () => unletteredLines.filter((l) => selected.has(l.id)),
        [unletteredLines, selected]
    );

    const totals = useMemo(() => {
        const d = selectedLines.reduce((s, l) => s + (l.debit || 0), 0);
        const c = selectedLines.reduce((s, l) => s + (l.credit || 0), 0);

        return {
            debit: d,
            credit: c,
            diff: Math.round((d - c) * 100) / 100,
            balanced: selectedLines.length >= 2 && Math.abs(d - c) < 0.01,
        };
    }, [selectedLines]);

    const submitManual = (e) => {
        e.preventDefault();
        matchForm.setData({
            account_id: account?.id ?? '',
            contact_id: contactId || '',
            line_ids: Array.from(selected),
            notes: matchForm.data.notes || '',
        });
        matchForm.transform((data) => ({
            ...data,
            account_id: account?.id ?? '',
            contact_id: contactId || '',
            line_ids: Array.from(selected),
        }));
        matchForm.post(route('ledger.lettering.manual'), {
            preserveScroll: true,
            onSuccess: () => setSelected(new Set()),
        });
    };

    const runAuto = (mode) => {
        autoForm.transform(() => ({
            account_id: account?.id ?? '',
            contact_id: contactId || '',
            mode,
        }));
        autoForm.post(route('ledger.lettering.auto'), {
            preserveScroll: true,
        });
    };

    const unmatch = async (letteringId, code) => {
        const ok = await confirm({
            title: 'Supprimer le lettrage',
            message: `Supprimer le lettrage ${code} ?`,
            confirmLabel: 'Supprimer',
        });
        if (!ok) return;
        router.delete(route('ledger.lettering.destroy', letteringId), {
            preserveScroll: true,
        });
    };

    const debitLines = unletteredLines.filter((l) => l.debit > 0);
    const creditLines = unletteredLines.filter((l) => l.credit > 0);

    return (
        <AuthenticatedLayout header="Lettrage">
            <Head title="Lettrage" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-semibold text-slate-900">Lettrage</h1>
                    <p className="mt-1 text-sm text-slate-600">
                        Rapprochez les mouvements débiteurs et créditeurs d’un compte de tiers
                        (401 Fournisseurs, 411 Clients, 42x Personnel…).
                    </p>
                </div>

                <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <form onSubmit={applyFilters} className="grid gap-4 md:grid-cols-4">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">
                                Compte de tiers *
                            </label>
                            <select
                                value={accountId}
                                onChange={(e) => setAccountId(e.target.value)}
                                className="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"
                            >
                                <option value="">— Sélectionner —</option>
                                {accounts.map((a) => (
                                    <option key={a.id} value={a.id}>
                                        {a.code} — {a.label}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">
                                Tiers (optionnel)
                            </label>
                            <select
                                value={contactId}
                                onChange={(e) => setContactId(e.target.value)}
                                className="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"
                            >
                                <option value="">Tous les tiers</option>
                                {contacts.map((c) => (
                                    <option key={c.id} value={c.id}>
                                        {c.display_name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="flex items-end">
                            <button
                                type="submit"
                                className="inline-flex w-full items-center justify-center rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-slate-800"
                            >
                                Afficher
                            </button>
                        </div>
                        {account && (
                            <div className="flex items-end justify-end gap-2">
                                <button
                                    type="button"
                                    onClick={() => runAuto('reference')}
                                    disabled={autoForm.processing}
                                    className="inline-flex items-center rounded-xl border border-indigo-300 bg-indigo-50 px-3 py-2.5 text-sm font-medium text-indigo-700 hover:bg-indigo-100 disabled:opacity-50"
                                >
                                    Auto par pièce
                                </button>
                                <button
                                    type="button"
                                    onClick={() => runAuto('amount')}
                                    disabled={autoForm.processing}
                                    className="inline-flex items-center rounded-xl border border-indigo-300 bg-indigo-50 px-3 py-2.5 text-sm font-medium text-indigo-700 hover:bg-indigo-100 disabled:opacity-50"
                                >
                                    Auto par montant
                                </button>
                            </div>
                        )}
                    </form>
                </div>

                {account ? (
                    <>
                        <div className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <div>
                                <div className="text-xs font-medium uppercase tracking-wide text-slate-500">
                                    Compte
                                </div>
                                <div className="mt-1 text-xl font-semibold text-slate-900">
                                    {account.code} — {account.label}
                                </div>
                            </div>
                            <div className="text-right">
                                <div className="text-xs text-slate-500">
                                    Solde non lettré{contactId ? ' (tiers filtré)' : ''}
                                </div>
                                <div
                                    className={`mt-1 text-xl font-semibold ${
                                        openBalance >= 0 ? 'text-emerald-700' : 'text-rose-700'
                                    }`}
                                >
                                    {formatMoney(openBalance)}
                                </div>
                                <div className="mt-1 text-xs text-slate-500">
                                    {unletteredLines.length} lignes ouvertes
                                </div>
                            </div>
                        </div>

                        <form onSubmit={submitManual} className="space-y-4">
                            <div className="grid gap-4 lg:grid-cols-2">
                                <LinesPanel
                                    title="Débits (ex: factures, créances)"
                                    side="debit"
                                    lines={debitLines}
                                    selected={selected}
                                    onToggle={toggleLine}
                                />
                                <LinesPanel
                                    title="Crédits (ex: règlements)"
                                    side="credit"
                                    lines={creditLines}
                                    selected={selected}
                                    onToggle={toggleLine}
                                />
                            </div>

                            <div className="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                                <div className="grid grid-cols-3 gap-6 text-right">
                                    <div>
                                        <div className="text-xs text-slate-500">Débits sélectionnés</div>
                                        <div className="mt-1 text-sm font-semibold text-slate-900">
                                            {formatMoney(totals.debit)}
                                        </div>
                                    </div>
                                    <div>
                                        <div className="text-xs text-slate-500">Crédits sélectionnés</div>
                                        <div className="mt-1 text-sm font-semibold text-slate-900">
                                            {formatMoney(totals.credit)}
                                        </div>
                                    </div>
                                    <div>
                                        <div className="text-xs text-slate-500">Écart</div>
                                        <div
                                            className={`mt-1 text-sm font-semibold ${
                                                totals.balanced
                                                    ? 'text-emerald-700'
                                                    : 'text-rose-700'
                                            }`}
                                        >
                                            {formatMoney(totals.diff)}
                                            {totals.balanced && ' ✓'}
                                        </div>
                                    </div>
                                </div>

                                <div className="flex flex-1 items-end justify-end gap-3">
                                    <input
                                        type="text"
                                        placeholder="Notes (optionnel)"
                                        value={matchForm.data.notes}
                                        onChange={(e) => matchForm.setData('notes', e.target.value)}
                                        className="w-full max-w-xs rounded-xl border border-slate-300 px-3 py-2.5 text-sm"
                                        maxLength={500}
                                    />
                                    <button
                                        type="submit"
                                        disabled={!totals.balanced || matchForm.processing}
                                        className="inline-flex items-center rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                                    >
                                        Lettrer {selected.size > 0 && `(${selected.size})`}
                                    </button>
                                </div>
                            </div>
                        </form>

                        {letterings.length > 0 && (
                            <div className="rounded-2xl border border-slate-200 bg-white shadow-sm">
                                <div className="border-b border-slate-200 px-6 py-4">
                                    <h2 className="text-lg font-semibold text-slate-900">
                                        Lettrages existants ({letterings.length})
                                    </h2>
                                </div>
                                <div className="divide-y divide-slate-100">
                                    {letterings.map((l) => (
                                        <div key={l.id} className="px-6 py-3">
                                            <div className="flex flex-wrap items-center justify-between gap-3">
                                                <div className="flex items-center gap-3">
                                                    <span className="rounded-lg bg-indigo-100 px-2 py-1 font-mono text-xs font-semibold text-indigo-800">
                                                        {l.code}
                                                    </span>
                                                    <span className="text-sm font-medium text-slate-900">
                                                        {formatMoney(l.total_amount)}
                                                    </span>
                                                    <span className="text-xs text-slate-500">
                                                        {matchTypeLabels[l.match_type] ?? l.match_type}
                                                    </span>
                                                    {l.contact && (
                                                        <span className="text-xs text-slate-500">
                                                            · {l.contact.display_name}
                                                        </span>
                                                    )}
                                                    <span className="text-xs text-slate-400">
                                                        {formatDate(l.matched_at)}
                                                        {l.matcher && ` · ${l.matcher}`}
                                                    </span>
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            setExpandedLettering(
                                                                expandedLettering === l.id ? null : l.id
                                                            )
                                                        }
                                                        className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
                                                    >
                                                        {expandedLettering === l.id ? 'Masquer' : 'Détail'}
                                                    </button>
                                                    <button
                                                        type="button"
                                                        onClick={() => unmatch(l.id, l.code)}
                                                        className="rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-medium text-rose-700 hover:bg-rose-50"
                                                    >
                                                        Délettrer
                                                    </button>
                                                </div>
                                            </div>
                                            {expandedLettering === l.id && (
                                                <div className="mt-3 overflow-x-auto rounded-xl border border-slate-200">
                                                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                                                        <thead className="bg-slate-50 text-xs uppercase text-slate-500">
                                                            <tr>
                                                                <th className="px-3 py-2 text-left">Date</th>
                                                                <th className="px-3 py-2 text-left">Journal</th>
                                                                <th className="px-3 py-2 text-left">Pièce</th>
                                                                <th className="px-3 py-2 text-right">Débit</th>
                                                                <th className="px-3 py-2 text-right">Crédit</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody className="divide-y divide-slate-100">
                                                            {l.lines.map((ln) => (
                                                                <tr key={ln.id}>
                                                                    <td className="px-3 py-2">{formatDate(ln.entry_date)}</td>
                                                                    <td className="px-3 py-2">{ln.journal_code || '—'}</td>
                                                                    <td className="px-3 py-2">{ln.reference || '—'}</td>
                                                                    <td className="px-3 py-2 text-right">
                                                                        {ln.debit > 0 ? formatMoney(ln.debit) : '—'}
                                                                    </td>
                                                                    <td className="px-3 py-2 text-right">
                                                                        {ln.credit > 0 ? formatMoney(ln.credit) : '—'}
                                                                    </td>
                                                                </tr>
                                                            ))}
                                                        </tbody>
                                                    </table>
                                                </div>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}
                    </>
                ) : (
                    <div className="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-500">
                        Sélectionnez un compte de tiers pour afficher les lignes à lettrer.
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}

function LinesPanel({ title, side, lines, selected, onToggle }) {
    const selectedCount = lines.filter((l) => selected.has(l.id)).length;

    return (
        <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div className="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                <h3 className="text-sm font-semibold text-slate-900">{title}</h3>
                <span className="text-xs text-slate-500">
                    {selectedCount} / {lines.length} sélectionnées
                </span>
            </div>
            <div className="max-h-[480px] overflow-y-auto">
                {lines.length === 0 ? (
                    <div className="px-4 py-10 text-center text-sm text-slate-400">
                        Aucune ligne ouverte.
                    </div>
                ) : (
                    <table className="min-w-full text-sm">
                        <thead className="sticky top-0 bg-slate-50 text-xs uppercase text-slate-500">
                            <tr>
                                <th className="w-8 px-2 py-2"></th>
                                <th className="px-2 py-2 text-left">Date</th>
                                <th className="px-2 py-2 text-left">Pièce</th>
                                <th className="px-2 py-2 text-left">Libellé</th>
                                <th className="px-2 py-2 text-right">Montant</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {lines.map((l) => (
                                <tr
                                    key={l.id}
                                    className={`cursor-pointer hover:bg-slate-50 ${
                                        selected.has(l.id) ? 'bg-indigo-50' : ''
                                    }`}
                                    onClick={() => onToggle(l.id)}
                                >
                                    <td className="px-2 py-2 text-center">
                                        <input
                                            type="checkbox"
                                            checked={selected.has(l.id)}
                                            onChange={() => onToggle(l.id)}
                                            onClick={(e) => e.stopPropagation()}
                                            className="h-4 w-4 rounded border-slate-300 text-indigo-600"
                                        />
                                    </td>
                                    <td className="px-2 py-2 whitespace-nowrap text-xs text-slate-700">
                                        {formatDate(l.entry_date)}
                                    </td>
                                    <td className="px-2 py-2 text-xs text-slate-700">
                                        {l.reference || '—'}
                                    </td>
                                    <td className="px-2 py-2 text-xs text-slate-700">
                                        {l.description || '—'}
                                    </td>
                                    <td className="px-2 py-2 text-right text-xs font-medium text-slate-900">
                                        {formatMoney(side === 'debit' ? l.debit : l.credit)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>
        </div>
    );
}
