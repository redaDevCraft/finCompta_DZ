import { Head, useForm, router } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import axios from 'axios';
import { Upload, ArrowRight, FileText, Landmark } from 'lucide-react';

export default function BankImport({ bankAccounts, fromDocument }) {
    const [step, setStep] = useState('upload');
    const [uploading, setUploading] = useState(false);
    const [headers, setHeaders] = useState([]);
    const [sampleRow, setSampleRow] = useState({});
    const [suggestedMapping, setSuggestedMapping] = useState(null);

    const uploadForm = useForm({
        bank_account_id: bankAccounts?.[0]?.id ?? '',
        import_type: 'csv',
        file: null,
        period_start: '',
        period_end: '',
    });

    const mappingForm = useForm({
        import_id: '',
        date_column: '',
        label_column: '',
        debit_column: '',
        credit_column: '',
        balance_column: '',
    });

    const submitUpload = async (e) => {
        e.preventDefault();
        setUploading(true);
        const fd = new FormData();
        Object.entries(uploadForm.data).forEach(([k, v]) => v && fd.append(k, v));

        try {
            const res = await axios.post('/bank/import', fd, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });

            if (uploadForm.data.import_type === 'pdf_ocr') {
                router.visit('/bank');
                return;
            }

            mappingForm.setData('import_id', res.data.import_id);
            setHeaders(res.data.headers ?? []);
            setSampleRow(res.data.sample_row ?? {});
            setSuggestedMapping(res.data.suggested_mapping ?? null);
            setStep('mapping');
        } catch (err) {
            uploadForm.setError('file', err.response?.data?.message ?? 'Erreur lors de l\'import.');
        } finally {
            setUploading(false);
        }
    };

    const submitMapping = (e) => {
        e.preventDefault();
        mappingForm.post('/bank/import/confirm');
    };

    const inputCls = 'w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500';
    const labelCls = 'block text-xs font-medium text-slate-600 mb-1';

    return (
        <AuthenticatedLayout>
            <Head title="Importer un relevé bancaire" />
            <div className="mx-auto max-w-2xl px-4 py-8">

                {/* Header */}
                <div className="mb-6">
                    <h1 className="text-xl font-bold text-slate-800">Importer un relevé bancaire</h1>
                    <p className="mt-1 text-sm text-slate-500">
                        Importez un fichier CSV, Excel ou PDF pour alimenter votre trésorerie.
                    </p>
                </div>

                {/* From-document banner */}
                {fromDocument && (
                    <div className="mb-6 flex items-start gap-3 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3">
                        <FileText className="mt-0.5 h-4 w-4 shrink-0 text-sky-600" />
                        <div>
                            <p className="text-sm font-medium text-sky-800">
                                Document OCR détecté : {fromDocument.file_name}
                            </p>
                            <p className="text-xs text-sky-600 mt-0.5">
                                Sélectionnez le compte bancaire correspondant, puis importez.
                            </p>
                        </div>
                    </div>
                )}

                {step === 'upload' && (
                    <form onSubmit={submitUpload} className="space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">

                        {/* Bank account */}
                        <div>
                            <label className={labelCls}>Compte bancaire *</label>
                            {bankAccounts.length === 0 ? (
                                <div className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                                    Aucun compte bancaire configuré.{' '}
                                    <a href="/settings/bank-accounts" className="font-semibold underline">
                                        Créer un compte
                                    </a>
                                </div>
                            ) : (
                                <select
                                    value={uploadForm.data.bank_account_id}
                                    onChange={e => uploadForm.setData('bank_account_id', e.target.value)}
                                    className={inputCls}
                                    required
                                >
                                    {bankAccounts.map(acc => (
                                        <option key={acc.id} value={acc.id}>
                                            {acc.bank_name} — {acc.account_number ?? 'N/A'}
                                        </option>
                                    ))}
                                </select>
                            )}
                        </div>

                        {/* Import type */}
                        <div>
                            <label className={labelCls}>Format du fichier *</label>
                            <div className="grid grid-cols-3 gap-2">
                                {[
                                    { val: 'csv',     label: 'CSV' },
                                    { val: 'excel',   label: 'Excel (.xlsx)' },
                                    { val: 'pdf_ocr', label: 'PDF (OCR)' },
                                ].map(({ val, label }) => (
                                    <button
                                        key={val}
                                        type="button"
                                        onClick={() => uploadForm.setData('import_type', val)}
                                        className={`rounded-xl border px-3 py-2 text-sm font-medium transition-colors ${
                                            uploadForm.data.import_type === val
                                                ? 'border-indigo-500 bg-indigo-50 text-indigo-700'
                                                : 'border-slate-200 text-slate-600 hover:border-slate-300'
                                        }`}
                                    >
                                        {label}
                                    </button>
                                ))}
                            </div>
                        </div>

                        {/* Period */}
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <label className={labelCls}>Date début</label>
                                <input type="date" className={inputCls}
                                    value={uploadForm.data.period_start}
                                    onChange={e => uploadForm.setData('period_start', e.target.value)} />
                            </div>
                            <div>
                                <label className={labelCls}>Date fin</label>
                                <input type="date" className={inputCls}
                                    value={uploadForm.data.period_end}
                                    onChange={e => uploadForm.setData('period_end', e.target.value)} />
                            </div>
                        </div>

                        {/* File */}
                        <div>
                            <label className={labelCls}>Fichier *</label>
                            <input
                                type="file"
                                accept=".csv,.xlsx,.xls,.pdf,.png,.jpg,.jpeg,.webp,.heic,.tiff,.bmp"
                                onChange={e => uploadForm.setData('file', e.target.files[0])}
                                className={inputCls}
                                required
                            />
                            {uploadForm.errors.file && (
                                <p className="mt-1 text-xs text-red-600">{uploadForm.errors.file}</p>
                            )}
                        </div>

                        <button
                            type="submit"
                            disabled={uploading || bankAccounts.length === 0}
                            className="flex w-full items-center justify-center gap-2 rounded-xl bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-sky-700 disabled:opacity-50"
                        >
                            {uploading ? 'Chargement...' : <><Upload className="h-4 w-4" /> Importer</>}
                        </button>
                    </form>
                )}

                {step === 'mapping' && (
                    <form onSubmit={submitMapping} className="space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h2 className="font-semibold text-slate-800">Associer les colonnes</h2>

                        {/* Sample row preview */}
                        {Object.keys(sampleRow).length > 0 && (
                            <div className="overflow-x-auto rounded-xl border border-slate-100 bg-slate-50 p-3">
                                <p className="mb-2 text-xs font-medium text-slate-500">Aperçu première ligne :</p>
                                <div className="flex gap-3 text-xs">
                                    {Object.entries(sampleRow).map(([k, v]) => (
                                        <div key={k} className="min-w-0">
                                            <div className="font-semibold text-slate-600">{k}</div>
                                            <div className="truncate text-slate-400">{String(v ?? '')}</div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}

                        {[
                            { field: 'date_column',    label: 'Colonne Date *',    required: true },
                            { field: 'label_column',   label: 'Colonne Libellé *', required: true },
                            { field: 'debit_column',   label: 'Colonne Débit *',   required: true },
                            { field: 'credit_column',  label: 'Colonne Crédit *',  required: true },
                            { field: 'balance_column', label: 'Colonne Solde',     required: false },
                        ].map(({ field, label, required }) => (
                            <div key={field}>
                                <label className={labelCls}>{label}</label>
                                <select
                                    value={mappingForm.data[field]}
                                    onChange={e => mappingForm.setData(field, e.target.value)}
                                    className={inputCls}
                                    required={required}
                                >
                                    <option value="">— Choisir une colonne —</option>
                                    {headers.map(h => (
                                        <option key={h} value={h}
                                            selected={suggestedMapping?.[field.replace('_column','')] === h}>
                                            {h}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        ))}

                        <div className="flex gap-3">
                            <button type="button" onClick={() => setStep('upload')}
                                className="flex-1 rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50">
                                Retour
                            </button>
                            <button type="submit" disabled={mappingForm.processing}
                                className="flex flex-1 items-center justify-center gap-2 rounded-xl bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-sky-700 disabled:opacity-50">
                                Confirmer et importer
                                <ArrowRight className="h-4 w-4" />
                            </button>
                        </div>
                    </form>
                )}
            </div>
        </AuthenticatedLayout>
    );
}