import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import {
    ArrowDownLeft,
    ArrowUpRight,
    CheckCircle2,
    CircleX,
    PencilLine,
    SearchCheck,
    X,
} from 'lucide-react';

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import AsyncCombobox from '@/Components/UI/AsyncCombobox';
import CursorPager from '@/Components/UI/CursorPager';

const formatCurrency = (value) =>
    new Intl.NumberFormat('fr-DZ', {
        style: 'currency',
        currency: 'DZD',
    }).format(Number(value ?? 0));

const formatDate = (value) => {
    if (!value) return '—';

    return new Intl.DateTimeFormat('fr-DZ', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).format(new Date(value));
};

const normalizeAmount = (value) => Math.abs(Number(value ?? 0));

const diffDays = (a, b) => {
    if (!a || !b) return 9999;
    const first = new Date(a);
    const second = new Date(b);
    const ms = Math.abs(first - second);
    return Math.floor(ms / (1000 * 60 * 60 * 24));
};

const computeScore = (tx, entry) => {
    const txAmount = normalizeAmount(tx.amount);
    const entryAmount = normalizeAmount(entry.amount);

    const amountDiff = Math.abs(txAmount - entryAmount);
    const percentDiff = txAmount > 0 ? amountDiff / txAmount : 1;
    const days = diffDays(tx.transaction_date, entry.entry_date);

    let score = 0;

    if (amountDiff <= 0.01) {
        score += 0.6;
    } else if (amountDiff <= 1) {
        score += 0.3;
    }

    if (days === 0) {
        score += 0.25;
    } else if (days <= 3) {
        score += 0.15;
    } else if (days <= 7) {
        score += 0.05;
    }

    const label = String(tx.label ?? '').toLowerCase();
    const reference = String(entry.reference ?? '').toLowerCase();

    if (reference && label.includes(reference)) {
        score += 0.15;
    }

    return Math.max(0, Math.min(1, score));
};

const getAmountMatchTone = (txAmount, entryAmount) => {
    const amount = normalizeAmount(txAmount);
    const candidate = normalizeAmount(entryAmount);
    const diff = Math.abs(amount - candidate);

    if (diff <= 0.01) {
        return 'green';
    }

    if (amount > 0 && diff / amount <= 0.05) {
        return 'amber';
    }

    return 'gray';
};

const buildSuggestedDescription = (transaction) => {
    const raw = String(transaction?.label ?? '').trim();
    const counterparty = raw ? raw.slice(0, 80) : 'banque';
    return `Règlement banque ${counterparty}`.trim();
};

function MatchConfirmBar({ transaction, entry, onConfirm, processing }) {
    if (!transaction || !entry) return null;

    const tone = getAmountMatchTone(transaction.amount, entry.amount);

    return (
        <div className="sticky bottom-0 rounded-xl border border-indigo-200 bg-indigo-50 p-4 shadow-sm">
            <div className="grid gap-4 lg:grid-cols-3">
                <div>
                    <p className="text-xs font-semibold uppercase tracking-wide text-indigo-700">
                        Transaction bancaire
                    </p>
                    <p className="mt-1 text-sm text-gray-900">{transaction.label}</p>
                    <p className="text-sm text-gray-600">
                        {formatDate(transaction.transaction_date)} · {formatCurrency(transaction.amount)}
                    </p>
                </div>

                <div>
                    <p className="text-xs font-semibold uppercase tracking-wide text-indigo-700">
                        Écriture sélectionnée
                    </p>
                    <p className="mt-1 text-sm text-gray-900">
                        {entry.reference || 'Sans référence'}
                    </p>
                    <p className="text-sm text-gray-600">
                        {formatDate(entry.entry_date)} · {formatCurrency(entry.amount)}
                    </p>
                </div>

                <div className="flex items-center justify-between gap-4 lg:justify-end">
                    <div
                        className={[
                            'rounded-lg px-3 py-2 text-sm font-medium',
                            tone === 'green'
                                ? 'bg-emerald-100 text-emerald-700'
                                : tone === 'amber'
                                ? 'bg-amber-100 text-amber-700'
                                : 'bg-gray-100 text-gray-700',
                        ].join(' ')}
                    >
                        {tone === 'green' && 'Montant exact'}
                        {tone === 'amber' && 'Montant proche'}
                        {tone === 'gray' && 'À vérifier'}
                    </div>

                    <button
                        type="button"
                        onClick={onConfirm}
                        disabled={processing}
                        className="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                    >
                        Confirmer le rapprochement
                    </button>
                </div>
            </div>
        </div>
    );
}

function ManualPostModal({
    open,
    onClose,
    transaction,
    processing,
}) {
    const [accountId, setAccountId] = useState('');
    const [accountPrefill, setAccountPrefill] = useState(null);
    const [description, setDescription] = useState('');

    useEffect(() => {
        if (!open || !transaction) return;
        setDescription(buildSuggestedDescription(transaction));
    }, [open, transaction?.id]);

    if (!open || !transaction) return null;

    const submit = (e) => {
        e.preventDefault();

        router.post(
            '/bank/reconcile/manual-post',
            {
                bank_transaction_id: transaction.id,
                account_id: accountId,
                description,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setAccountId('');
                    setAccountPrefill(null);
                    setDescription('');
                    onClose();
                },
            }
        );
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div className="w-full max-w-lg rounded-2xl bg-white shadow-xl">
                <div className="flex items-center justify-between border-b border-gray-200 px-5 py-4">
                    <div>
                        <h3 className="text-base font-semibold text-gray-900">
                            Saisie manuelle
                        </h3>
                        <p className="text-sm text-gray-500">
                            Créer une écriture à partir de la transaction sélectionnée
                        </p>
                    </div>

                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-md p-2 text-gray-500 hover:bg-gray-100"
                    >
                        <X className="h-5 w-5" />
                    </button>
                </div>

                <form onSubmit={submit} className="space-y-4 px-5 py-5">
                    <div className="rounded-xl bg-gray-50 p-4">
                        <p className="text-sm font-medium text-gray-900">{transaction.label}</p>
                        <p className="mt-1 text-sm text-gray-600">
                            {formatDate(transaction.transaction_date)} · {formatCurrency(transaction.amount)}
                        </p>
                    </div>

                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">
                            Compte bancaire
                        </label>
                        <AsyncCombobox
                            endpoint="/suggest/accounts"
                            value={accountId}
                            prefill={accountPrefill}
                            onChange={(id, option) => {
                                setAccountId(id || '');
                                setAccountPrefill(option ?? null);
                            }}
                            getLabel={(a) => `${a.code} — ${a.label}`}
                            placeholder="Code ou libellé du compte…"
                            minChars={1}
                            required
                            ariaLabel="Compte bancaire"
                        />
                    </div>

                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">
                            Description
                        </label>
                        <textarea
                            rows={4}
                            value={description}
                            onChange={(e) => setDescription(e.target.value)}
                            className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                            placeholder="Saisir le libellé comptable"
                        />
                    </div>

                    <div className="flex justify-end gap-3">
                        <button
                            type="button"
                            onClick={onClose}
                            className="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            Annuler
                        </button>

                        <button
                            type="submit"
                            disabled={processing}
                            className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                        >
                            Valider la saisie
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

export default function Reconcile({ bankTransactions, openItems }) {
    const [selectedTransactionId, setSelectedTransactionId] = useState(
        bankTransactions?.data?.[0]?.id ?? null
    );
    const [selectedEntryId, setSelectedEntryId] = useState(null);
    const [manualModalOpen, setManualModalOpen] = useState(false);
    const [processing, setProcessing] = useState(false);

    const selectedTransaction =
        bankTransactions?.data?.find((tx) => tx.id === selectedTransactionId) ?? null;

    const openItemsData = useMemo(() => {
        const raw = openItems?.data ?? openItems ?? [];
        return raw.map((item) => ({
            ...item,
            // For a balanced entry, total_debit == total_credit, so the debit
            // side is the absolute settlement amount we match against the
            // bank transaction.
            amount: Number(item.totals?.debit ?? item.amount ?? 0),
        }));
    }, [openItems]);

    const rankedOpenItems = useMemo(() => {
        if (!selectedTransaction) {
            return openItemsData;
        }

        return [...openItemsData]
            .map((item) => ({
                ...item,
                match_score: computeScore(selectedTransaction, item),
                amount_tone: getAmountMatchTone(selectedTransaction.amount, item.amount),
            }))
            .sort((a, b) => b.match_score - a.match_score);
    }, [selectedTransaction, openItemsData]);

    const selectedEntry =
        rankedOpenItems.find((item) => item.id === selectedEntryId) ?? null;

    const submitMatch = () => {
        if (!selectedTransaction || !selectedEntry) return;

        setProcessing(true);

        router.post(
            '/bank/reconcile/match',
            {
                bank_transaction_id: selectedTransaction.id,
                journal_entry_id: selectedEntry.id,
            },
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
            }
        );
    };

    const excludeTransaction = (transactionId) => {
        setProcessing(true);

        router.post(
            '/bank/reconcile/exclude',
            {
                bank_transaction_id: transactionId,
            },
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
            }
        );
    };

    return (
        <AuthenticatedLayout header="Rapprochement bancaire">
            <Head title="Rapprochement bancaire" />

            <div className="space-y-6">
                <div>
                    <h2 className="text-xl font-semibold text-gray-900">
                        Workspace de rapprochement
                    </h2>
                    <p className="text-sm text-gray-500">
                        Comparez les transactions bancaires avec les écritures en attente sans rapprochement automatique.
                    </p>
                </div>

                <div className="grid gap-6 xl:grid-cols-2">
                    <div className="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                        <div className="border-b border-gray-200 px-5 py-4">
                            <h3 className="text-base font-semibold text-gray-900">
                                Transactions bancaires non rapprochées
                            </h3>
                        </div>

                        <div className="max-h-[70vh] overflow-y-auto">
                            {bankTransactions?.data?.length > 0 ? (
                                bankTransactions.data.map((tx) => {
                                    const selected = tx.id === selectedTransactionId;

                                    return (
                                        <div
                                            key={tx.id}
                                            className={[
                                                'border-b border-gray-100 p-4',
                                                selected ? 'bg-indigo-50' : 'bg-white hover:bg-gray-50',
                                            ].join(' ')}
                                        >
                                            <button
                                                type="button"
                                                onClick={() => {
                                                    setSelectedTransactionId(tx.id);
                                                    setSelectedEntryId(null);
                                                }}
                                                className="w-full text-left"
                                            >
                                                <div className="flex items-start justify-between gap-4">
                                                    <div className="min-w-0 flex-1">
                                                        <div className="flex items-center gap-2">
                                                            {tx.direction === 'credit' ? (
                                                                <ArrowDownLeft className="h-4 w-4 text-emerald-600" />
                                                            ) : (
                                                                <ArrowUpRight className="h-4 w-4 text-red-600" />
                                                            )}
                                                            <p className="truncate text-sm font-medium text-gray-900">
                                                                {tx.label}
                                                            </p>
                                                        </div>

                                                        <p className="mt-1 text-sm text-gray-500">
                                                            {formatDate(tx.transaction_date)}
                                                        </p>
                                                    </div>

                                                    <div className="text-right">
                                                        <p className="text-sm font-semibold text-gray-900">
                                                            {formatCurrency(tx.amount)}
                                                        </p>
                                                        <p className="text-xs text-gray-500">
                                                            {tx.direction === 'credit' ? 'Entrée' : 'Sortie'}
                                                        </p>
                                                    </div>
                                                </div>
                                            </button>

                                            <div className="mt-3 flex flex-wrap gap-2">
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        setSelectedTransactionId(tx.id);
                                                        setManualModalOpen(true);
                                                    }}
                                                    className="inline-flex items-center gap-2 rounded-md border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50"
                                                >
                                                    <PencilLine className="h-4 w-4" />
                                                    Saisie manuelle
                                                </button>

                                                <button
                                                    type="button"
                                                    onClick={() => excludeTransaction(tx.id)}
                                                    className="inline-flex items-center gap-2 rounded-md border border-red-300 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50"
                                                >
                                                    <CircleX className="h-4 w-4" />
                                                    Exclure
                                                </button>
                                            </div>
                                        </div>
                                    );
                                })
                            ) : (
                                <div className="p-8 text-center text-sm text-gray-500">
                                    Aucune transaction non rapprochée
                                </div>
                            )}
                        </div>

                        <CursorPager
                            paginator={bankTransactions}
                            only={['bankTransactions']}
                        />
                    </div>

                    <div className="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                        <div className="border-b border-gray-200 px-5 py-4">
                            <h3 className="text-base font-semibold text-gray-900">
                                Écritures / Factures en attente
                            </h3>
                        </div>

                        <div className="max-h-[70vh] overflow-y-auto">
                            {rankedOpenItems?.length > 0 ? (
                                rankedOpenItems.map((item) => {
                                    const selected = item.id === selectedEntryId;

                                    return (
                                        <button
                                            type="button"
                                            key={item.id}
                                            onClick={() => {
                                                if (!selectedTransaction) return;
                                                setSelectedEntryId(item.id);
                                            }}
                                            className={[
                                                'w-full border-b border-gray-100 p-4 text-left transition',
                                                selected ? 'bg-indigo-50' : 'bg-white hover:bg-gray-50',
                                            ].join(' ')}
                                        >
                                            <div className="flex items-start justify-between gap-4">
                                                <div className="min-w-0 flex-1">
                                                    <div className="flex items-center gap-2">
                                                        <SearchCheck className="h-4 w-4 text-gray-500" />
                                                        <p className="truncate text-sm font-medium text-gray-900">
                                                            {item.reference || 'Sans référence'}
                                                        </p>
                                                    </div>

                                                    <p className="mt-1 text-sm text-gray-600">
                                                        {item.description || 'Sans description'}
                                                    </p>

                                                    <p className="mt-1 text-xs text-gray-500">
                                                        {formatDate(item.entry_date)}
                                                    </p>
                                                </div>

                                                <div className="text-right">
                                                    <div
                                                        className={[
                                                            'rounded-md px-2.5 py-1 text-sm font-semibold',
                                                            item.amount_tone === 'green'
                                                                ? 'bg-emerald-100 text-emerald-700'
                                                                : item.amount_tone === 'amber'
                                                                ? 'bg-amber-100 text-amber-700'
                                                                : 'bg-gray-100 text-gray-700',
                                                        ].join(' ')}
                                                    >
                                                        {formatCurrency(item.amount)}
                                                    </div>

                                                    {selectedTransaction && (
                                                        <p className="mt-2 text-xs text-gray-500">
                                                            Score: {Math.round((item.match_score ?? 0) * 100)}%
                                                        </p>
                                                    )}
                                                </div>
                                            </div>
                                        </button>
                                    );
                                })
                            ) : (
                                <div className="p-8 text-center text-sm text-gray-500">
                                    Aucune écriture ouverte à rapprocher
                                </div>
                            )}
                        </div>

                        <CursorPager
                            paginator={openItems}
                            only={['openItems']}
                        />
                    </div>
                </div>

                <MatchConfirmBar
                    transaction={selectedTransaction}
                    entry={selectedEntry}
                    onConfirm={submitMatch}
                    processing={processing}
                />

                <ManualPostModal
                    open={manualModalOpen}
                    onClose={() => setManualModalOpen(false)}
                    transaction={selectedTransaction}
                    processing={processing}
                />
            </div>
        </AuthenticatedLayout>
    );
}