import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import axios from 'axios';
import { Upload, X, Landmark, FileSpreadsheet, FileText } from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

function ImportModal({ open, onClose, bankAccounts }) {
    const [step, setStep] = useState('upload');
    const [uploading, setUploading] = useState(false);
    const [importId, setImportId] = useState(null);
    const [headers, setHeaders] = useState([]);
    const [sampleRow, setSampleRow] = useState({});
    const [suggestedMapping, setSuggestedMapping] = useState(null);

    const uploadForm = useForm({
        bank_account_id: '',
        import_type: 'csv',
        file: null,
    });

    const mappingForm = useForm({
        import_id: '',
        date_column: '',
        label_column: '',
        debit_column: '',
        credit_column: '',
        balance_column: '',
    });

    const canConfirmMapping = useMemo(() => {
        return (
            mappingForm.data.import_id &&
            mappingForm.data.date_column &&
            mappingForm.data.label_column &&
            mappingForm.data.debit_column &&
            mappingForm.data.credit_column
        );
    }, [mappingForm.data]);

    const submitUpload = async (e) => {
        e.preventDefault();
        setUploading(true);

        const formData = new FormData();
        formData.append('bank_account_id', uploadForm.data.bank_account_id);
        formData.append('import_type', uploadForm.data.import_type);
        formData.append('file', uploadForm.data.file);

        try {
            const response = await axios.post('/bank/import', formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });

            if (uploadForm.data.import_type === 'pdf_ocr') {
                onClose();
                router.visit('/bank');
                return;
            }

            setImportId(response.data.import_id);
            setHeaders(response.data.headers || []);
            setSampleRow(response.data.sample_row || {});
            setSuggestedMapping(response.data.suggested_mapping || {});

            mappingForm.setData({
                import_id: response.data.import_id,
                date_column: response.data.suggested_mapping?.date || '',
                label_column: response.data.suggested_mapping?.label || '',
                debit_column: response.data.suggested_mapping?.debit || '',
                credit_column: response.data.suggested_mapping?.credit || '',
                balance_column: response.data.suggested_mapping?.balance || '',
            });

            setStep('mapping');
        } finally {
            setUploading(false);
        }
    };

    const confirmImport = (e) => {
        e.preventDefault();
        mappingForm.post('/bank/import/confirm', {
            onSuccess: () => onClose(),
        });
    };

    if (!open) return null;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div className="w-full max-w-3xl rounded-2xl bg-white shadow-xl">
                <div className="flex items-center justify-between border-b px-6 py-4">
                    <h2 className="text-lg font-semibold text-slate-900">Importer un relevé bancaire</h2>
                    <button onClick={onClose} className="rounded-lg p-2 hover:bg-slate-100">
                        <X className="h-5 w-5" />
                    </button>
                </div>

                {step === 'upload' && (
                    <form onSubmit={submitUpload} className="space-y-5 p-6">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">
                                Compte bancaire
                            </label>
                            <select
                                value={uploadForm.data.bank_account_id}
                                onChange={(e) => uploadForm.setData('bank_account_id', e.target.value)}
                                className="w-full rounded-xl border border-slate-300 px-3 py-2"
                            >
                                <option value="">Sélectionner</option>
                                {bankAccounts.map((account) => (
                                    <option key={account.id} value={account.id}>
                                        {account.bank_name} — {account.account_number}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">
                                Type d'import
                            </label>
                            <select
                                value={uploadForm.data.import_type}
                                onChange={(e) => uploadForm.setData('import_type', e.target.value)}
                                className="w-full rounded-xl border border-slate-300 px-3 py-2"
                            >
                                <option value="csv">CSV</option>
                                <option value="excel">Excel</option>
                                <option value="pdf_ocr">PDF OCR</option>
                            </select>
                        </div>

                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">
                                Fichier
                            </label>
                            <label className="flex min-h-36 cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-300 bg-slate-50 p-6 text-center">
                                <Upload className="mb-3 h-8 w-8 text-slate-500" />
                                <span className="text-sm text-slate-700">
                                    Glissez le fichier ici ou cliquez pour choisir
                                </span>
                                <span className="mt-1 text-xs text-slate-500">
                                    CSV, XLSX ou PDF — max 10 MB
                                </span>
                                <input
                                    type="file"
                                    className="hidden"
                                    accept=".csv,.xlsx,.pdf"
                                    onChange={(e) => uploadForm.setData('file', e.target.files?.[0] || null)}
                                />
                            </label>
                            {uploadForm.data.file && (
                                <p className="mt-2 text-sm text-slate-600">{uploadForm.data.file.name}</p>
                            )}
                        </div>

                        <div className="flex justify-end gap-3">
                            <button
                                type="button"
                                onClick={onClose}
                                className="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700"
                            >
                                Annuler
                            </button>
                            <button
                                type="submit"
                                disabled={uploading}
                                className="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                            >
                                {uploading ? 'Analyse en cours...' : 'Analyser le fichier'}
                            </button>
                        </div>
                    </form>
                )}

                {step === 'mapping' && (
                    <form onSubmit={confirmImport} className="space-y-6 p-6">
                        <div>
                            <h3 className="text-base font-semibold text-slate-900">
                                Vérification du mapping des colonnes
                            </h3>
                            <p className="mt-1 text-sm text-slate-600">
                                Les colonnes proposées par l’IA doivent être confirmées avant import.
                            </p>
                        </div>

                        <div className="grid gap-4 md:grid-cols-2">
                            {[
                                ['date_column', 'Colonne date'],
                                ['label_column', 'Colonne libellé'],
                                ['debit_column', 'Colonne débit'],
                                ['credit_column', 'Colonne crédit'],
                                ['balance_column', 'Colonne solde (optionnelle)'],
                            ].map(([key, label]) => (
                                <div key={key}>
                                    <label className="mb-1 block text-sm font-medium text-slate-700">
                                        {label}
                                    </label>
                                    <select
                                        value={mappingForm.data[key]}
                                        onChange={(e) => mappingForm.setData(key, e.target.value)}
                                        className="w-full rounded-xl border border-slate-300 px-3 py-2"
                                    >
                                        <option value="">Sélectionner</option>
                                        {headers.map((header) => (
                                            <option key={header} value={header}>
                                                {header}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            ))}
                        </div>

                        <div className="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <p className="mb-2 text-sm font-medium text-slate-800">Exemple de ligne</p>
                            <div className="space-y-1 text-xs text-slate-600">
                                {headers.map((header) => (
                                    <div key={header}>
                                        <span className="font-medium text-slate-800">{header}:</span>{' '}
                                        {String(sampleRow[header] ?? '')}
                                    </div>
                                ))}
                            </div>
                        </div>

                        <div className="flex justify-end gap-3">
                            <button
                                type="button"
                                onClick={onClose}
                                className="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700"
                            >
                                Annuler
                            </button>
                            <button
                                type="submit"
                                disabled={!canConfirmMapping || mappingForm.processing}
                                className="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                            >
                                Confirmer l'import
                            </button>
                        </div>
                    </form>
                )}
            </div>
        </div>
    );
}

export default function Index({ bankAccounts, recentImports }) {
    const [open, setOpen] = useState(false);

    return (
        <AuthenticatedLayout>
            <Head title="Banque" />

            <div className="space-y-8">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-slate-900">Comptes bancaires</h1>
                        <p className="mt-1 text-sm text-slate-600">
                            Gérez les imports de relevés et préparez le rapprochement.
                        </p>
                    </div>

                    <button
                        onClick={() => setOpen(true)}
                        className="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white"
                    >
                        <Upload className="h-4 w-4" />
                        Importer un relevé
                    </button>
                </div>

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {bankAccounts.map((account) => (
                        <div key={account.id} className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <div className="flex items-start gap-3">
                                <div className="rounded-xl bg-indigo-50 p-3 text-indigo-700">
                                    <Landmark className="h-5 w-5" />
                                </div>
                                <div className="min-w-0">
                                    <h2 className="truncate font-semibold text-slate-900">{account.bank_name}</h2>
                                    <p className="text-sm text-slate-600">{account.account_number}</p>
                                    <p className="mt-2 text-sm text-slate-500">
                                        Compte GL : {account.gl_account?.code ?? '—'} {account.gl_account?.label ?? ''}
                                    </p>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>

                <div className="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b px-5 py-4">
                        <h2 className="font-semibold text-slate-900">Imports récents</h2>
                    </div>
                    <div className="divide-y">
                        {recentImports.length === 0 && (
                            <div className="px-5 py-8 text-sm text-slate-500">
                                Aucun import pour le moment.
                            </div>
                        )}

                        {recentImports.map((item) => (
                            <div key={item.id} className="flex items-center justify-between px-5 py-4">
                                <div className="min-w-0">
                                    <p className="font-medium text-slate-900">{item.file_name}</p>
                                    <p className="text-sm text-slate-500">
                                        {item.bank_account?.bank_name ?? 'Compte bancaire'} · {item.import_type}
                                    </p>
                                </div>
                                <div className="text-right text-sm text-slate-500">
                                    <p>{item.status}</p>
                                    <p>{item.row_count} lignes</p>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </div>

            <ImportModal
                open={open}
                onClose={() => setOpen(false)}
                bankAccounts={bankAccounts}
            />
        </AuthenticatedLayout>
    );
}
