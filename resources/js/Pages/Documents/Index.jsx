import { Head, Link, router } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { useDropzone } from 'react-dropzone';
import axios from 'axios';
import {
    FileText,
    Image as ImageIcon,
    LoaderCircle,
    ScanSearch,
    TriangleAlert,
    Upload,
} from 'lucide-react';

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Alert from '@/Components/UI/Alert';
import Spinner from '@/Components/UI/Spinner';
import ConfidenceBadge from '@/Components/UI/ConfidenceBadge';

const FIELD_LABELS = {
    vendor_name: 'Fournisseur',
    supplier_name: 'Fournisseur',
    invoice_number: 'N° de facture',
    document_number: 'N° de document',
    issue_date: 'Date',
    expense_date: 'Date de dépense',
    due_date: 'Date d’échéance',
    total_ht: 'Total HT',
    total_vat: 'TVA',
    total_ttc: 'Total TTC',
    currency: 'Devise',
    payment_mode: 'Mode de paiement',
    notes: 'Notes',
};

const formatCurrency = (value) =>
    new Intl.NumberFormat('fr-DZ', {
        style: 'currency',
        currency: 'DZD',
    }).format(Number(value ?? 0));

function labelForField(key) {
    return FIELD_LABELS[key] ?? key;
}

function normalizeSuggestions(rawSuggestions) {
    if (!rawSuggestions) return [];

    if (Array.isArray(rawSuggestions)) {
        return rawSuggestions.map((item, index) => ({
            key: item.field ?? item.key ?? `field_${index}`,
            suggested_value: item.suggested_value ?? item.value ?? '',
            confidence: Number(item.confidence ?? 0),
        }));
    }

    return Object.entries(rawSuggestions).map(([key, value]) => ({
        key,
        suggested_value:
            typeof value === 'object' && value !== null
                ? value.suggested_value ?? value.value ?? ''
                : value ?? '',
        confidence:
            typeof value === 'object' && value !== null
                ? Number(value.confidence ?? 0)
                : 0,
    }));
}

function DocumentPreview({ file, previewUrl, uploadProgress, polling, documentType }) {
    return (
        <div className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div className="mb-4 flex items-center gap-3">
                <div className="rounded-lg bg-indigo-50 p-2 text-indigo-700">
                    <ScanSearch className="h-5 w-5" />
                </div>
                <div>
                    <h3 className="text-base font-semibold text-gray-900">
                        Aperçu du document
                    </h3>
                    <p className="text-sm text-gray-500">
                        Vérifiez le document avant confirmation
                    </p>
                </div>
            </div>

            {!file ? (
                <div className="flex min-h-[280px] items-center justify-center rounded-xl border border-dashed border-gray-300 bg-gray-50 p-8 text-center">
                    <div>
                        <FileText className="mx-auto h-10 w-10 text-gray-400" />
                        <p className="mt-3 text-sm text-gray-500">
                            Aucun document sélectionné
                        </p>
                    </div>
                </div>
            ) : (
                <div className="space-y-4">
                    <div className="rounded-xl border border-gray-200 bg-gray-50 p-4">
                        <div className="flex items-start gap-3">
                            <div className="rounded-lg bg-white p-2 shadow-sm">
                                {file.type?.startsWith('image/') ? (
                                    <ImageIcon className="h-5 w-5 text-gray-600" />
                                ) : (
                                    <FileText className="h-5 w-5 text-gray-600" />
                                )}
                            </div>

                            <div className="min-w-0 flex-1">
                                <p className="truncate text-sm font-medium text-gray-900">
                                    {file.name}
                                </p>
                                <p className="text-xs text-gray-500">
                                    Type: {documentType === 'expense' ? 'Dépense' : 'Document'}
                                </p>
                            </div>
                        </div>
                    </div>

                    {file.type?.startsWith('image/') && previewUrl ? (
                        <div className="overflow-hidden rounded-xl border border-gray-200 bg-gray-50">
                            <img
                                src={previewUrl}
                                alt="Aperçu du document importé"
                                className="max-h-[420px] w-full object-contain"
                            />
                        </div>
                    ) : (
                        <div className="flex min-h-[280px] items-center justify-center rounded-xl border border-dashed border-gray-300 bg-gray-50 p-8 text-center">
                            <div>
                                <FileText className="mx-auto h-12 w-12 text-gray-400" />
                                <p className="mt-3 text-sm font-medium text-gray-700">
                                    Aperçu PDF non intégré
                                </p>
                                <p className="mt-1 text-sm text-gray-500">
                                    Le fichier a bien été sélectionné et sera traité après l’envoi.
                                </p>
                            </div>
                        </div>
                    )}

                    {(uploadProgress > 0 || polling) && (
                        <div>
                            <div className="mb-2 flex items-center justify-between text-sm text-gray-600">
                                <span>
                                    {polling ? 'Analyse OCR/IA en cours...' : 'Téléversement...'}
                                </span>
                                <span>{polling ? 'Traitement' : `${uploadProgress}%`}</span>
                            </div>

                            <div className="h-2 overflow-hidden rounded-full bg-gray-200">
                                <div
                                    className="h-full rounded-full bg-indigo-600 transition-all"
                                    style={{ width: `${polling ? 100 : uploadProgress}%` }}
                                />
                            </div>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}

function ManualEntryFallback() {
    return (
        <div className="rounded-xl border border-amber-200 bg-amber-50 p-5">
            <div className="flex items-start gap-3">
                <TriangleAlert className="mt-0.5 h-5 w-5 text-amber-700" />
                <div>
                    <h3 className="text-base font-semibold text-amber-900">
                        Saisie manuelle requise
                    </h3>
                    <p className="mt-1 text-sm text-amber-800">
                        L'extraction automatique n'a pas pu extraire les données.
                        Veuillez saisir les informations manuellement.
                    </p>
                    <Link
                        href="/expenses/create"
                        className="mt-4 inline-flex rounded-lg bg-amber-700 px-4 py-2 text-sm font-medium text-white hover:bg-amber-800"
                    >
                        Aller au formulaire manuel
                    </Link>
                </div>
            </div>
        </div>
    );
}

function ExtractionReviewForm({
    suggestions,
    confirmedValues,
    touchedFields,
    setConfirmedValues,
    setTouchedFields,
    onSubmit,
    submitting,
}) {
    const lowConfidenceKeys = suggestions
        .filter((item) => Number(item.confidence) < 0.7)
        .map((item) => item.key);

    const unresolvedLowConfidence = lowConfidenceKeys.filter(
        (key) => !touchedFields[key]
    );

    const canSubmit = suggestions.length > 0 && unresolvedLowConfidence.length === 0 && !submitting;

    const handleChange = (key, value) => {
        setConfirmedValues((prev) => ({
            ...prev,
            [key]: value,
        }));
    };

    const handleConfirmInteraction = (key) => {
        setTouchedFields((prev) => ({
            ...prev,
            [key]: true,
        }));
    };

    return (
        <div className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div className="mb-5">
                <h3 className="text-base font-semibold text-gray-900">
                    Vérification de l’extraction
                </h3>
                <p className="mt-1 text-sm text-gray-500">
                    Vérifiez et confirmez chaque valeur avant de créer la dépense.
                </p>
            </div>

            {unresolvedLowConfidence.length > 0 && (
                <Alert variant="warning">
                    Certains champs ont une confiance faible. Vous devez les modifier ou les confirmer avant de continuer.
                </Alert>
            )}

            <div className="mt-4 space-y-4">
                {suggestions.map((item) => {
                    const isLow = Number(item.confidence) < 0.7;
                    const wasTouched = !!touchedFields[item.key];

                    return (
                        <div
                            key={item.key}
                            className={[
                                'rounded-xl border p-4',
                                isLow && !wasTouched
                                    ? 'border-amber-300 bg-amber-50'
                                    : 'border-gray-200 bg-white',
                            ].join(' ')}
                        >
                            <div className="mb-2 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <label className="text-sm font-medium text-gray-800">
                                    {labelForField(item.key)}
                                </label>
                                <div className="flex items-center gap-2">
                                    {isLow && !wasTouched && (
                                        <span className="inline-flex items-center gap-1 text-xs font-medium text-amber-800">
                                            <TriangleAlert className="h-4 w-4" />
                                            Vérification requise
                                        </span>
                                    )}
                                    <ConfidenceBadge confidence={item.confidence} />
                                </div>
                            </div>

                            <input
                                type={
                                    item.key.includes('date')
                                        ? 'date'
                                        : ['total_ht', 'total_vat', 'total_ttc'].includes(item.key)
                                        ? 'number'
                                        : 'text'
                                }
                                step={
                                    ['total_ht', 'total_vat', 'total_ttc'].includes(item.key)
                                        ? '0.01'
                                        : undefined
                                }
                                value={confirmedValues[item.key] ?? ''}
                                onChange={(e) => handleChange(item.key, e.target.value)}
                                onBlur={() => handleConfirmInteraction(item.key)}
                                className={[
                                    'w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2',
                                    isLow && !wasTouched
                                        ? 'border-amber-400 bg-amber-100 focus:border-amber-500 focus:ring-amber-200'
                                        : 'border-gray-300 bg-white focus:border-indigo-500 focus:ring-indigo-200',
                                ].join(' ')}
                            />

                            {isLow && !wasTouched && (
                                <p className="mt-2 text-xs text-amber-800">
                                    ⚠ Ce champ doit être vérifié manuellement avant soumission.
                                </p>
                            )}
                        </div>
                    );
                })}
            </div>

            <div className="mt-6">
                <button
                    type="button"
                    onClick={onSubmit}
                    disabled={!canSubmit}
                    className="inline-flex w-full items-center justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    Confirmer et créer la dépense
                </button>
            </div>
        </div>
    );
}

export default function Index() {
    const [documentType, setDocumentType] = useState('expense');
    const [selectedFile, setSelectedFile] = useState(null);
    const [previewUrl, setPreviewUrl] = useState('');
    const [uploadProgress, setUploadProgress] = useState(0);
    const [uploading, setUploading] = useState(false);

    const [docId, setDocId] = useState(null);
    const [polling, setPolling] = useState(false);
    const [pollStartedAt, setPollStartedAt] = useState(null);

    const [ocrStatus, setOcrStatus] = useState(null);
    const [failed, setFailed] = useState(false);

    const [suggestions, setSuggestions] = useState([]);
    const [confirmedValues, setConfirmedValues] = useState({});
    const [touchedFields, setTouchedFields] = useState({});
    const [submittingExpense, setSubmittingExpense] = useState(false);

    const normalizedSuggestions = useMemo(
        () => normalizeSuggestions(suggestions),
        [suggestions]
    );

    const allVeryLowConfidence =
        normalizedSuggestions.length > 0 &&
        normalizedSuggestions.every((item) => Number(item.confidence) < 0.5);

    useEffect(() => {
        if (!selectedFile || !selectedFile.type?.startsWith('image/')) {
            setPreviewUrl('');
            return;
        }

        const objectUrl = URL.createObjectURL(selectedFile);
        setPreviewUrl(objectUrl);

        return () => URL.revokeObjectURL(objectUrl);
    }, [selectedFile]);

    const startUpload = useCallback(
        async (file) => {
            setSelectedFile(file);
            setUploading(true);
            setUploadProgress(0);
            setDocId(null);
            setPolling(false);
            setPollStartedAt(null);
            setSuggestions([]);
            setConfirmedValues({});
            setTouchedFields({});
            setFailed(false);
            setOcrStatus(null);

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
                        const percent = Math.round((event.loaded * 100) / event.total);
                        setUploadProgress(percent);
                    },
                });

                const uploadedDocumentId =
                    response.data?.document_id ??
                    response.data?.id ??
                    response.data?.document?.id;

                if (uploadedDocumentId) {
                    setDocId(uploadedDocumentId);
                    setPolling(true);
                    setPollStartedAt(Date.now());
                    setOcrStatus('processing');
                }

                setUploadProgress(100);
            } catch (error) {
                setFailed(true);
                setPolling(false);
            } finally {
                setUploading(false);
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
            'image/*': ['.png', '.jpg', '.jpeg', '.webp'],
        },
        multiple: false,
        onDrop,
    });

    useEffect(() => {
        if (!polling || !docId) return;

        const maxDurationMs = 5 * 60 * 1000;

        const interval = setInterval(async () => {
            if (pollStartedAt && Date.now() - pollStartedAt >= maxDurationMs) {
                setPolling(false);
                setFailed(true);
                setOcrStatus('failed');
                clearInterval(interval);
                return;
            }

            try {
                const r = await axios.get(`/documents/${docId}/status`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });

                const status = r.data?.ocr_status;
                setOcrStatus(status);

                if (status === 'done') {
                    const nextSuggestions = r.data?.suggestions ?? [];
                    const normalized = normalizeSuggestions(nextSuggestions);

                    setSuggestions(nextSuggestions);
                    setConfirmedValues(
                        normalized.reduce((acc, item) => {
                            acc[item.key] = item.suggested_value ?? '';
                            return acc;
                        }, {})
                    );
                    setTouchedFields({});
                    setPolling(false);
                    clearInterval(interval);
                }

                if (status === 'failed') {
                    setPolling(false);
                    setFailed(true);
                    clearInterval(interval);
                }
            } catch (error) {
                setPolling(false);
                setFailed(true);
                clearInterval(interval);
            }
        }, 2000);

        return () => clearInterval(interval);
    }, [polling, docId, pollStartedAt]);

    const submitConfirmedExpense = async () => {
        setSubmittingExpense(true);

        try {
            await axios.post('/expenses', {
                ...confirmedValues,
                source_document_id: docId,
            });

            router.visit('/expenses');
        } catch (error) {
            setSubmittingExpense(false);
        }
    };

    return (
        <AuthenticatedLayout header="Documents">
            <Head title="Documents" />

            <div className="space-y-6">
                <div>
                    <h2 className="text-xl font-semibold text-gray-900">
                        Import de document
                    </h2>
                    <p className="text-sm text-gray-500">
                        Déposez un PDF ou une image pour lancer l’extraction OCR et IA.
                    </p>
                </div>

                <div className="grid gap-6 xl:grid-cols-2">
                    <div className="space-y-6">
                        <div className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                            <div className="mb-4 flex items-center justify-between gap-4">
                                <div>
                                    <h3 className="text-base font-semibold text-gray-900">
                                        Téléversement
                                    </h3>
                                    <p className="text-sm text-gray-500">
                                        Formats acceptés: PDF et images
                                    </p>
                                </div>

                                <select
                                    value={documentType}
                                    onChange={(e) => setDocumentType(e.target.value)}
                                    className="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200"
                                >
                                    <option value="expense">Dépense</option>
                                    <option value="invoice">Facture fournisseur</option>
                                    <option value="other">Autre</option>
                                </select>
                            </div>

                            <div
                                {...getRootProps()}
                                className={[
                                    'cursor-pointer rounded-xl border-2 border-dashed p-8 text-center transition',
                                    isDragActive
                                        ? 'border-indigo-500 bg-indigo-50'
                                        : 'border-gray-300 bg-gray-50 hover:border-indigo-400 hover:bg-indigo-50/50',
                                ].join(' ')}
                            >
                                <input {...getInputProps()} />
                                <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-white shadow-sm">
                                    {uploading || polling ? (
                                        <LoaderCircle className="h-6 w-6 animate-spin text-indigo-600" />
                                    ) : (
                                        <Upload className="h-6 w-6 text-indigo-600" />
                                    )}
                                </div>

                                <p className="mt-4 text-sm font-medium text-gray-900">
                                    {isDragActive
                                        ? 'Déposez le fichier ici'
                                        : 'Glissez-déposez un fichier ici ou cliquez pour sélectionner'}
                                </p>

                                <p className="mt-1 text-sm text-gray-500">
                                    PDF, PNG, JPG, JPEG, WEBP
                                </p>
                            </div>
                        </div>

                        <DocumentPreview
                            file={selectedFile}
                            previewUrl={previewUrl}
                            uploadProgress={uploadProgress}
                            polling={polling}
                            documentType={documentType}
                        />
                    </div>

                    <div className="space-y-6">
                        {uploading && (
                            <div className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                                <Spinner label="Téléversement du document..." />
                            </div>
                        )}

                        {polling && (
                            <div className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                                <Spinner label="Extraction OCR et analyse IA en cours..." />
                            </div>
                        )}

                        {!uploading && !polling && failed && <ManualEntryFallback />}

                        {!uploading && !polling && !failed && allVeryLowConfidence && (
                            <ManualEntryFallback />
                        )}

                        {!uploading &&
                            !polling &&
                            !failed &&
                            normalizedSuggestions.length > 0 &&
                            !allVeryLowConfidence && (
                                <ExtractionReviewForm
                                    suggestions={normalizedSuggestions}
                                    confirmedValues={confirmedValues}
                                    touchedFields={touchedFields}
                                    setConfirmedValues={setConfirmedValues}
                                    setTouchedFields={setTouchedFields}
                                    onSubmit={submitConfirmedExpense}
                                    submitting={submittingExpense}
                                />
                            )}

                        {!uploading &&
                            !polling &&
                            !failed &&
                            normalizedSuggestions.length === 0 &&
                            !selectedFile && (
                                <div className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                                    <div className="flex min-h-[240px] items-center justify-center text-center">
                                        <div>
                                            <ScanSearch className="mx-auto h-10 w-10 text-gray-400" />
                                            <p className="mt-3 text-sm font-medium text-gray-800">
                                                Les résultats d’extraction apparaîtront ici
                                            </p>
                                            <p className="mt-1 text-sm text-gray-500">
                                                Importez un document pour commencer l’analyse.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}