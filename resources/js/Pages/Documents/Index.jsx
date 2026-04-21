import { Head, Link, router } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { useDropzone } from 'react-dropzone';
import axios from 'axios';
import {
    CheckCircle2,
    ClipboardCopy,
    Download,
    FileText,
    Image as ImageIcon,
    LoaderCircle,
    RefreshCw,
    ScanText,
    TriangleAlert,
    Upload,
    X,
} from 'lucide-react';

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useNotification } from '@/Context/NotificationContext';

const DOCUMENT_TYPES = [
    { value: 'expense', label: 'Dépense / reçu' },
    { value: 'supplier_bill', label: 'Facture fournisseur' },
    { value: 'invoice', label: 'Facture client' },
    { value: 'bank_statement', label: 'Relevé bancaire' },
    { value: 'other', label: 'Autre' },
];

const STATUS_META = {
    pending: {
        label: 'En attente',
        tone: 'bg-slate-100 text-slate-700 ring-slate-200',
    },
    processing: {
        label: 'Traitement OCR',
        tone: 'bg-indigo-50 text-indigo-700 ring-indigo-200',
    },
    done: {
        label: 'Texte extrait',
        tone: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    },
    failed: {
        label: 'Échec OCR',
        tone: 'bg-rose-50 text-rose-700 ring-rose-200',
    },
};

const HINT_LABELS = {
    vendor_name: 'Fournisseur',
    reference: 'Référence',
    document_date: 'Date',
    total_ht: 'Total HT',
    total_vat: 'TVA',
    total_ttc: 'Total TTC',
    tva_rate: 'Taux TVA',
    currency: 'Devise',
    payment_method: 'Mode de paiement',
    vendor_nif: 'NIF',
    vendor_nis: 'NIS',
    vendor_rc: 'RC',
    account_code_hint: 'Compte SCF',
    document_kind: 'Type (heuristique)',
    parser_locale_hints: 'Texte AR / FR',
};

function bytesToHuman(bytes) {
    if (!bytes) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB'];
    let u = 0;
    let n = bytes;
    while (n >= 1024 && u < units.length - 1) {
        n /= 1024;
        u += 1;
    }
    return `${n.toFixed(u === 0 ? 0 : 1)} ${units[u]}`;
}

function StatusBadge({ status }) {
    const meta = STATUS_META[status] ?? STATUS_META.pending;

    return (
        <span
            className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ${meta.tone}`}
        >
            {status === 'processing' && <LoaderCircle className="h-3 w-3 animate-spin" />}
            {status === 'done' && <CheckCircle2 className="h-3 w-3" />}
            {status === 'failed' && <TriangleAlert className="h-3 w-3" />}
            {meta.label}
        </span>
    );
}

function formatHintValue(key, value) {
    if (value === null || value === undefined || value === '') return '—';

    if (key === 'parser_locale_hints' && Array.isArray(value)) {
        const map = { ar: 'Arabe', fr: 'Français (latin)' };
        return value.map((v) => map[v] ?? v).join(' · ') || '—';
    }

    if (key === 'document_kind') {
        const map = {
            supplier_invoice: 'Achat / fournisseur',
            customer_invoice: 'Vente / client',
        };
        return map[value] ?? String(value);
    }

    if (['total_ht', 'total_vat', 'total_ttc'].includes(key)) {
        const n = Number(value);
        if (Number.isFinite(n)) {
            return new Intl.NumberFormat('fr-DZ', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            }).format(n);
        }
    }

    if (key === 'tva_rate') {
        const n = Number(value);
        return Number.isFinite(n) ? `${n}%` : String(value);
    }

    if (key === 'payment_method') {
        const labels = { bank: 'Banque', cash: 'Espèces', card: 'Carte', check: 'Chèque' };
        return labels[value] ?? String(value);
    }

    return String(value);
}

function UploadCard({
    documentType,
    setDocumentType,
    onDrop,
    isDragActive,
    uploading,
    uploadProgress,
    maxSizeKb,
    getRootProps,
    getInputProps,
}) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 className="text-base font-semibold text-slate-900">Téléverser un document</h3>
                    <p className="text-sm text-slate-500">
                        PDF ou image — OCR local via Tesseract, aucune donnée envoyée à l'extérieur.
                    </p>
                </div>

                <select
                    value={documentType}
                    onChange={(e) => setDocumentType(e.target.value)}
                    className="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200"
                >
                    {DOCUMENT_TYPES.map((t) => (
                        <option key={t.value} value={t.value}>
                            {t.label}
                        </option>
                    ))}
                </select>
            </div>

            <div
                {...getRootProps()}
                className={[
                    'cursor-pointer rounded-xl border-2 border-dashed p-8 text-center transition',
                    isDragActive
                        ? 'border-indigo-500 bg-indigo-50'
                        : 'border-slate-300 bg-slate-50 hover:border-indigo-400 hover:bg-indigo-50/50',
                ].join(' ')}
            >
                <input {...getInputProps()} />

                <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-white shadow-sm">
                    {uploading ? (
                        <LoaderCircle className="h-6 w-6 animate-spin text-indigo-600" />
                    ) : (
                        <Upload className="h-6 w-6 text-indigo-600" />
                    )}
                </div>

                <p className="mt-4 text-sm font-medium text-slate-900">
                    {isDragActive
                        ? 'Déposez le fichier ici'
                        : 'Glissez-déposez un fichier, ou cliquez pour choisir'}
                </p>

                <p className="mt-1 text-xs text-slate-500">
                    PDF, PNG, JPG, JPEG, WEBP, HEIC — jusqu'à {Math.round((maxSizeKb ?? 20480) / 1024)} MB
                </p>

                {uploading && (
                    <div className="mx-auto mt-5 max-w-sm">
                        <div className="mb-1 flex justify-between text-xs text-slate-600">
                            <span>Téléversement…</span>
                            <span>{uploadProgress}%</span>
                        </div>
                        <div className="h-2 overflow-hidden rounded-full bg-slate-200">
                            <div
                                className="h-full rounded-full bg-indigo-600 transition-all"
                                style={{ width: `${uploadProgress}%` }}
                            />
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}

function HintsGrid({ hints }) {
    const entries = Object.entries(hints ?? {}).filter(
        ([, v]) => v !== null && v !== undefined && v !== ''
    );

    if (entries.length === 0) {
        return (
            <p className="text-sm text-slate-500">
                Aucune donnée structurée détectée automatiquement.
            </p>
        );
    }

    return (
        <dl className="grid gap-3 sm:grid-cols-2">
            {entries.map(([key, value]) => (
                <div
                    key={key}
                    className="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2"
                >
                    <dt className="text-xs uppercase tracking-wide text-slate-500">
                        {HINT_LABELS[key] ?? key}
                    </dt>
                    <dd className="mt-0.5 text-sm font-medium text-slate-900">
                        {formatHintValue(key, value)}
                    </dd>
                </div>
            ))}
        </dl>
    );
}

function ActiveDocumentPanel({ active, onClose, onRetry, onUseInExpense }) {
    const [copied, setCopied] = useState(false);

    useEffect(() => {
        setCopied(false);
    }, [active?.id]);

    if (!active) {
        return (
            <div className="flex h-full min-h-[280px] items-center justify-center rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center">
                <div>
                    <ScanText className="mx-auto h-10 w-10 text-slate-400" />
                    <p className="mt-3 text-sm font-medium text-slate-800">
                        Aucun document sélectionné
                    </p>
                    <p className="mt-1 text-sm text-slate-500">
                        Téléversez un document ou sélectionnez-en un dans la liste pour voir son texte OCR.
                    </p>
                </div>
            </div>
        );
    }

    const copyText = async () => {
        if (!active.ocr_raw_text) return;
        try {
            await navigator.clipboard.writeText(active.ocr_raw_text);
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        } catch (e) {
            setCopied(false);
        }
    };

    return (
        <div className="flex h-full flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div className="flex items-start justify-between gap-3 border-b border-slate-200 px-5 py-4">
                <div className="min-w-0">
                    <div className="flex items-center gap-2">
                        <FileText className="h-4 w-4 shrink-0 text-slate-500" />
                        <h3 className="truncate text-base font-semibold text-slate-900">
                            {active.file_name}
                        </h3>
                    </div>
                    <div className="mt-1 flex flex-wrap items-center gap-2">
                        <StatusBadge status={active.ocr_status} />
                        <a
                            href={`/documents/${active.id}`}
                            className="inline-flex items-center gap-1 rounded-md border border-slate-200 bg-white px-2 py-0.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
                        >
                            Ouvrir en plein écran
                        </a>
                    </div>
                </div>

                <button
                    type="button"
                    onClick={onClose}
                    className="rounded-md p-1.5 text-slate-500 hover:bg-slate-100"
                    aria-label="Fermer"
                >
                    <X className="h-4 w-4" />
                </button>
            </div>

            <div className="flex-1 space-y-5 overflow-y-auto p-5">
                {active.ocr_status === 'processing' && (
                    <div className="flex items-center gap-3 rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-800">
                        <LoaderCircle className="h-4 w-4 animate-spin" />
                        Extraction OCR en cours…
                    </div>
                )}

                {active.ocr_status === 'failed' && (
                    <div className="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                        <div className="flex items-start gap-2">
                            <TriangleAlert className="mt-0.5 h-4 w-4 shrink-0" />
                            <div>
                                <div className="font-medium">L'OCR a échoué</div>
                                {active.ocr_error && (
                                    <div className="mt-1 text-xs">{active.ocr_error}</div>
                                )}
                                <button
                                    type="button"
                                    onClick={() => onRetry(active.id)}
                                    className="mt-2 inline-flex items-center gap-1.5 rounded-md border border-rose-300 bg-white px-2.5 py-1 text-xs font-medium text-rose-700 hover:bg-rose-100"
                                >
                                    <RefreshCw className="h-3 w-3" />
                                    Relancer l'OCR
                                </button>
                            </div>
                        </div>
                    </div>
                )}

                {active.ocr_status === 'done' && (
                    <>
                        <section>
                            <h4 className="mb-2 text-sm font-semibold text-slate-900">
                                Données détectées
                            </h4>
                            <HintsGrid hints={active.hints} />
                        </section>

                        <section>
                            <div className="mb-2 flex items-center justify-between">
                                <h4 className="text-sm font-semibold text-slate-900">
                                    Texte OCR brut
                                </h4>

                                <div className="flex items-center gap-2">
                                    <button
                                        type="button"
                                        onClick={copyText}
                                        className="inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50"
                                    >
                                        <ClipboardCopy className="h-3 w-3" />
                                        {copied ? 'Copié' : 'Copier'}
                                    </button>

                                    <a
                                        href={`/documents/${active.id}/download`}
                                        className="inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50"
                                    >
                                        <Download className="h-3 w-3" />
                                        Original
                                    </a>
                                </div>
                            </div>

                            <pre
                                dir="auto"
                                style={{ unicodeBidi: 'plaintext' }}
                                className="max-h-[360px] overflow-auto whitespace-pre-wrap break-words rounded-lg border border-slate-200 bg-slate-50 p-3 font-sans text-xs text-slate-800"
                            >
                                {active.ocr_raw_text || '(texte vide)'}
                            </pre>
                        </section>
                    </>
                )}
            </div>

            {active.ocr_status === 'done' && (
                <div className="border-t border-slate-200 bg-slate-50 px-5 py-3">
                    <button
                        type="button"
                        onClick={() => onUseInExpense(active.id)}
                        className="inline-flex w-full items-center justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-indigo-700"
                    >
                        Créer une dépense à partir de ce document
                    </button>
                </div>
            )}
        </div>
    );
}

export default function Index({ documents, config }) {
    const maxSizeKb = config?.max_size_kb ?? 20480;

    const [documentType, setDocumentType] = useState('expense');
    const [uploading, setUploading] = useState(false);
    const [uploadProgress, setUploadProgress] = useState(0);

    const [activeId, setActiveId] = useState(null);
    const [active, setActive] = useState(null);
    const [loadingActive, setLoadingActive] = useState(false);
    const { error: notifyError } = useNotification();

    const rows = documents?.data ?? [];

    const needsPolling = useMemo(() => {
        if (active && ['pending', 'processing'].includes(active.ocr_status)) return true;
        return rows.some((r) => ['pending', 'processing'].includes(r.ocr_status));
    }, [active, rows]);

    const startUpload = useCallback(
        async (file) => {
            setUploading(true);
            setUploadProgress(0);

            const formData = new FormData();
            formData.append('file', file);
            formData.append('document_type', documentType);

            try {
                const response = await axios.post('/documents/upload', formData, {
                    headers: {
                        'Content-Type': 'multipart/form-data',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    onUploadProgress: (event) => {
                        if (!event.total) return;
                        setUploadProgress(Math.round((event.loaded * 100) / event.total));
                    },
                });

                const newId = response.data?.document_id;

                router.reload({
                    only: ['documents'],
                    onSuccess: () => {
                        if (newId) setActiveId(newId);
                    },
                });
            } catch (error) {
                const message =
                    error?.response?.data?.message ||
                    "Le téléversement a échoué. Vérifiez le format et la taille du fichier.";
                notifyError(message);
            } finally {
                setUploading(false);
                setUploadProgress(0);
            }
        },
        [documentType]
    );

    const onDrop = useCallback(
        (acceptedFiles) => {
            const file = acceptedFiles?.[0];
            if (!file) return;
            startUpload(file);
        },
        [startUpload]
    );

    const { getRootProps, getInputProps, isDragActive } = useDropzone({
        accept: {
            'application/pdf': ['.pdf'],
            'image/*': ['.png', '.jpg', '.jpeg', '.webp', '.heic'],
        },
        multiple: false,
        onDrop,
        disabled: uploading,
    });

    const fetchActive = useCallback(async (id) => {
        if (!id) {
            setActive(null);
            return;
        }

        setLoadingActive(true);
        try {
            const r = await axios.get(`/documents/${id}/status`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            setActive(r.data);
        } catch (e) {
            setActive(null);
        } finally {
            setLoadingActive(false);
        }
    }, []);

    useEffect(() => {
        fetchActive(activeId);
    }, [activeId, fetchActive]);

    useEffect(() => {
        if (!needsPolling) return;

        const id = setInterval(() => {
            router.reload({ only: ['documents'] });
            if (activeId) fetchActive(activeId);
        }, 2500);

        return () => clearInterval(id);
    }, [needsPolling, activeId, fetchActive]);

    const handleRetry = async (id) => {
        try {
            await axios.post(`/documents/${id}/retry`, null, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            router.reload({ only: ['documents'] });
            fetchActive(id);
        } catch (e) {
            notifyError("La relance de l'OCR a échoué.");
        }
    };

    const handleUseInExpense = (id) => {
        window.location.href = `/documents/${id}/use-in-expense`;
    };

    return (
        <AuthenticatedLayout header="Documents">
            <Head title="Documents" />

            <div className="space-y-6">
                <div>
                    <h2 className="text-xl font-semibold text-slate-900">Documents & OCR</h2>
                    <p className="text-sm text-slate-500">
                        Importez vos factures et reçus. Le texte est extrait localement et peut
                        être utilisé pour créer une dépense en un clic.
                    </p>
                </div>

                <div className="grid gap-6 xl:grid-cols-3">
                    <div className="space-y-6 xl:col-span-1">
                        <UploadCard
                            documentType={documentType}
                            setDocumentType={setDocumentType}
                            onDrop={onDrop}
                            isDragActive={isDragActive}
                            uploading={uploading}
                            uploadProgress={uploadProgress}
                            maxSizeKb={maxSizeKb}
                            getRootProps={getRootProps}
                            getInputProps={getInputProps}
                        />

                        <div className="rounded-2xl border border-slate-200 bg-white shadow-sm">
                            <div className="border-b border-slate-200 px-5 py-4">
                                <h3 className="text-base font-semibold text-slate-900">
                                    Documents récents
                                </h3>
                                <p className="text-sm text-slate-500">
                                    Cliquez sur un document pour voir son texte OCR.
                                </p>
                            </div>

                            <ul className="divide-y divide-slate-100">
                                {rows.length === 0 && (
                                    <li className="px-5 py-6 text-center text-sm text-slate-500">
                                        Aucun document pour l'instant.
                                    </li>
                                )}

                                {rows.map((doc) => {
                                    const isActive = doc.id === activeId;
                                    const Icon = doc.mime_type?.startsWith('image/')
                                        ? ImageIcon
                                        : FileText;

                                    return (
                                        <li
                                            key={doc.id}
                                            className={[
                                                'cursor-pointer px-5 py-3 transition hover:bg-slate-50',
                                                isActive ? 'bg-indigo-50/60' : '',
                                            ].join(' ')}
                                            onClick={() => setActiveId(doc.id)}
                                        >
                                            <div className="flex items-start gap-3">
                                                <div className="mt-0.5 rounded-lg bg-white p-1.5 ring-1 ring-slate-200">
                                                    <Icon className="h-4 w-4 text-slate-600" />
                                                </div>

                                                <div className="min-w-0 flex-1">
                                                    <p className="truncate text-sm font-medium text-slate-900">
                                                        {doc.file_name}
                                                    </p>
                                                    <p className="mt-0.5 text-xs text-slate-500">
                                                        {bytesToHuman(doc.file_size_bytes)}
                                                    </p>
                                                    <div className="mt-1.5">
                                                        <StatusBadge status={doc.ocr_status} />
                                                    </div>
                                                </div>
                                            </div>
                                        </li>
                                    );
                                })}
                            </ul>
                        </div>
                    </div>

                    <div className="xl:col-span-2">
                        {loadingActive && !active ? (
                            <div className="flex min-h-[280px] items-center justify-center rounded-2xl border border-slate-200 bg-white">
                                <LoaderCircle className="h-6 w-6 animate-spin text-slate-400" />
                            </div>
                        ) : (
                            <ActiveDocumentPanel
                                active={active}
                                onClose={() => {
                                    setActiveId(null);
                                    setActive(null);
                                }}
                                onRetry={handleRetry}
                                onUseInExpense={handleUseInExpense}
                            />
                        )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
