<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessDocumentOcr;
use App\Models\AiSuggestion;
use App\Models\Document;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{

    public function index()
{
    return inertia('Documents/Index');
}


    // Note: cette route reste protégée par le middleware CSRF.
    // Les téléversements multipart doivent transmettre le jeton _token.
    public function upload(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,heic', 'max:20480'],
            'document_type' => ['required', 'in:supplier_bill,bank_statement,other'],
        ]);

        $company = app('currentCompany');
        $file = $request->file('file');

        $key = 'documents/' . $company->id . '/' . $file->hashName();

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

        ProcessDocumentOcr::dispatch($doc->id)->onQueue('ocr');

        return response()->json([
            'document_id' => $doc->id,
            'status' => 'queued',
        ], 201);
    }

    public function status(Document $document): JsonResponse
    {
        abort_if($document->company_id !== app('currentCompany')->id, 403);

        $suggestions = AiSuggestion::forSource('document', $document->id)->get();

        return response()->json([
            'ocr_status' => $document->ocr_status,
            'suggestions' => $suggestions,
        ]);
    }

    public function applySuggestion(Request $request, Document $document): JsonResponse
    {
        abort_if($document->company_id !== app('currentCompany')->id, 403);

        $validated = $request->validate([
            'suggestion_id' => ['required', 'uuid'],
            'final_value' => ['required', 'string'],
        ]);

        $suggestion = AiSuggestion::forSource('document', $document->id)
            ->where('id', $validated['suggestion_id'])
            ->firstOrFail();

        $suggestion->update([
            'accepted' => true,
            'final_value' => $validated['final_value'],
        ]);

        return response()->json([
            'ok' => true,
        ]);
    }
}