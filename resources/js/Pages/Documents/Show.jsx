import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import {
    ArrowLeft,
    ArrowRight,
    CheckCircle2,
    ClipboardCopy,
    Download,
    FileText,
    Image as ImageIcon,
    Loader2,
    RefreshCw,
    AlertTriangle,
    Clock,
    ChevronDown,
} from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

// ─── Constants ────────────────────────────────────────────────────────────────

const STATUS_CFG = {
    pending:    { label: 'En attente',   Icon: Clock,         cls: 'bg-amber-50  text-amber-700  ring-amber-200'  },
    processing: { label: 'OCR en cours', Icon: Loader2,       cls: 'bg-indigo-50 text-indigo-700 ring-indigo-200' },
    done:       { label: 'OCR terminé',  Icon: CheckCircle2,  cls: 'bg-emerald-50 text-emerald-700 ring-emerald-200' },
    failed:     { label: 'Échec OCR',    Icon: AlertTriangle, cls: 'bg-rose-50   text-rose-700   ring-rose-200'   },
};

const TYPE_LABELS = {
    purchase_invoice: 'Facture achat',
    sales_invoice:    'Facture vente',
    credit_note:      'Avoir',
    bank_statement:   'Relevé bancaire',
    receipt:          'Reçu / Ticket',
    other:            'Autre',
};

// Backward-compat: old parser used supplier_invoice / customer_invoice
const KIND_MAP = {
    supplier_invoice: 'purchase_invoice',
    customer_invoice: 'sales_invoice',
};

// Primary action per resolved document type
const TYPE_ACTIONS = {
    purchase_invoice: { label: 'Créer une dépense',        href: (id) => `/documents/${id}/use-in-expense`,                         cls: 'bg-indigo-600 hover:bg-indigo-700'  },
    receipt:          { label: 'Créer une dépense',        href: (id) => `/documents/${id}/use-in-expense`,                         cls: 'bg-indigo-600 hover:bg-indigo-700'  },
    sales_invoice:    { label: 'Créer facture vente',      href: (id) => `/invoices/create?from_document=${id}`,                    cls: 'bg-emerald-600 hover:bg-emerald-700' },
    credit_note:      { label: 'Créer un avoir',           href: (id) => `/invoices/create?type=credit_note&from_document=${id}`,   cls: 'bg-amber-600 hover:bg-amber-700'    },
    bank_statement:   { label: 'Importer dans Trésorerie', href: (id) => `/bank/import?from_document=${id}`,                       cls: 'bg-sky-600 hover:bg-sky-700'        },
};

const ALL_TYPE_OPTIONS = [
    { type: 'purchase_invoice', label: 'Facture achat / Dépense'  },
    { type: 'sales_invoice',    label: 'Facture vente'             },
    { type: 'credit_note',      label: 'Avoir'                     },
    { type: 'bank_statement',   label: 'Relevé bancaire'           },
    { type: 'receipt',          label: 'Reçu / Ticket de caisse'   },
];

// ─── Helpers ──────────────────────────────────────────────────────────────────

function fmtMoney(v) {
    if (v === null || v === undefined) return '—';
    return new Intl.NumberFormat('fr-DZ', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(v) + ' DZD';
}

function fmtDate(iso, withTime = false) {
    if (!iso) return '—';
    return new Date(iso).toLocaleString('fr-FR', withTime
        ? { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }
        : { day: '2-digit', month: '2-digit', year: 'numeric' });
}

function fmtBytes(bytes) {
    if (!bytes) return '—';
    const kb = bytes / 1024;
    return kb < 1024 ? `${kb.toFixed(1)} Ko` : `${(kb / 1024).toFixed(2)} Mo`;
}

// ─── StatusBadge ──────────────────────────────────────────────────────────────

function StatusBadge({ status }) {
    const cfg = STATUS_CFG[status] ?? { label: status, Icon: FileText, cls: 'bg-slate-100 text-slate-700 ring-slate-200' };
    const { Icon } = cfg;
    return (
        <span className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ring-1 ${cfg.cls}`}>
            <Icon className={`h-3.5 w-3.5 ${status === 'processing' ? 'animate-spin' : ''}`} />
            {cfg.label}
        </span>
    );
}

// ─── Smart Action Button ──────────────────────────────────────────────────────

function SmartActionButton({ document: doc }) {
    const [open, setOpen] = useState(false);
    const id = doc.id;

    // Normalize: map old parser kind names → new names
    const rawKind      = doc.ocr_parsed_hints?.document_kind;
    const normalKind   = rawKind ? (KIND_MAP[rawKind] ?? rawKind) : null;
    const resolvedType = doc.document_type ?? normalKind;
    const primary      = resolvedType ? TYPE_ACTIONS[resolvedType] : null;
    const isAiDetected = !doc.document_type && !!normalKind;

    if (doc.ocr_status !== 'done') return null;

    return (
        <div className="relative">
            {primary ? (
                <div className="flex overflow-hidden rounded-xl shadow-sm">
                    <a
                        href={primary.href(id)}
                        className={`flex flex-1 items-center justify-center gap-2 px-5 py-2.5 text-sm font-semibold text-white transition-colors ${primary.cls}`}
                    >
                        {primary.label}
                        {isAiDetected && (
                            <span className="rounded-full bg-white/20 px-1.5 py-0.5 text-xs">IA</span>
                        )}
                        <ArrowRight className="h-4 w-4" />
                    </a>
                    <button
                        type="button"
                        onClick={() => setOpen((o) => !o)}
                        className={`border-l border-white/20 px-3 text-white transition-colors ${primary.cls}`}
                        title="Autres options"
                    >
                        <ChevronDown className={`h-4 w-4 transition-transform duration-150 ${open ? 'rotate-180' : ''}`} />
                    </button>
                </div>
            ) : (
                <button
                    type="button"
                    onClick={() => setOpen((o) => !o)}
                    className="flex w-full items-center justify-center gap-2 rounded-xl bg-slate-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-slate-800"
                >
                    Créer à partir de ce document
                    <ChevronDown className={`h-4 w-4 transition-transform duration-150 ${open ? 'rotate-180' : ''}`} />
                </button>
            )}

            {open && (
                <>
                    {/* Backdrop — click anywhere outside to close */}
                    <div
                        className="fixed inset-0 z-10"
                        onClick={() => setOpen(false)}
                        onKeyDown={(e) => e.key === 'Escape' && setOpen(false)}
                    />
                    <div className="absolute right-0 top-full z-20 mt-1.5 w-full min-w-[220px] overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg">
                        {ALL_TYPE_OPTIONS.map(({ type, label }) => {
                            const action    = TYPE_ACTIONS[type];
                            const isCurrent = resolvedType === type;
                            return (
                                <a
                                    key={type}
                                    href={action.href(id)}
                                    onClick={() => setOpen(false)}
                                    className={`flex items-center justify-between px-4 py-2.5 text-sm text-slate-700 transition-colors hover:bg-slate-50 ${isCurrent ? 'bg-indigo-50 font-semibold text-indigo-700' : ''}`}
                                >
                                    {label}
                                    {isCurrent && (
                                        <span className="rounded-full bg-indigo-100 px-1.5 py-0.5 text-xs text-indigo-600">
                                            {isAiDetected ? 'IA' : '✓'}
                                        </span>
                                    )}
                                </a>
                            );
                        })}
                    </div>
                </>
            )}
        </div>
    );
}

// ─── Main Page ────────────────────────────────────────────────────────────────

export default function Show({ document, linkedExpenses = [] }) {
    const [retrying, setRetrying] = useState(false);
    const [copied,   setCopied]   = useState(false);

    const downloadUrl  = `/documents/${document.id}/download`;
    const hints        = document.ocr_parsed_hints ?? {};
    const rawKind      = hints.document_kind;
    const normalKind   = rawKind ? (KIND_MAP[rawKind] ?? rawKind) : null;
    const resolvedType = document.document_type ?? normalKind;
    const typeLabel    = resolvedType ? (TYPE_LABELS[resolvedType] ?? resolvedType) : null;
    const isAiType     = !document.document_type && !!normalKind;

    const retry = async () => {
        if (retrying) return;
        setRetrying(true);
        const token = window.document.querySelector('meta[name="csrf-token"]')?.content ?? '';
        try {
            await fetch(`/documents/${document.id}/retry`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' },
            });
            router.reload({ only: ['document'] });
        } finally {
            setRetrying(false);
        }
    };

    const copyText = async () => {
        if (!document.ocr_raw_text) return;
        try {
            await navigator.clipboard.writeText(document.ocr_raw_text);
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        } catch (_) {}
    };

    return (
        <AuthenticatedLayout>
            <Head title={document.file_name} />

            <div className="mx-auto max-w-6xl space-y-5 px-4 py-6 sm:px-6">

                {/* ── Top bar ── */}
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex min-w-0 items-center gap-3">
                        <Link
                            href="/documents"
                            className="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-50"
                        >
                            <ArrowLeft className="h-4 w-4" />
                            Documents
                        </Link>
                        <h1 className="truncate text-base font-bold text-slate-900">{document.file_name}</h1>
                        <StatusBadge status={document.ocr_status} />
                    </div>
                    <div className="flex items-center gap-2">
                        <a
                            href={downloadUrl}
                            className="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-50"
                        >
                            <Download className="h-4 w-4" />
                            Télécharger
                        </a>
                        {document.ocr_status === 'failed' && (
                            <button
                                type="button"
                                onClick={retry}
                                disabled={retrying}
                                className="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-50 disabled:opacity-50"
                            >
                                <RefreshCw className={`h-4 w-4 ${retrying ? 'animate-spin' : ''}`} />
                                Relancer OCR
                            </button>
                        )}
                    </div>
                </div>

                {/* ── Main grid ── */}
                <div className="grid gap-5 lg:grid-cols-5">

                    {/* Left panel */}
                    <div className="space-y-4 lg:col-span-2">

                        {/* Smart CTA card */}
                        {document.ocr_status === 'done' && (
                            <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                                <p className="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    Créer depuis ce document
                                </p>
                                <SmartActionButton document={document} />
                                {isAiType && (
                                    <p className="mt-2 text-xs text-slate-400">
                                        Type détecté par IA — utilisez ▾ pour choisir un autre type
                                    </p>
                                )}
                            </div>
                        )}

                        {/* Extracted data */}
                        {document.ocr_status === 'done' && Object.keys(hints).length > 0 && (
                            <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                                <p className="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    Données extraites
                                </p>
                                <div className="space-y-2.5">
                                    {hints.vendor_name && (
                                        <div className="rounded-lg bg-slate-50 px-3 py-2.5">
                                            <p className="text-xs text-slate-400">Fournisseur / Émetteur</p>
                                            <p className="mt-0.5 text-sm font-semibold text-slate-900">{hints.vendor_name}</p>
                                        </div>
                                    )}
                                    <div className="grid grid-cols-2 gap-2">
                                        {hints.reference && (
                                            <div className="rounded-lg bg-slate-50 px-3 py-2">
                                                <p className="text-xs text-slate-400">N° document</p>
                                                <p className="mt-0.5 text-sm font-medium text-slate-800">{hints.reference}</p>
                                            </div>
                                        )}
                                        {hints.document_date && (
                                            <div className="rounded-lg bg-slate-50 px-3 py-2">
                                                <p className="text-xs text-slate-400">Date</p>
                                                <p className="mt-0.5 text-sm font-medium text-slate-800">{fmtDate(hints.document_date)}</p>
                                            </div>
                                        )}
                                        {hints.total_ht != null && (
                                            <div className="rounded-lg bg-slate-50 px-3 py-2">
                                                <p className="text-xs text-slate-400">Total HT</p>
                                                <p className="mt-0.5 text-sm font-medium tabular-nums text-slate-800">{fmtMoney(hints.total_ht)}</p>
                                            </div>
                                        )}
                                        {hints.tva_rate != null && (
                                            <div className="rounded-lg bg-slate-50 px-3 py-2">
                                                <p className="text-xs text-slate-400">TVA</p>
                                                <p className="mt-0.5 text-sm font-medium text-slate-800">{hints.tva_rate}%</p>
                                            </div>
                                        )}
                                    </div>
                                    {hints.total_ttc != null && (
                                        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2.5">
                                            <p className="text-xs text-emerald-600">Total TTC</p>
                                            <p className="mt-0.5 text-base font-bold tabular-nums text-emerald-800">{fmtMoney(hints.total_ttc)}</p>
                                        </div>
                                    )}
                                    {(hints.vendor_nif || hints.vendor_nis || hints.vendor_rc) && (
                                        <div className="grid grid-cols-3 gap-1.5">
                                            {hints.vendor_nif && (
                                                <div className="rounded-lg bg-slate-50 px-2 py-1.5 text-center">
                                                    <p className="text-xs text-slate-400">NIF</p>
                                                    <p className="text-xs font-medium text-slate-700">{hints.vendor_nif}</p>
                                                </div>
                                            )}
                                            {hints.vendor_nis && (
                                                <div className="rounded-lg bg-slate-50 px-2 py-1.5 text-center">
                                                    <p className="text-xs text-slate-400">NIS</p>
                                                    <p className="text-xs font-medium text-slate-700">{hints.vendor_nis}</p>
                                                </div>
                                            )}
                                            {hints.vendor_rc && (
                                                <div className="rounded-lg bg-slate-50 px-2 py-1.5 text-center">
                                                    <p className="text-xs text-slate-400">RC</p>
                                                    <p className="text-xs font-medium text-slate-700">{hints.vendor_rc}</p>
                                                </div>
                                            )}
                                        </div>
                                    )}
                                </div>
                            </div>
                        )}

                        {/* File metadata */}
                        <details className="rounded-2xl border border-slate-200 bg-white shadow-sm">
                            <summary className="flex cursor-pointer list-none items-center justify-between px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 hover:bg-slate-50">
                                Informations fichier
                                <ChevronDown className="h-3.5 w-3.5" />
                            </summary>
                            <dl className="divide-y divide-slate-100 px-4 pb-3 text-sm">
                                {[
                                    ['Type doc.',    typeLabel ? (
                                        <span className={`font-medium ${isAiType ? 'text-violet-700' : 'text-slate-900'}`}>
                                            {typeLabel}{isAiType ? ' (IA)' : ''}
                                        </span>
                                    ) : <span className="italic text-slate-400">Non défini</span>],
                                    ['MIME',         <span className="font-mono text-xs text-slate-600">{document.mime_type}</span>],
                                    ['Taille',       fmtBytes(document.file_size_bytes)],
                                    ['Source',       document.source],
                                    ['Téléversé le', fmtDate(document.created_at, true)],
                                    ['Conservation', fmtDate(document.retention_until)],
                                ].map(([label, val]) => (
                                    <div key={label} className="flex items-center justify-between py-2">
                                        <dt className="text-slate-400">{label}</dt>
                                        <dd className="text-slate-800">{val}</dd>
                                    </div>
                                ))}
                            </dl>
                        </details>

                        {/* OCR error */}
                        {document.ocr_error && (
                            <div className="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
                                <div className="flex items-center gap-1.5 font-semibold">
                                    <AlertTriangle className="h-4 w-4" />
                                    Erreur OCR
                                </div>
                                <pre className="mt-1.5 whitespace-pre-wrap break-words text-xs text-rose-700">{document.ocr_error}</pre>
                            </div>
                        )}

                        {/* Linked expenses */}
                        {linkedExpenses.length > 0 && (
                            <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                                <p className="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    Dépenses liées ({linkedExpenses.length})
                                </p>
                                <div className="space-y-2">
                                    {linkedExpenses.map((e) => (
                                        <Link
                                            key={e.id}
                                            href={`/expenses/${e.id}`}
                                            className="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2 text-sm hover:bg-slate-50"
                                        >
                                            <div>
                                                <p className="font-medium text-slate-800">{e.reference ?? '—'}</p>
                                                <p className="text-xs text-slate-400">{e.expense_date}</p>
                                            </div>
                                            {e.total_ttc != null && (
                                                <span className="tabular-nums text-sm font-semibold text-slate-700">
                                                    {fmtMoney(e.total_ttc)}
                                                </span>
                                            )}
                                        </Link>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Right: preview + raw OCR */}
                    <div className="space-y-4 lg:col-span-3">
                        <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                            <div className="flex items-center justify-between border-b border-slate-100 px-5 py-3">
                                <h2 className="text-xs font-semibold uppercase tracking-wide text-slate-500">Aperçu</h2>
                                {document.is_image ? <ImageIcon className="h-4 w-4 text-slate-400" /> : <FileText className="h-4 w-4 text-slate-400" />}
                            </div>
                            <div className="bg-slate-50 p-4">
                                {document.is_image && (
                                    <img
                                        src={downloadUrl}
                                        alt={document.file_name}
                                        loading="lazy"
                                        className="mx-auto max-h-[680px] w-auto rounded-xl border border-slate-200 bg-white shadow"
                                    />
                                )}
                                {document.is_pdf && (
                                    <iframe
                                        src={downloadUrl}
                                        title={document.file_name}
                                        className="h-[680px] w-full rounded-xl border border-slate-200 bg-white"
                                    />
                                )}
                                {!document.is_image && !document.is_pdf && (
                                    <div className="py-16 text-center text-sm text-slate-400">
                                        Aperçu non disponible pour ce type de fichier.
                                    </div>
                                )}
                            </div>
                        </div>

                        {document.ocr_raw_text && (
                            <details className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                                <summary className="flex cursor-pointer list-none items-center justify-between border-b border-slate-100 px-5 py-3 hover:bg-slate-50">
                                    <h2 className="text-xs font-semibold uppercase tracking-wide text-slate-500">Texte brut OCR</h2>
                                    <button
                                        type="button"
                                        onClick={(e) => { e.preventDefault(); copyText(); }}
                                        className="inline-flex items-center gap-1.5 rounded-lg px-2 py-1 text-xs text-slate-500 hover:bg-slate-100"
                                    >
                                        <ClipboardCopy className="h-3.5 w-3.5" />
                                        {copied ? 'Copié !' : 'Copier'}
                                    </button>
                                </summary>
                                <pre className="max-h-96 overflow-auto whitespace-pre-wrap break-words p-5 font-mono text-xs leading-relaxed text-slate-700">
                                    {document.ocr_raw_text}
                                </pre>
                            </details>
                        )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}