import { Head, Link, router, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import {
    AlertCircle,
    ArrowLeft,
    BookOpenCheck,
    CheckCircle2,
    Download,
    FileText,
} from 'lucide-react';
import { useNotification } from '@/Context/NotificationContext';

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

function statusBadge(status) {
    const map = {
        draft: 'bg-slate-100 text-slate-700',
        confirmed: 'bg-blue-100 text-blue-700',
        paid: 'bg-emerald-100 text-emerald-700',
        cancelled: 'bg-rose-100 text-rose-700',
    };
    const labels = {
        draft: 'Brouillon',
        confirmed: 'Confirmée',
        paid: 'Payée',
        cancelled: 'Annulée',
    };
    return (
        <span
            className={`inline-flex rounded-full px-2.5 py-1 text-xs font-medium ${map[status] || 'bg-slate-100 text-slate-700'}`}
        >
            {labels[status] || status || '—'}
        </span>
    );
}

export default function Show({ expense }) {
    const { errors } = usePage().props;
    const { confirm } = useNotification();

    if (!expense) {
        return (
            <AuthenticatedLayout header="Dépense">
                <Head title="Dépense introuvable" />
                <div className="rounded-xl border border-rose-200 bg-rose-50 p-6 text-rose-800">
                    Dépense introuvable.
                </div>
            </AuthenticatedLayout>
        );
    }

    const isDraft = expense.status === 'draft';

    const confirmExpense = async () => {
        const ok = await confirm({
            title: 'Confirmer la dépense',
            message: 'Confirmer cette dépense et générer l’écriture comptable ?',
            confirmLabel: 'Confirmer',
        });
        if (!ok) return;
        router.post(`/expenses/${expense.id}/confirm`, {}, { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout header={`Dépense ${expense.reference ?? ''}`}>
            <Head title="Dépense" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center gap-3">
                    <Link
                        href="/expenses"
                        className="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                    >
                        <ArrowLeft className="h-4 w-4" />
                        Retour
                    </Link>

                    <div className="ml-auto flex flex-wrap items-center gap-2">
                        {expense.document?.file_path && (
                            <a
                                href={`/documents/${expense.document.id}/download`}
                                target="_blank"
                                rel="noreferrer"
                                className="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                            >
                                <Download className="h-4 w-4" />
                                Pièce jointe
                            </a>
                        )}

                        {isDraft && (
                            <button
                                type="button"
                                onClick={confirmExpense}
                                className="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-3.5 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                            >
                                <CheckCircle2 className="h-4 w-4" />
                                Confirmer
                            </button>
                        )}
                    </div>
                </div>

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
                                    Dépense
                                </p>
                                <h2 className="mt-1 text-2xl font-semibold text-slate-900">
                                    {expense.reference || 'Sans référence'}
                                </h2>
                                <p className="mt-1 text-sm text-slate-600">
                                    Date : {formatDate(expense.expense_date)}
                                    {expense.due_date ? ` · échéance ${formatDate(expense.due_date)}` : ''}
                                </p>
                            </div>
                            {statusBadge(expense.status)}
                        </div>

                        <div className="mt-4 grid gap-4 sm:grid-cols-2">
                            <div className="rounded-lg bg-slate-50 p-4">
                                <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    Fournisseur
                                </p>
                                <p className="mt-1 text-sm font-medium text-slate-900">
                                    {expense.contact?.display_name ?? 'Non renseigné'}
                                </p>
                                {expense.contact?.nif && (
                                    <p className="mt-0.5 text-xs text-slate-500">
                                        NIF : {expense.contact.nif}
                                    </p>
                                )}
                            </div>
                            <div className="rounded-lg bg-slate-50 p-4">
                                <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    Compte de charge
                                </p>
                                <p className="mt-1 text-sm font-medium text-slate-900">
                                    {expense.account
                                        ? `${expense.account.code} — ${expense.account.label}`
                                        : '—'}
                                </p>
                            </div>
                        </div>

                        {expense.description && (
                            <div className="mt-4 rounded-lg border border-slate-200 p-4">
                                <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    Description
                                </p>
                                <p className="mt-1 whitespace-pre-wrap text-sm text-slate-700">
                                    {expense.description}
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
                                    {formatCurrency(expense.total_ht)}
                                </dd>
                            </div>
                            <div className="flex items-center justify-between">
                                <dt className="text-slate-600">TVA</dt>
                                <dd className="font-medium text-slate-900">
                                    {formatCurrency(expense.total_vat)}
                                </dd>
                            </div>
                            <div className="mt-2 flex items-center justify-between border-t border-slate-200 pt-2 text-base">
                                <dt className="font-semibold text-slate-900">Total TTC</dt>
                                <dd className="font-bold text-slate-900">
                                    {formatCurrency(expense.total_ttc)}
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>

                {expense.journal_entry && (
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
                                    {expense.journal_entry.lines?.map((line) => (
                                        <tr key={line.id}>
                                            <td className="px-4 py-2 font-mono text-xs text-slate-700">
                                                {line.account?.code} — {line.account?.label}
                                            </td>
                                            <td className="px-4 py-2 text-slate-700">
                                                {line.description ?? '—'}
                                            </td>
                                            <td className="px-4 py-2 text-right text-slate-900">
                                                {Number(line.debit) > 0
                                                    ? formatCurrency(line.debit)
                                                    : ''}
                                            </td>
                                            <td className="px-4 py-2 text-right text-slate-900">
                                                {Number(line.credit) > 0
                                                    ? formatCurrency(line.credit)
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
