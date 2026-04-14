<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\BankStatementImport;
use App\Models\BankTransaction;
use App\Models\Document;
use App\Services\AiExtractionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use League\Csv\Reader;
use Maatwebsite\Excel\Facades\Excel;
use App\Jobs\ProcessDocumentOcr;

class BankController extends Controller
{
    public function index(Request $request): Response
    {
        $companyId = app('currentCompany')->id;

        $bankAccounts = BankAccount::query()
            ->where('company_id', $companyId)
            ->with('glAccount:id,code,label')
            ->orderBy('bank_name')
            ->get();

        $recentImports = BankStatementImport::query()
            ->where('company_id', $companyId)
            ->with('bankAccount:id,bank_name,account_number')
            ->latest()
            ->limit(20)
            ->get();

        return Inertia::render('Bank/Index', [
            'bankAccounts' => $bankAccounts,
            'recentImports' => $recentImports,
        ]);
    }

    public function import(Request $request, AiExtractionService $ai): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'bank_account_id' => ['required', 'uuid'],
            'file' => ['required', 'file', 'mimes:csv,xlsx,pdf', 'max:10240'],
            'import_type' => ['required', 'in:csv,excel,pdf_ocr'],
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date'],
        ]);

        $companyId = app('currentCompany')->id;

        $bankAccount = BankAccount::query()
            ->where('company_id', $companyId)
            ->findOrFail($validated['bank_account_id']);

        $storedPath = $request->file('file')->store("bank-imports/{$companyId}", 'local');

        $import = BankStatementImport::query()->create([
            'id' => (string) Str::uuid(),
            'company_id' => $companyId,
            'bank_account_id' => $bankAccount->id,
            'import_type' => $validated['import_type'],
            'file_name' => $request->file('file')->getClientOriginalName(),
            'file_path' => $storedPath,
            'mime_type' => $request->file('file')->getMimeType(),
            'period_start' => $validated['period_start'] ?? null,
            'period_end' => $validated['period_end'] ?? null,
            'row_count' => 0,
            'status' => 'uploaded',
            'meta' => null,
        ]);

        if ($validated['import_type'] === 'pdf_ocr') {
            $document = Document::query()->create([
                'id' => (string) Str::uuid(),
                'company_id' => $companyId,
                'document_type' => 'bank_statement',
                'original_name' => $request->file('file')->getClientOriginalName(),
                'mime_type' => $request->file('file')->getMimeType(),
                'storage_disk' => 'local',
                'storage_key' => $storedPath,
                'ocr_status' => 'queued',
                'retention_until' => now()->addYears(10),
            ]);

            $import->update([
                'document_id' => $document->id,
                'status' => 'processing',
            ]);

            ProcessDocumentOcr::dispatch($document->id);

            return response()->json([
                'import_id' => $import->id,
                'status' => 'processing',
                'message' => 'Le relevé PDF a été envoyé pour extraction OCR.',
            ]);
        }

        [$headers, $sampleRow] = $this->extractHeadersAndSampleRow(
            $storedPath,
            $validated['import_type']
        );

        $mapping = $ai->mapBankCsvColumns($headers, $sampleRow);

        $import->update([
            'status' => 'awaiting_mapping',
            'meta' => [
                'headers' => $headers,
                'sample_row' => $sampleRow,
                'suggested_mapping' => $mapping,
            ],
        ]);

        return response()->json([
            'import_id' => $import->id,
            'headers' => $headers,
            'sample_row' => $sampleRow,
            'suggested_mapping' => $mapping,
        ]);
    }

    public function confirmImport(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'import_id' => ['required', 'uuid'],
            'date_column' => ['required', 'string'],
            'label_column' => ['required', 'string'],
            'debit_column' => ['required', 'string'],
            'credit_column' => ['required', 'string'],
            'balance_column' => ['nullable', 'string'],
        ]);

        $companyId = app('currentCompany')->id;

        $import = BankStatementImport::query()
            ->where('company_id', $companyId)
            ->findOrFail($validated['import_id']);

        $rows = $this->readNormalizedRows($import->file_path, $import->import_type);

        $rowCount = 0;
        $warnings = [];

        DB::transaction(function () use ($rows, $validated, $import, $companyId, &$rowCount, &$warnings) {
            foreach ($rows as $index => $row) {
                $dateValue = $row[$validated['date_column']] ?? null;
                $labelValue = $row[$validated['label_column']] ?? null;
                $debitValue = $row[$validated['debit_column']] ?? null;
                $creditValue = $row[$validated['credit_column']] ?? null;
                $balanceValue = $validated['balance_column']
                    ? ($row[$validated['balance_column']] ?? null)
                    : null;

                $debit = $this->parseMoney($debitValue);
                $credit = $this->parseMoney($creditValue);

                if ($debit > 0 && $credit > 0) {
                    $warnings[] = "Ligne {$index}: débit et crédit présents en même temps.";
                    continue;
                }

                if ($debit <= 0 && $credit <= 0) {
                    continue;
                }

                $direction = $debit > 0 ? 'debit' : 'credit';
                $amount = $debit > 0 ? $debit : $credit;

                BankTransaction::query()->create([
                    'id' => (string) Str::uuid(),
                    'company_id' => $companyId,
                    'bank_account_id' => $import->bank_account_id,
                    'statement_import_id' => $import->id,
                    'transaction_date' => $this->parseDate($dateValue),
                    'label' => trim((string) $labelValue),
                    'amount' => $amount,
                    'direction' => $direction,
                    'balance_after' => $balanceValue !== null && $balanceValue !== ''
                        ? $this->parseMoney($balanceValue)
                        : null,
                    'reconcile_status' => 'unmatched',
                    'meta' => [
                        'source_row' => $index,
                    ],
                ]);

                $rowCount++;
            }

            $import->update([
                'row_count' => $rowCount,
                'status' => 'imported',
                'meta' => array_merge($import->meta ?? [], [
                    'warnings' => $warnings,
                    'confirmed_mapping' => [
                        'date' => $validated['date_column'],
                        'label' => $validated['label_column'],
                        'debit' => $validated['debit_column'],
                        'credit' => $validated['credit_column'],
                        'balance' => $validated['balance_column'] ?? null,
                    ],
                ]),
            ]);
        });

        return redirect('/bank/reconcile')->with(
            'success',
            "Import bancaire terminé ({$rowCount} lignes importées)."
        );
    }

    protected function extractHeadersAndSampleRow(string $path, string $importType): array
    {
        $rows = $this->readNormalizedRows($path, $importType, limit: 2);

        $headers = array_keys($rows[0] ?? []);
        $sampleRow = $rows[0] ?? [];

        return [$headers, $sampleRow];
    }

    protected function readNormalizedRows(string $path, string $importType, ?int $limit = null): array
    {
        return match ($importType) {
            'csv' => $this->readCsvRows($path, $limit),
            'excel' => $this->readExcelRows($path, $limit),
            default => [],
        };
    }

    protected function readCsvRows(string $path, ?int $limit = null): array
    {
        $absolutePath = Storage::disk('local')->path($path);
        $delimiter = $this->detectCsvDelimiter($absolutePath);

        $reader = Reader::createFromPath($absolutePath, 'r');
        $reader->setDelimiter($delimiter);
        $reader->setHeaderOffset(0);

        $records = iterator_to_array($reader->getRecords());
        $rows = array_map(fn ($row) => $this->normalizeArrayKeys($row), $records);

        return $limit ? array_slice($rows, 0, $limit) : $rows;
    }

    protected function readExcelRows(string $path, ?int $limit = null): array
    {
        $absolutePath = Storage::disk('local')->path($path);
        $sheets = Excel::toArray([], $absolutePath);
        $sheet = $sheets[0] ?? [];

        if (count($sheet) < 2) {
            return [];
        }

        $headers = array_map(fn ($value) => trim((string) $value), $sheet[0]);
        $rows = [];

        foreach (array_slice($sheet, 1) as $row) {
            $assoc = [];
            foreach ($headers as $index => $header) {
                if ($header === '') {
                    continue;
                }
                $assoc[$header] = $row[$index] ?? null;
            }
            $rows[] = $this->normalizeArrayKeys($assoc);
        }

        return $limit ? array_slice($rows, 0, $limit) : $rows;
    }

    protected function detectCsvDelimiter(string $absolutePath): string
    {
        $lines = file($absolutePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $sample = array_slice($lines ?: [], 0, 5);

        $commaScore = 0;
        $semicolonScore = 0;

        foreach ($sample as $line) {
            $commaScore += substr_count($line, ',');
            $semicolonScore += substr_count($line, ';');
        }

        return $semicolonScore > $commaScore ? ';' : ',';
    }

    protected function normalizeArrayKeys(array $row): array
    {
        $normalized = [];
        foreach ($row as $key => $value) {
            $normalized[trim((string) $key)] = $value;
        }

        return $normalized;
    }

    protected function parseMoney(mixed $value): float
    {
        if ($value === null) {
            return 0.0;
        }

        $string = trim((string) $value);

        if ($string === '') {
            return 0.0;
        }

        $string = str_replace(["\xc2\xa0", ' '], '', $string);
        $string = str_replace('.', '', $string);
        $string = str_replace(',', '.', $string);

        return round(abs((float) $string), 2);
    }

    protected function parseDate(mixed $value): string
    {
        return \Carbon\Carbon::parse($value)->toDateString();
    }
}
