<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessDocumentOcr;
use App\Models\Account;
use App\Models\Contact;
use App\Models\Document;
use App\Models\Expense;
use App\Models\TaxRate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function index(Request $request): Response
    {
        $company = app('currentCompany');

        $documents = Document::query()
            ->where('company_id', $company->id)
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString()
            ->through(function (Document $doc) {
                return [
                    'id' => $doc->id,
                    'file_name' => $doc->file_name,
                    'mime_type' => $doc->mime_type,
                    'file_size_bytes' => $doc->file_size_bytes,
                    'document_type' => $doc->document_type,
                    'ocr_status' => $doc->ocr_status,
                    'ocr_error' => $doc->ocr_error,
                    'has_text' => ! empty($doc->ocr_raw_text),
                    'hints' => $doc->ocr_parsed_hints,
                    'created_at' => $doc->created_at?->toIso8601String(),
                ];
            });

        return Inertia::render('Documents/Index', [
            'documents' => $documents,
            'config' => [
                'max_size_kb' => (int) config('ocr.upload.max_size_kb'),
                'allowed_mimes' => (array) config('ocr.upload.allowed_mimes'),
            ],
        ]);
    }

    public function upload(Request $request): JsonResponse
    {
        $maxKb = (int) config('ocr.upload.max_size_kb', 20480);

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp,heic', 'max:'.$maxKb],
            'document_type' => ['required', 'in:supplier_bill,expense,invoice,bank_statement,other'],
        ]);

        $company = app('currentCompany');
        $file = $request->file('file');

        $key = 'documents/'.$company->id.'/'.$file->hashName();

        Storage::disk('local')->put(
            $key,
            file_get_contents($file->getRealPath())
        );

        $doc = Document::create([
            'company_id' => $company->id,
            'file_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_size_bytes' => $file->getSize(),
            'storage_key' => $key,
            'document_type' => $validated['document_type'],
            'source' => 'upload',
            'ocr_status' => 'pending',
            'retention_until' => now()->addYears(10)->toDateString(),
            'uploaded_by' => $request->user()?->id,
        ]);

        ProcessDocumentOcr::dispatch($doc->id)
            ->onQueue((string) config('ocr.processing.queue', 'ocr'));

        return response()->json([
            'document_id' => $doc->id,
            'status' => 'queued',
        ], 201);
    }

    public function show(Document $document): Response
    {
        $this->authorizeDocument($document);

        $linkedExpenses = Expense::query()
            ->where('company_id', $document->company_id)
            ->where('source_document_id', $document->id)
            ->with('contact:id,display_name')
            ->orderByDesc('created_at')
            ->get([
                'id',
                'reference',
                'expense_date',
                'total_ht',
                'total_vat',
                'total_ttc',
                'currency',
                'status',
                'contact_id',
            ])
            ->map(fn (Expense $e) => [
                'id' => $e->id,
                'reference' => $e->reference,
                'expense_date' => $e->expense_date?->toDateString(),
                'total_ht' => (float) $e->total_ht,
                'total_vat' => (float) $e->total_vat,
                'total_ttc' => (float) $e->total_ttc,
                'currency' => $e->currency,
                'status' => $e->status,
                'contact_name' => $e->contact?->display_name,
            ]);

        return Inertia::render('Documents/Show', [
            'document' => [
                'id' => $document->id,
                'file_name' => $document->file_name,
                'mime_type' => $document->mime_type,
                'file_size_bytes' => $document->file_size_bytes,
                'document_type' => $document->document_type,
                'source' => $document->source,
                'ocr_status' => $document->ocr_status,
                'ocr_error' => $document->ocr_error,
                'ocr_raw_text' => $document->ocr_raw_text,
                'ocr_parsed_hints' => $document->ocr_parsed_hints,
                'retention_until' => $document->retention_until,
                'created_at' => $document->created_at?->toIso8601String(),
                'updated_at' => $document->updated_at?->toIso8601String(),
                'is_image' => str_starts_with((string) $document->mime_type, 'image/'),
                'is_pdf' => $document->mime_type === 'application/pdf',
            ],
            'linkedExpenses' => $linkedExpenses,
        ]);
    }

    public function status(Document $document): JsonResponse
    {
        $this->authorizeDocument($document);

        return response()->json([
            'id' => $document->id,
            'file_name' => $document->file_name,
            'ocr_status' => $document->ocr_status,
            'ocr_error' => $document->ocr_error,
            'ocr_raw_text' => $document->ocr_raw_text,
            'hints' => $document->ocr_parsed_hints,
        ]);
    }

    public function download(Document $document): StreamedResponse
    {
        $this->authorizeDocument($document);

        $disk = Storage::disk('local');

        abort_unless($disk->exists($document->storage_key), 404);

        $stream = $disk->readStream($document->storage_key);
        $mime = $document->mime_type ?: 'application/octet-stream';

        return response()->streamDownload(
            function () use ($stream) {
                if (is_resource($stream)) {
                    fpassthru($stream);
                    fclose($stream);
                }
            },
            $document->file_name,
            ['Content-Type' => $mime]
        );
    }

    public function retry(Document $document): JsonResponse
    {
        $this->authorizeDocument($document);

        abort_if(
            ! in_array($document->ocr_status, ['failed', 'done'], true),
            422,
            'Impossible de relancer l\'OCR pour le statut actuel.'
        );

        $document->update([
            'ocr_status' => 'pending',
            'ocr_error' => null,
        ]);

        ProcessDocumentOcr::dispatch($document->id)
            ->onQueue((string) config('ocr.processing.queue', 'ocr'));

        return response()->json([
            'document_id' => $document->id,
            'status' => 'queued',
        ]);
    }

    public function useInExpense(Document $document): RedirectResponse
    {
        $this->authorizeDocument($document);

        $hints = (array) ($document->ocr_parsed_hints ?? []);
        $company = app('currentCompany');

        $taxRateId = $this->resolveTaxRateId($company->id, $hints['tva_rate'] ?? null);
        $accountId = $this->resolveAccountId($company->id, $hints['account_code_hint'] ?? null);
        $contactId = $this->resolveContactId(
            $company->id,
            $hints['vendor_name'] ?? null,
            $hints['vendor_nif'] ?? null,
            $hints['vendor_nis'] ?? null,
            $hints['vendor_rc'] ?? null,
        );

        return redirect()->route('expenses.create', array_filter([
            'from_document' => $document->id,
            'vendor_name' => $hints['vendor_name'] ?? null,
            'reference' => $hints['reference'] ?? null,
            'expense_date' => $hints['document_date'] ?? null,
            'total_ht' => $hints['total_ht'] ?? null,
            'total_vat' => $hints['total_vat'] ?? null,
            'total_ttc' => $hints['total_ttc'] ?? null,
            'currency' => $hints['currency'] ?? null,
            'payment_method' => $hints['payment_method'] ?? null,
            'tax_rate_id' => $taxRateId,
            'expense_account_id' => $accountId,
            'contact_id' => $contactId,
        ], fn ($v) => $v !== null && $v !== ''));
    }

    private function resolveTaxRateId(string $companyId, mixed $rate): ?string
    {
        if (! is_numeric($rate)) {
            return null;
        }

        return TaxRate::query()
            ->forCompany($companyId)
            ->active()
            ->whereRaw('ROUND(rate_percent, 2) = ?', [round((float) $rate, 2)])
            ->value('id');
    }

    private function resolveAccountId(string $companyId, ?string $code): ?string
    {
        if (! $code) {
            return null;
        }

        return Account::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where('class', 6)
            ->where('code', $code)
            ->value('id');
    }

    private function resolveContactId(
        string $companyId,
        ?string $vendorName,
        ?string $nif,
        ?string $nis,
        ?string $rc,
    ): ?string {
        foreach ([['nif', $nif], ['nis', $nis], ['rc', $rc]] as [$col, $val]) {
            if (! empty($val)) {
                $id = Contact::query()
                    ->where('company_id', $companyId)
                    ->where('is_active', true)
                    ->whereIn('type', ['supplier', 'both'])
                    ->where($col, $val)
                    ->value('id');

                if ($id) {
                    return $id;
                }
            }
        }

        if (! $vendorName) {
            return null;
        }

        $needle = mb_strtolower(trim($vendorName));
        if ($needle === '') {
            return null;
        }

        $suppliers = Contact::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->whereIn('type', ['supplier', 'both'])
            ->get(['id', 'display_name', 'raison_sociale']);

        $best = null;
        $bestScore = 0;

        foreach ($suppliers as $contact) {
            foreach ([$contact->display_name, $contact->raison_sociale] as $candidate) {
                if (! $candidate) {
                    continue;
                }

                $cand = mb_strtolower($candidate);

                if (str_contains($cand, $needle) || str_contains($needle, $cand)) {
                    $score = 90;
                } else {
                    similar_text($needle, $cand, $percent);
                    $score = (int) round($percent);
                }

                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best = $contact->id;
                }
            }
        }

        return $bestScore >= 75 ? $best : null;
    }

    private function authorizeDocument(Document $document): void
    {
        abort_if(
            $document->company_id !== app('currentCompany')->id,
            403,
            'Accès non autorisé à ce document.'
        );
    }
}
