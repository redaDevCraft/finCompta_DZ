import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import {
    ArrowLeft,
    Download,
    FileText,
    Image as ImageIcon,
    Loader2,
    RefreshCw,
    Receipt,
    AlertTriangle,
    CheckCircle2,
    Clock,
} from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

const STATUS_STYLES = {
    pending: { label: 'En attente', icon: Clock, cls: 'bg-amber-50 text-amber-700' },
    processing: { label: 'Traitement', icon: Loader2, cls: 'bg-indigo-50 text-indigo-700' },
    done: { label: 'Terminé', icon: CheckCircle2, cls: 'bg-emerald-50 text-emerald-700' },
    failed: { label: 'Échec', icon: AlertTriangle, cls: 'bg-rose-50 text-rose-700' },
};

function formatBytes(bytes) {
    if (!bytes) return '—';
    const kb = bytes / 1024;
    if (kb < 1024) return `${kb.toFixed(1)} Ko`;
    return `${(kb / 1024).toFixed(2)} Mo`;
}

function formatDate(iso, withTime = false) {
    if (!iso) return '—';
    const opts = withTime
        ? { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }
        : { day: '2-digit', month: '2-digit', year: 'numeric' };
    return new Date(iso).toLocaleString('fr-FR', opts);
}

function formatMoney(v, currency = 'DZD') {
    if (v === null || v === undefined) return '—';
    return `${Number(v).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${currency}`;
}

function StatusBadge({ status }) {
    const cfg = STATUS_STYLES[status] ?? { label: status, icon: FileText, cls: 'bg-slate-100 text-slate-700' };
    const Icon = cfg.icon;
    return (
        <span className={`inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-medium ${cfg.cls}`}>
            <Icon className={`h-3.5 w-3.5 ${status === 'processing' ? 'animate-spin' : ''}`} />
            {cfg.label}
        </span>
    );
}

function HintCard({ hints }) {
    if (!hints || Object.keys(hints).length === 0) {
        return (
            <div className="rounded-lg border border-dashed border-slate-300 p-4 text-sm text-slate-500">
                Aucune donnée extraite.
            </div>
        );
    }

    const entries = [
        ['vendor_name', 'Fournisseur'],
        ['vendor_nif', 'NIF'],
        ['vendor_nis', 'NIS'],
        ['vendor_rc', 'RC'],
        ['reference', 'N° facture'],
        ['document_date', 'Date'],
        ['total_ht', 'Total HT'],
        ['total_vat', 'TVA'],
        ['total_ttc', 'Total TTC'],
        ['tva_rate', 'Taux TVA'],
        ['currency', 'Devise'],
        ['payment_method', 'Règlement'],
        ['account_code_hint', 'Compte suggéré'],
    ];

    return (
        <dl className="grid gap-x-6 gap-y-3 sm:grid-cols-2">
            {entries.map(([key, label]) => {
                const val = hints[key];
                if (val === null || val === undefined || val === '') return null;
                return (
                    <div key={key}>
                        <dt className="text-xs font-medium uppercase tracking-wide text-slate-500">{label}</dt>
                        <dd className="mt-0.5 text-sm text-slate-900">{String(val)}</dd>
                    </div>
                );
            })}
        </dl>
    );
}

export default function Show({ document, linkedExpenses }) {
    const [retrying, setRetrying] = useState(false);
    const downloadUrl = `/documents/${document.id}/download`;

    const retry = () => {
        if (retrying) return;
        setRetrying(true);
        const token = typeof window !== 'undefined'
            ? window.document.querySelector('meta[name="csrf-token"]')?.content ?? ''
            : '';
        fetch(`/documents/${document.id}/retry`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': token,
                Accept: 'application/json',
            },
        })
            .then(() => router.reload({ only: ['document'] }))
            .finally(() => setRetrying(false));
    };

    return (
        <AuthenticatedLayout>
            <Head title={document.file_name} />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex items-center gap-3">
                        <Link
                            href="/documents"
                            className="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50"
                        >
                            <ArrowLeft className="h-4 w-4" />
                            Documents
                        </Link>
                        <h1 className="text-2xl font-semibold text-slate-900">{document.file_name}</h1>
                        <StatusBadge status={document.ocr_status} />
                    </div>
                    <div className="flex items-center gap-2">
                        <a
                            href={downloadUrl}
                            className="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50"
                        >
                            <Download className="h-4 w-4" />
                            Télécharger
                        </a>
                        {(document.ocr_status === 'failed' || document.ocr_status === 'done') && (
                            <button
                                type="button"
                                onClick={retry}
                                disabled={retrying}
                                className="inline-flex items-center gap-2 rounded-lg border border-indigo-300 bg-indigo-50 px-3 py-1.5 text-sm text-indigo-700 hover:bg-indigo-100 disabled:opacity-60"
                            >
                                <RefreshCw className={`h-4 w-4 ${retrying ? 'animate-spin' : ''}`} />
                                Relancer OCR
                            </button>
                        )}
                        {document.ocr_status === 'done' && (
                            <Link
                                href={`/documents/${document.id}/use-in-expense`}
                                className="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-700"
                            >
                                <Receipt className="h-4 w-4" />
                                Utiliser en dépense
                            </Link>
                        )}
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-5">
                    {/* Left: metadata + hints */}
                    <div className="space-y-6 lg:col-span-2">
                        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <h2 className="text-sm font-semibold uppercase tracking-wide text-slate-500">Informations</h2>
                            <dl className="mt-4 space-y-3 text-sm">
                                <div className="flex justify-between">
                                    <dt className="text-slate-500">Type</dt>
                                    <dd className="text-slate-900">{document.document_type}</dd>
                                </div>
                                <div className="flex justify-between">
                                    <dt className="text-slate-500">MIME</dt>
                                    <dd className="font-mono text-xs text-slate-700">{document.mime_type}</dd>
                                </div>
                                <div className="flex justify-between">
                                    <dt className="text-slate-500">Taille</dt>
                                    <dd className="text-slate-900">{formatBytes(document.file_size_bytes)}</dd>
                                </div>
                                <div className="flex justify-between">
                                    <dt className="text-slate-500">Source</dt>
                                    <dd className="text-slate-900">{document.source}</dd>
                                </div>
                                <div className="flex justify-between">
                                    <dt className="text-slate-500">Ajouté le</dt>
                                    <dd className="text-slate-900">{formatDate(document.created_at, true)}</dd>
                                </div>
                                <div className="flex justify-between">
                                    <dt className="text-slate-500">Conservation</dt>
                                    <dd className="text-slate-900">{formatDate(document.retention_until)}</dd>
                                </div>
                            </dl>
                        </div>

                        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <h2 className="text-sm font-semibold uppercase tracking-wide text-slate-500">Données OCR extraites</h2>
                            <div className="mt-4">
                                <HintCard hints={document.ocr_parsed_hints} />
                            </div>
                        </div>

                        {document.ocr_error && (
                            <div className="rounded-2xl border border-rose-200 bg-rose-50 p-5 text-sm text-rose-800">
                                <div className="flex items-center gap-2 font-semibold">
                                    <AlertTriangle className="h-4 w-4" />
                                    Erreur OCR
                                </div>
                                <p className="mt-2 whitespace-pre-wrap break-words">{document.ocr_error}</p>
                            </div>
                        )}

                        {linkedExpenses.length > 0 && (
                            <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                                <h2 className="text-sm font-semibold uppercase tracking-wide text-slate-500">
                                    Dépenses liées ({linkedExpenses.length})
                                </h2>
                                <ul className="mt-3 divide-y divide-slate-100">
                                    {linkedExpenses.map((exp) => (
                                        <li key={exp.id} className="py-3">
                                            <Link
                                                href={`/expenses/${exp.id}`}
                                                className="block hover:bg-slate-50"
                                            >
                                                <div className="flex items-center justify-between">
                                                    <div>
                                                        <div className="font-medium text-slate-900">
                                                            {exp.reference || 'Sans référence'}
                                                        </div>
                                                        <div className="text-xs text-slate-500">
                                                            {formatDate(exp.expense_date)} • {exp.contact_name ?? '—'}
                                                        </div>
                                                    </div>
                                                    <div className="text-right">
                                                        <div className="font-semibold text-slate-900">
                                                            {formatMoney(exp.total_ttc, exp.currency)}
                                                        </div>
                                                        <span className="text-xs text-slate-500">{exp.status}</span>
                                                    </div>
                                                </div>
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}
                    </div>

                    {/* Right: preview + raw text */}
                    <div className="space-y-6 lg:col-span-3">
                        <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                            <div className="flex items-center justify-between border-b border-slate-200 px-5 py-3">
                                <h2 className="text-sm font-semibold uppercase tracking-wide text-slate-500">
                                    Aperçu du document
                                </h2>
                                {document.is_image ? <ImageIcon className="h-4 w-4 text-slate-400" /> : <FileText className="h-4 w-4 text-slate-400" />}
                            </div>
                            <div className="bg-slate-50 p-4">
                                {document.is_image && (
                                    <img
                                        src={downloadUrl}
                                        alt={document.file_name}
                                        className="mx-auto max-h-[640px] w-auto rounded-lg border border-slate-200 bg-white shadow-sm"
                                    />
                                )}
                                {document.is_pdf && (
                                    <iframe
                                        src={downloadUrl}
                                        title={document.file_name}
                                        className="h-[640px] w-full rounded-lg border border-slate-200 bg-white"
                                    />
                                )}
                                {!document.is_image && !document.is_pdf && (
                                    <div className="py-12 text-center text-sm text-slate-500">
                                        Aperçu non disponible pour ce type de fichier.
                                    </div>
                                )}
                            </div>
                        </div>

                        {document.ocr_raw_text && (
                            <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                                <div className="border-b border-slate-200 px-5 py-3">
                                    <h2 className="text-sm font-semibold uppercase tracking-wide text-slate-500">
                                        Texte brut OCR
                                    </h2>
                                </div>
                                <pre className="max-h-[420px] overflow-auto whitespace-pre-wrap break-words p-5 font-mono text-xs text-slate-700">
                                    {document.ocr_raw_text}
                                </pre>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
