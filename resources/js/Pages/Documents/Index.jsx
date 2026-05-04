import { Head, Link, router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { useDropzone } from 'react-dropzone';
import axios from 'axios';
import {
    ArrowRight,
    CheckCircle2,
    ClipboardCopy,
    Download,
    Eye,
    FileText,
    LoaderCircle,
    RefreshCw,
    ScanText,
    TriangleAlert,
    Upload,
    X,
} from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useNotification } from '@/Context/NotificationContext';

// ─── Constants ────────────────────────────────────────────────────────────────

// Values must match backend: DocumentController upload validation
// 'in:supplier_bill,expense,invoice,bank_statement,other'
const DOCUMENT_TYPES = [
    { value: '',              label: '— Détecter automatiquement —' },
    { value: 'supplier_bill', label: 'Facture achat / Fournisseur'  },
    { value: 'invoice',       label: 'Facture vente'                },
    { value: 'bank_statement',label: 'Relevé bancaire'              },
    { value: 'expense',       label: 'Reçu / Note de frais'         },
    { value: 'other',         label: 'Autre'                        },
];

// Display labels — covers both old parser values and new
const TYPE_LABELS = {
    purchase_invoice: 'Facture achat',
    supplier_bill:    'Facture achat',
    supplier_invoice: 'Facture achat',
    sales_invoice:    'Facture vente',
    customer_invoice: 'Facture vente',
    invoice:          'Facture vente',
    credit_note:      'Avoir',
    bank_statement:   'Relevé bancaire',
    receipt:          'Reçu / Ticket',
    expense:          'Note de frais',
    other:            'Autre',
};

const STATUS_META = {
    pending:    { label: 'En attente',   tone: 'bg-slate-100 text-slate-600',    spin: false, check: false, warn: false },
    processing: { label: 'OCR en cours', tone: 'bg-indigo-50 text-indigo-700',   spin: true,  check: false, warn: false },
    done:       { label: 'OCR terminé',  tone: 'bg-emerald-50 text-emerald-700', spin: false, check: true,  warn: false },
    failed:     { label: 'Échec OCR',    tone: 'bg-rose-50 text-rose-700',       spin: false, check: false, warn: true  },
};

// Backward-compat kind map
const KIND_MAP = {
    supplier_invoice: 'purchase_invoice',
    customer_invoice: 'sales_invoice',
};

// ─── Helpers ──────────────────────────────────────────────────────────────────

function fmtMoney(v) {
    if (v === null || v === undefined) return null;
    return new Intl.NumberFormat('fr-DZ', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(v);
}

function fmtDate(iso) {
    if (!iso) return null;
    return new Date(iso).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

function resolveDocType(doc) {
    const rawKind = doc.hints?.document_kind ?? doc.ocr_parsed_hints?.document_kind;
    const normalKind = rawKind ? (KIND_MAP[rawKind] ?? rawKind) : null;
    return doc.document_type ?? normalKind;
}

// ─── StatusBadge ─────────────────────────────────────────────────────────────

function StatusBadge({ status }) {
    const m = STATUS_META[status] ?? STATUS_META.pending;
    return (
        <span className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium ${m.tone}`}>
            {m.spin  && <LoaderCircle  className="h-3 w-3 animate-spin" />}
            {m.check && <CheckCircle2  className="h-3 w-3" />}
            {m.warn  && <TriangleAlert className="h-3 w-3" />}
            {m.label}
        </span>
    );
}

// ─── Upload Zone ─────────────────────────────────────────────────────────────

function UploadZone({ documentType, setDocumentType, getRootProps, getInputProps, isDragActive, uploading, uploadProgress, maxSizeKb }) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
                <div>
                    <h3 className="text-sm font-semibold text-slate-900">Téléverser un document</h3>
                    <p className="mt-0.5 text-xs text-slate-500">OCR local via Tesseract — aucune donnée envoyée à l'extérieur</p>
                </div>
                <select
                    value={documentType}
                    onChange={(e) => setDocumentType(e.target.value)}
                    className="rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-medium text-slate-700 focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                >
                    {DOCUMENT_TYPES.map((t) => (
                        <option key={t.value} value={t.value}>{t.label}</option>
                    ))}
                </select>
            </div>

            <div
                {...getRootProps()}
                className={[
                    'cursor-pointer p-6 text-center transition-colors',
                    isDragActive ? 'bg-indigo-50' : 'hover:bg-slate-50/70',
                ].join(' ')}
            >
                <input {...getInputProps()} />
                <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-indigo-50">
                    {uploading
                        ? <LoaderCircle className="h-5 w-5 animate-spin text-indigo-600" />
                        : <Upload       className="h-5 w-5 text-indigo-600" />
                    }
                </div>
                <p className="mt-3 text-sm font-medium text-slate-800">
                    {isDragActive ? 'Déposez ici…' : 'Glisser-déposer ou cliquer'}
                </p>
                <p className="mt-1 text-xs text-slate-400">
                    PDF, PNG, JPG, WEBP, HEIC — max {Math.round((maxSizeKb ?? 20480) / 1024)} MB
                </p>
                {uploading && (
                    <div className="mx-auto mt-4 max-w-xs">
                        <div className="mb-1 flex justify-between text-xs text-slate-500">
                            <span>Envoi…</span><span>{uploadProgress}%</span>
                        </div>
                        <div className="h-1.5 overflow-hidden rounded-full bg-slate-200">
                            <div className="h-full rounded-full bg-indigo-500 transition-all" style={{ width: `${uploadProgress}%` }} />
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}

// ─── Document Row ─────────────────────────────────────────────────────────────

function DocRow({ doc, isActive, onClick }) {
    const hints   = doc.hints ?? doc.ocr_parsed_hints ?? {};
    const vendor  = hints.vendor_name;
    const amount  = hints.total_ttc != null ? fmtMoney(hints.total_ttc) : null;
    const date    = hints.document_date ? fmtDate(hints.document_date) : null;
    const docType = resolveDocType(doc);
    const typeLabel = docType ? (TYPE_LABELS[docType] ?? docType) : null;

    return (
        <button
            type="button"
            onClick={onClick}
            className={[
                'group w-full rounded-xl border px-4 py-3 text-left transition-all',
                isActive
                    ? 'border-indigo-300 bg-indigo-50 shadow-sm'
                    : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50',
            ].join(' ')}
        >
            <div className="flex items-start justify-between gap-2">
                <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-medium text-slate-900">{doc.file_name}</p>
                    {(vendor || date) && (
                        <p className="mt-0.5 truncate text-xs text-slate-500">
                            {[vendor, date].filter(Boolean).join(' · ')}
                        </p>
                    )}
                    <div className="mt-1.5 flex flex-wrap items-center gap-1.5">
                        <StatusBadge status={doc.ocr_status} />
                        {typeLabel && (
                            <span className="rounded-full bg-indigo-50 px-2 py-0.5 text-xs text-indigo-600">
                                {typeLabel}
                            </span>
                        )}
                    </div>
                </div>
                {amount && (
                    <span className="shrink-0 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold tabular-nums text-emerald-700">
                        {amount} DZD
                    </span>
                )}
            </div>
        </button>
    );
}

// ─── Preview Panel ────────────────────────────────────────────────────────────

function PreviewPanel({ doc, onClose, onRetry }) {
    const [copied, setCopied] = useState(false);
    const hints = doc?.hints ?? doc?.ocr_parsed_hints ?? {};

    const copyText = async () => {
        if (!doc?.ocr_raw_text) return;
        try {
            await navigator.clipboard.writeText(doc.ocr_raw_text);
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        } catch (_) {}
    };

    if (!doc) {
        return (
            <div className="flex h-full min-h-[320px] flex-col items-center justify-center rounded-2xl border border-dashed border-slate-200 bg-white p-8 text-center">
                <ScanText className="h-10 w-10 text-slate-300" />
                <p className="mt-3 text-sm font-medium text-slate-700">Sélectionnez un document</p>
                <p className="mt-1 text-xs text-slate-400">Cliquez sur un document dans la liste pour voir son aperçu</p>
            </div>
        );
    }

    const hasOcr  = doc.ocr_status === 'done';
    const docType = resolveDocType(doc);

    const getCTA = () => {
        if (!hasOcr) return null;
        switch (docType) {
            case 'sales_invoice':
            case 'invoice':
                return { label: 'Créer facture vente',    href: `/invoices/create?from_document=${doc.id}`,                          cls: 'bg-emerald-600 hover:bg-emerald-700' };
            case 'bank_statement':
                return { label: 'Importer dans Trésorerie', href: `/bank/import?from_document=${doc.id}`,                            cls: 'bg-sky-600 hover:bg-sky-700' };
            case 'credit_note':
                return { label: 'Créer un avoir',          href: `/invoices/create?type=credit_note&from_document=${doc.id}`,        cls: 'bg-amber-600 hover:bg-amber-700' };
            case 'purchase_invoice':
            case 'supplier_bill':
            case 'receipt':
            case 'expense':
            default:
                return { label: 'Créer une dépense',       href: `/documents/${doc.id}/use-in-expense`,                              cls: 'bg-indigo-600 hover:bg-indigo-700' };
        }
    };

    const cta = getCTA();

    return (
        <div className="flex h-full flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            {/* Header */}
            <div className="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-3">
                <div className="flex min-w-0 items-center gap-2">
                    <FileText className="h-4 w-4 shrink-0 text-slate-400" />
                    <span className="truncate text-sm font-semibold text-slate-900">{doc.file_name}</span>
                    <StatusBadge status={doc.ocr_status} />
                </div>
                <div className="flex shrink-0 items-center gap-1">
                    {(doc.ocr_status === 'failed' || doc.ocr_status === 'done') && (
                        <button onClick={() => onRetry(doc.id)} className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700" title="Relancer OCR">
                            <RefreshCw className="h-3.5 w-3.5" />
                        </button>
                    )}
                    <a href={`/documents/${doc.id}/download`} className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700" title="Télécharger">
                        <Download className="h-3.5 w-3.5" />
                    </a>
                    <Link href={`/documents/${doc.id}`} className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700" title="Ouvrir la page complète">
                        <Eye className="h-3.5 w-3.5" />
                    </Link>
                    <button onClick={onClose} className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                        <X className="h-3.5 w-3.5" />
                    </button>
                </div>
            </div>

            <div className="flex-1 overflow-y-auto">
                {/* Extracted data */}
                {hasOcr && Object.keys(hints).length > 0 && (
                    <div className="border-b border-slate-100 px-5 py-4">
                        <div className="grid grid-cols-2 gap-x-4 gap-y-2.5">
                            {hints.vendor_name && (
                                <div className="col-span-2">
                                    <p className="text-xs text-slate-400">Fournisseur / Émetteur</p>
                                    <p className="text-sm font-semibold text-slate-900">{hints.vendor_name}</p>
                                </div>
                            )}
                            {hints.reference && (
                                <div>
                                    <p className="text-xs text-slate-400">N° document</p>
                                    <p className="text-sm font-medium text-slate-800">{hints.reference}</p>
                                </div>
                            )}
                            {hints.document_date && (
                                <div>
                                    <p className="text-xs text-slate-400">Date</p>
                                    <p className="text-sm font-medium text-slate-800">{fmtDate(hints.document_date)}</p>
                                </div>
                            )}
                            {hints.total_ttc != null && (
                                <div>
                                    <p className="text-xs text-slate-400">Total TTC</p>
                                    <p className="text-sm font-semibold tabular-nums text-emerald-700">{fmtMoney(hints.total_ttc)} DZD</p>
                                </div>
                            )}
                            {hints.tva_rate != null && (
                                <div>
                                    <p className="text-xs text-slate-400">TVA</p>
                                    <p className="text-sm font-medium text-slate-800">{hints.tva_rate}%</p>
                                </div>
                            )}
                        </div>
                    </div>
                )}

                {/* OCR status messages */}
                {doc.ocr_status === 'processing' && (
                    <div className="flex flex-col items-center gap-2 px-5 py-8 text-center">
                        <LoaderCircle className="h-8 w-8 animate-spin text-indigo-400" />
                        <p className="text-sm text-slate-600">Extraction du texte en cours…</p>
                    </div>
                )}
                {doc.ocr_status === 'pending' && (
                    <div className="flex flex-col items-center gap-2 px-5 py-8 text-center">
                        <ScanText className="h-8 w-8 text-slate-300" />
                        <p className="text-sm text-slate-500">En attente de traitement OCR</p>
                    </div>
                )}
                {doc.ocr_status === 'failed' && (
                    <div className="m-5 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
                        <div className="flex items-center gap-1.5 font-semibold">
                            <TriangleAlert className="h-4 w-4" />
                            Échec de l'extraction
                        </div>
                        <p className="mt-1 text-xs text-rose-600">Cliquez sur ↺ en haut pour relancer l'OCR.</p>
                    </div>
                )}

                {/* Collapsible raw text */}
                {doc.ocr_raw_text && (
                    <details className="group border-t border-slate-100">
                        <summary className="flex cursor-pointer list-none items-center justify-between px-5 py-3 text-xs font-medium text-slate-500 hover:bg-slate-50">
                            Texte brut OCR
                            <span className="text-slate-400 group-open:hidden">Afficher ▾</span>
                            <span className="hidden text-slate-400 group-open:inline">Masquer ▴</span>
                        </summary>
                        <div className="relative px-5 pb-4">
                            <button onClick={copyText} className="absolute right-8 top-1 inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs text-slate-400 hover:bg-slate-100">
                                <ClipboardCopy className="h-3 w-3" />
                                {copied ? 'Copié !' : 'Copier'}
                            </button>
                            <pre className="max-h-48 overflow-auto whitespace-pre-wrap break-words rounded-lg bg-slate-50 p-3 font-mono text-xs leading-relaxed text-slate-700">
                                {doc.ocr_raw_text}
                            </pre>
                        </div>
                    </details>
                )}
            </div>

            {/* Smart CTA footer */}
            {hasOcr && cta && (
                <div className="border-t border-slate-100 p-4">
                    <a
                        href={cta.href}
                        className={`flex w-full items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold text-white transition-colors ${cta.cls}`}
                    >
                        {cta.label}
                        <ArrowRight className="h-4 w-4" />
                    </a>
                </div>
            )}
            {hasOcr && !cta && (
                <div className="border-t border-slate-100 p-4">
                    <Link
                        href={`/documents/${doc.id}`}
                        className="flex w-full items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700"
                    >
                        Ouvrir et utiliser
                        <ArrowRight className="h-4 w-4" />
                    </Link>
                </div>
            )}
        </div>
    );
}

// ─── Main Page ────────────────────────────────────────────────────────────────

export default function Index({ documents: initialDocuments = [], config }) {
    const maxFileSizeKb = config?.max_size_kb ?? 20480;

    const [documents, setDocuments] = useState(
        Array.isArray(initialDocuments) ? initialDocuments : (initialDocuments?.data ?? [])
    );
    const [activeId,      setActiveId]      = useState(null);
    const [documentType,  setDocumentType]  = useState('');
    const [uploading,     setUploading]     = useState(false);
    const [uploadProgress, setUploadProgress] = useState(0);

    const { success: notifySuccess, error: notifyError } = useNotification();
    const pollingRef = useRef({});

    const active = documents.find((d) => d.id === activeId) ?? null;

    const pollDocument = useCallback((id) => {
        if (pollingRef.current[id]) return;
        pollingRef.current[id] = setInterval(async () => {
            try {
                const res = await axios.get(`/documents/${id}/status`);
                const updated = res.data;
                setDocuments((prev) => prev.map((d) => d.id === id ? { ...d, ...updated } : d));
                if (updated.ocr_status === 'done' || updated.ocr_status === 'failed') {
                    clearInterval(pollingRef.current[id]);
                    delete pollingRef.current[id];
                }
            } catch (_) {
                clearInterval(pollingRef.current[id]);
                delete pollingRef.current[id];
            }
        }, 3000);
    }, []);

    useEffect(() => {
        documents.forEach((d) => {
            if (d.ocr_status === 'processing' || d.ocr_status === 'pending') pollDocument(d.id);
        });
        return () => { Object.values(pollingRef.current).forEach(clearInterval); };
    }, []);

    const onDrop = useCallback(async (accepted) => {
        if (!accepted.length) return;
        const file = accepted[0];
        const fd = new FormData();
        fd.append('file', file);
        // Only send document_type if the user actually selected one
        if (documentType) fd.append('document_type', documentType);

        setUploading(true);
        setUploadProgress(0);

        try {
            const response = await axios.post('/documents/upload', fd, {
                headers: {
                    'Content-Type': 'multipart/form-data',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                onUploadProgress: (e) => {
                    if (!e.total) return;
                    setUploadProgress(Math.round((e.loaded * 100) / e.total));
                },
            });

            const newId = response.data?.document_id;
            notifySuccess?.('Document téléversé avec succès');

            router.reload({
                only: ['documents'],
                onSuccess: (page) => {
                    const fresh = page?.props?.documents?.data ?? page?.props?.documents ?? [];
                    setDocuments(Array.isArray(fresh) ? fresh : []);
                    if (newId) { setActiveId(newId); pollDocument(newId); }
                },
            });
        } catch (error) {
            const message = error?.response?.data?.message ?? 'Le téléversement a échoué. Vérifiez le format et la taille du fichier.';
            notifyError?.(message);
        } finally {
            setUploading(false);
            setUploadProgress(0);
        }
    }, [documentType, pollDocument, notifySuccess, notifyError]);

    const { getRootProps, getInputProps, isDragActive } = useDropzone({
        onDrop,
        multiple: false,
        accept: {
            'application/pdf': ['.pdf'],
            'image/png':  ['.png'],
            'image/jpeg': ['.jpg', '.jpeg'],
            'image/webp': ['.webp'],
            'image/heic': ['.heic'],
        },
    });

    const handleRetry = useCallback(async (id) => {
        const token = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
        try {
            await fetch(`/documents/${id}/retry`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' },
            });
            setDocuments((prev) => prev.map((d) => d.id === id ? { ...d, ocr_status: 'processing' } : d));
            pollDocument(id);
        } catch (_) {}
    }, [pollDocument]);

    return (
        <AuthenticatedLayout>
            <Head title="Documents" />
            <div className="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6">

                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-bold text-slate-900">Documents</h1>
                        <p className="mt-0.5 text-sm text-slate-500">
                            Téléversez vos factures et justificatifs — l'OCR extrait les données automatiquement
                        </p>
                    </div>
                    <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600">
                        {documents.length} document{documents.length !== 1 ? 's' : ''}
                    </span>
                </div>

                <div className="grid gap-5 lg:grid-cols-5">
                    {/* Left: upload + list */}
                    <div className="space-y-4 lg:col-span-2">
                        <UploadZone
                            documentType={documentType}
                            setDocumentType={setDocumentType}
                            getRootProps={getRootProps}
                            getInputProps={getInputProps}
                            isDragActive={isDragActive}
                            uploading={uploading}
                            uploadProgress={uploadProgress}
                            maxSizeKb={maxFileSizeKb}
                        />

                        {documents.length > 0 ? (
                            <div
                                className="rounded-2xl border border-slate-200 bg-white p-3"
                                style={{ maxHeight: '420px', minHeight: '180px', overflowY: 'auto' }}
                            >
                                <div className="space-y-2">
                                    {documents.map((doc) => (
                                        <DocRow
                                            key={doc.id}
                                            doc={doc}
                                            isActive={doc.id === activeId}
                                            onClick={() => setActiveId(doc.id === activeId ? null : doc.id)}
                                        />
                                    ))}
                                </div>
                            </div>
                        ) : (
                            <div className="rounded-2xl border border-dashed border-slate-200 bg-white py-10 text-center">
                                <FileText className="mx-auto h-8 w-8 text-slate-300" />
                                <p className="mt-2 text-sm text-slate-500">Aucun document</p>
                                <p className="text-xs text-slate-400">Téléversez votre premier document ci-dessus</p>
                            </div>
                        )}
                    </div>

                    {/* Right: sticky preview */}
                    <div className="lg:col-span-3">
                        <div className="sticky top-6">
                            <PreviewPanel
                                doc={active}
                                onClose={() => setActiveId(null)}
                                onRetry={handleRetry}
                            />
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}