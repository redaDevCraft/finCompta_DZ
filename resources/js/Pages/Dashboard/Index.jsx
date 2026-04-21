import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeftRight,
    BadgeDollarSign,
    BookOpenCheck,
    FileText,
    Landmark,
    Receipt,
    Scale,
    TrendingUp,
    Users,
    Wallet,
} from 'lucide-react';

const formatCurrency = (value) =>
    new Intl.NumberFormat('fr-DZ', {
        style: 'currency',
        currency: 'DZD',
        maximumFractionDigits: 0,
    }).format(Number(value ?? 0));

const formatDate = (value) => {
    if (!value) return '—';
    return new Intl.DateTimeFormat('fr-DZ', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).format(new Date(value));
};

function KpiCard({ label, value, icon: Icon, tone = 'indigo', hint }) {
    const tones = {
        indigo: 'bg-indigo-50 text-indigo-700',
        emerald: 'bg-emerald-50 text-emerald-700',
        amber: 'bg-amber-50 text-amber-700',
        rose: 'bg-rose-50 text-rose-700',
        sky: 'bg-sky-50 text-sky-700',
        slate: 'bg-slate-100 text-slate-700',
    };
    return (
        <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                        {label}
                    </p>
                    <p className="mt-2 text-2xl font-bold text-slate-900">
                        {formatCurrency(value)}
                    </p>
                    {hint ? (
                        <p className="mt-1 text-xs text-slate-500">{hint}</p>
                    ) : null}
                </div>
                <div className={`rounded-xl p-3 ${tones[tone] ?? tones.indigo}`}>
                    {Icon ? <Icon className="h-5 w-5" /> : null}
                </div>
            </div>
        </div>
    );
}

function SignalPill({ count, label, href, tone = 'slate' }) {
    const tones = {
        slate: 'border-slate-200 bg-slate-50 text-slate-700',
        amber: 'border-amber-200 bg-amber-50 text-amber-800',
        rose: 'border-rose-200 bg-rose-50 text-rose-800',
        sky: 'border-sky-200 bg-sky-50 text-sky-800',
        indigo: 'border-indigo-200 bg-indigo-50 text-indigo-800',
    };
    const inner = (
        <div className="flex items-center gap-3">
            <span className={`inline-flex h-8 min-w-8 items-center justify-center rounded-lg px-2 text-sm font-semibold ${tones[tone]}`}>
                {count}
            </span>
            <span className="text-sm text-slate-700">{label}</span>
        </div>
    );
    if (!href) return <div className="rounded-xl border border-slate-200 bg-white p-3">{inner}</div>;
    return (
        <Link
            href={href}
            className="block rounded-xl border border-slate-200 bg-white p-3 transition hover:border-indigo-300 hover:bg-indigo-50/40"
        >
            {inner}
        </Link>
    );
}

function RevenueChart({ series }) {
    if (!series || series.length === 0) {
        return (
            <div className="flex h-64 items-center justify-center rounded-xl border border-dashed border-slate-200 bg-slate-50 text-sm text-slate-400">
                Pas encore de données sur les 12 derniers mois
            </div>
        );
    }

    const max = Math.max(
        1,
        ...series.map((s) => Math.max(Number(s.revenue || 0), Number(s.expense || 0)))
    );

    return (
        <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="mb-3 flex items-center justify-between">
                <div>
                    <h3 className="text-sm font-semibold text-slate-900">
                        Produits vs charges — 12 derniers mois
                    </h3>
                    <p className="text-xs text-slate-500">
                        Basé sur les classes 6 et 7 du grand livre (écritures validées)
                    </p>
                </div>
                <div className="flex items-center gap-4 text-xs">
                    <span className="flex items-center gap-1.5">
                        <span className="h-2.5 w-2.5 rounded-sm bg-emerald-500" /> Produits
                    </span>
                    <span className="flex items-center gap-1.5">
                        <span className="h-2.5 w-2.5 rounded-sm bg-rose-500" /> Charges
                    </span>
                </div>
            </div>

            <div className="flex h-56 items-end gap-2 overflow-x-auto pb-4">
                {series.map((s) => {
                    const rev = Math.max(0, Number(s.revenue || 0));
                    const exp = Math.max(0, Number(s.expense || 0));
                    const revHeight = (rev / max) * 100;
                    const expHeight = (exp / max) * 100;
                    return (
                        <div
                            key={s.month}
                            className="flex min-w-[44px] flex-1 flex-col items-center gap-1"
                            title={`${s.label} — Produits: ${formatCurrency(rev)} | Charges: ${formatCurrency(exp)}`}
                        >
                            <div className="flex h-full w-full items-end justify-center gap-0.5">
                                <div
                                    className="w-1/2 rounded-t bg-emerald-500/90"
                                    style={{ height: `${revHeight}%` }}
                                />
                                <div
                                    className="w-1/2 rounded-t bg-rose-500/80"
                                    style={{ height: `${expHeight}%` }}
                                />
                            </div>
                            <span className="text-[10px] uppercase text-slate-500">
                                {s.label}
                            </span>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}

function BalanceList({ title, emptyLabel, rows, tone = 'indigo' }) {
    const toneText = tone === 'rose' ? 'text-rose-700' : 'text-indigo-700';
    return (
        <div className="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div className="border-b border-slate-200 px-5 py-3">
                <h3 className="text-sm font-semibold text-slate-900">{title}</h3>
            </div>
            <div className="divide-y divide-slate-100">
                {rows && rows.length > 0 ? (
                    rows.map((r, idx) => (
                        <div
                            key={r.contact_id || idx}
                            className="flex items-center justify-between px-5 py-3"
                        >
                            <div className="flex items-center gap-3">
                                <span className="flex h-8 w-8 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-600">
                                    {idx + 1}
                                </span>
                                <span className="text-sm text-slate-800">
                                    {r.display_name}
                                </span>
                            </div>
                            <span className={`text-sm font-semibold ${toneText}`}>
                                {formatCurrency(r.balance)}
                            </span>
                        </div>
                    ))
                ) : (
                    <div className="px-5 py-8 text-center text-sm text-slate-400">
                        {emptyLabel}
                    </div>
                )}
            </div>
        </div>
    );
}

export default function Index({
    kpis = {},
    series = [],
    top_debtors = [],
    top_creditors = [],
    recent_entries = [],
    recent_invoices = [],
    signals = {},
}) {
    const { props } = usePage();
    const subscription = props.subscription ?? null;
    const showUpgradeCta = !subscription || ['trial', 'past_due', 'canceled', 'expired'].includes(subscription.status);

    return (
        <AuthenticatedLayout header="Tableau de bord">
            <Head title="Tableau de bord" />

            <div className="space-y-6">
                {showUpgradeCta && (
                    <div className="rounded-2xl border border-indigo-200 bg-gradient-to-r from-indigo-50 to-sky-50 p-5 shadow-sm">
                        <div className="flex flex-wrap items-center justify-between gap-4">
                            <div>
                                <h3 className="text-lg font-semibold text-indigo-900">
                                    Passez au plan Pro pour débloquer toutes les fonctionnalités
                                </h3>
                                <p className="mt-1 text-sm text-indigo-800">
                                    Comparez Starter, Pro et Enterprise — offre limitée, montée en
                                    charge immédiate sans interruption.
                                </p>
                            </div>
                            <Link
                                href="/pricing"
                                className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700"
                            >
                                Commencer maintenant
                            </Link>
                        </div>
                    </div>
                )}

                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 className="text-xl font-semibold text-slate-900">
                            Vue d’ensemble
                        </h2>
                        <p className="text-sm text-slate-500">
                            Indicateurs clés, tendances et actions à traiter
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Link
                            href="/ledger/entries/create"
                            className="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                        >
                            <BookOpenCheck className="h-4 w-4" />
                            Nouvelle écriture
                        </Link>
                        <Link
                            href="/invoices/create"
                            className="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-3.5 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                        >
                            <FileText className="h-4 w-4" />
                            Nouvelle facture
                        </Link>
                    </div>
                </div>

                <div className="grid grid-cols-2 gap-4 xl:grid-cols-4">
                    <KpiCard
                        label="Trésorerie"
                        value={kpis.cash_balance}
                        icon={Landmark}
                        tone="sky"
                        hint="Classe 5 — banques & caisse"
                    />
                    <KpiCard
                        label="Créances clients"
                        value={kpis.ar_balance}
                        icon={Users}
                        tone="indigo"
                        hint="Solde 411 ouvert"
                    />
                    <KpiCard
                        label="Dettes fournisseurs"
                        value={kpis.ap_balance}
                        icon={Wallet}
                        tone="amber"
                        hint="Solde 401 ouvert"
                    />
                    <KpiCard
                        label="Résultat YTD"
                        value={kpis.result_ytd}
                        icon={Scale}
                        tone={Number(kpis.result_ytd) >= 0 ? 'emerald' : 'rose'}
                        hint={`${formatCurrency(kpis.revenue_ytd)} − ${formatCurrency(kpis.expenses_ytd)}`}
                    />
                </div>

                <div className="grid grid-cols-2 gap-4 xl:grid-cols-4">
                    <KpiCard
                        label="CA du mois"
                        value={kpis.revenue_mtd}
                        icon={TrendingUp}
                        tone="emerald"
                    />
                    <KpiCard
                        label="Charges du mois"
                        value={kpis.expenses_mtd}
                        icon={Receipt}
                        tone="rose"
                    />
                    <KpiCard
                        label="CA année"
                        value={kpis.revenue_ytd}
                        icon={BadgeDollarSign}
                        tone="emerald"
                    />
                    <KpiCard
                        label="Charges année"
                        value={kpis.expenses_ytd}
                        icon={Receipt}
                        tone="amber"
                    />
                </div>

                {(signals.draft_entries > 0 ||
                    signals.unmatched_bank > 0 ||
                    signals.unlettered_lines > 0 ||
                    signals.pending_documents > 0 ||
                    signals.draft_invoices > 0 ||
                    signals.draft_expenses > 0) && (
                    <div className="rounded-xl border border-amber-200 bg-amber-50/50 p-4">
                        <div className="mb-3 flex items-center gap-2 text-sm font-semibold text-amber-900">
                            <AlertTriangle className="h-4 w-4" />
                            À traiter
                        </div>
                        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            {signals.draft_entries > 0 && (
                                <SignalPill
                                    count={signals.draft_entries}
                                    label="Écriture(s) en brouillon"
                                    href="/ledger/entries?status=draft"
                                    tone="amber"
                                />
                            )}
                            {signals.unlettered_lines > 0 && (
                                <SignalPill
                                    count={signals.unlettered_lines}
                                    label="Ligne(s) non lettrée(s)"
                                    href="/ledger/lettering"
                                    tone="indigo"
                                />
                            )}
                            {signals.unmatched_bank > 0 && (
                                <SignalPill
                                    count={signals.unmatched_bank}
                                    label="Opération(s) bancaire(s) non rapprochée(s)"
                                    href="/bank/reconcile"
                                    tone="sky"
                                />
                            )}
                            {signals.pending_documents > 0 && (
                                <SignalPill
                                    count={signals.pending_documents}
                                    label="Document(s) OCR en cours"
                                    href="/documents"
                                    tone="slate"
                                />
                            )}
                            {signals.draft_invoices > 0 && (
                                <SignalPill
                                    count={signals.draft_invoices}
                                    label="Facture(s) en brouillon"
                                    href="/invoices?status=draft"
                                    tone="amber"
                                />
                            )}
                            {signals.draft_expenses > 0 && (
                                <SignalPill
                                    count={signals.draft_expenses}
                                    label="Dépense(s) en brouillon"
                                    href="/expenses?status=draft"
                                    tone="amber"
                                />
                            )}
                        </div>
                    </div>
                )}

                <RevenueChart series={series} />

                <div className="grid gap-4 lg:grid-cols-2">
                    <BalanceList
                        title="Top 5 créances clients (ouvertes)"
                        emptyLabel="Aucune créance ouverte"
                        rows={top_debtors}
                        tone="indigo"
                    />
                    <BalanceList
                        title="Top 5 dettes fournisseurs (ouvertes)"
                        emptyLabel="Aucune dette ouverte"
                        rows={top_creditors}
                        tone="rose"
                    />
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                        <div className="flex items-center justify-between border-b border-slate-200 px-5 py-3">
                            <h3 className="text-sm font-semibold text-slate-900">
                                Dernières écritures
                            </h3>
                            <Link
                                href="/ledger/entries"
                                className="text-xs font-medium text-indigo-600 hover:text-indigo-700"
                            >
                                Voir tout →
                            </Link>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-200 text-sm">
                                <thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                                    <tr>
                                        <th className="px-4 py-2 text-left font-semibold">Date</th>
                                        <th className="px-4 py-2 text-left font-semibold">Journal</th>
                                        <th className="px-4 py-2 text-left font-semibold">Libellé</th>
                                        <th className="px-4 py-2 text-right font-semibold">Total</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {recent_entries.length > 0 ? (
                                        recent_entries.map((e) => (
                                            <tr key={e.id} className="hover:bg-slate-50">
                                                <td className="whitespace-nowrap px-4 py-2 text-slate-700">
                                                    {formatDate(e.entry_date)}
                                                </td>
                                                <td className="whitespace-nowrap px-4 py-2 font-mono text-xs text-slate-600">
                                                    {e.journal_code ?? e.journal?.code ?? '—'}
                                                </td>
                                                <td className="max-w-[260px] truncate px-4 py-2 text-slate-700">
                                                    {e.description || e.reference || '—'}
                                                </td>
                                                <td className="whitespace-nowrap px-4 py-2 text-right font-medium text-slate-900">
                                                    {formatCurrency(e.total_debit)}
                                                </td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td
                                                colSpan={4}
                                                className="px-4 py-10 text-center text-sm text-slate-400"
                                            >
                                                Aucune écriture validée pour le moment
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                        <div className="flex items-center justify-between border-b border-slate-200 px-5 py-3">
                            <h3 className="text-sm font-semibold text-slate-900">
                                Factures récentes
                            </h3>
                            <Link
                                href="/invoices"
                                className="text-xs font-medium text-indigo-600 hover:text-indigo-700"
                            >
                                Voir tout →
                            </Link>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-200 text-sm">
                                <thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                                    <tr>
                                        <th className="px-4 py-2 text-left font-semibold">N°</th>
                                        <th className="px-4 py-2 text-left font-semibold">Client</th>
                                        <th className="px-4 py-2 text-left font-semibold">Date</th>
                                        <th className="px-4 py-2 text-right font-semibold">TTC</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {recent_invoices.length > 0 ? (
                                        recent_invoices.map((inv) => (
                                            <tr key={inv.id} className="hover:bg-slate-50">
                                                <td className="px-4 py-2">
                                                    <Link
                                                        href={`/invoices/${inv.id}`}
                                                        className="font-medium text-slate-900 hover:text-indigo-600"
                                                    >
                                                        {inv.invoice_number ?? 'Brouillon'}
                                                    </Link>
                                                </td>
                                                <td className="px-4 py-2 text-slate-700">
                                                    {inv.contact?.display_name ?? '—'}
                                                </td>
                                                <td className="whitespace-nowrap px-4 py-2 text-slate-700">
                                                    {formatDate(inv.issue_date)}
                                                </td>
                                                <td className="whitespace-nowrap px-4 py-2 text-right font-medium text-slate-900">
                                                    {formatCurrency(inv.total_ttc)}
                                                </td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td
                                                colSpan={4}
                                                className="px-4 py-10 text-center text-sm text-slate-400"
                                            >
                                                Aucune facture pour le moment
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
