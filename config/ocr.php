<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OCR Configuration
    |--------------------------------------------------------------------------
    |
    | Central configuration for the OCR pipeline. The pipeline is fully local
    | and does NOT rely on any AI provider. It supports:
    |   - Images (jpg/jpeg/png/webp/heic) via Tesseract
    |   - PDFs via pdftoppm (Poppler) + Tesseract, with optional ocrmypdf
    |
    | All paths can be absolute (recommended on Windows) or simple binary
    | names if the tool is on PATH.
    |
    */

    'tesseract' => [
        'bin' => env('TESSERACT_PATH', 'tesseract'),

        // Tesseract language codes, combined with '+'.
        // Example: "fra+ara+eng" for French + Arabic + English.
        'lang' => env('TESSERACT_LANG', 'fra+ara+eng'),

        // OCR Engine Mode:
        // 0 = legacy only, 1 = LSTM only, 2 = legacy + LSTM, 3 = default
        'oem' => (int) env('TESSERACT_OEM', 1),

        // Page Segmentation Mode:
        // 3 = fully automatic page segmentation (default, best for invoices)
        // 6 = assume a single uniform block of text
        'psm' => (int) env('TESSERACT_PSM', 3),

        /*
        | Optional second pass (images + rasterized PDF pages only) when the
        | primary OCR has almost no Arabic script — helps DZ bilingual invoices
        | where the Arabic footer (HT / TVA / TTC) was missed with fra+ara+eng.
        */
        'arabic_boost_pass' => (bool) env('TESSERACT_ARABIC_BOOST', false),
        'arabic_boost_lang' => env('TESSERACT_ARABIC_BOOST_LANG', 'ara+fra+eng'),
        'arabic_boost_psm' => (int) env('TESSERACT_ARABIC_BOOST_PSM', 4),
        /** Minimum Arabic script ratio (0–1) on primary text to skip boost */
        'arabic_boost_min_primary_ratio' => (float) env('TESSERACT_ARABIC_BOOST_MIN_RATIO', 0.06),

        // Maximum seconds a single Tesseract call may run.
        'timeout' => (int) env('TESSERACT_TIMEOUT', 90),

        // Extra Tesseract `-c key=value` pairs (helps mixed Arabic/Latin invoices).
        'config' => [
            'preserve_interword_spaces' => '1',
        ],
    ],

    'pdf' => [
        // Poppler's `pdftoppm` binary. Needed to rasterize scanned PDFs
        // before feeding them to Tesseract. On Windows, install Poppler
        // and set the full path, e.g. C:\\poppler\\bin\\pdftoppm.exe
        'pdftoppm_bin' => env('PDFTOPPM_PATH', 'pdftoppm'),

        // DPI used when rasterizing PDFs. 300 is a good quality/size trade-off.
        'dpi' => (int) env('PDF_OCR_DPI', 300),

        // Optional: OCRmyPDF accelerates digital PDFs (native text extraction
        // with no OCR) and is otherwise skipped on missing binary.
        'ocrmypdf_bin' => env('OCRMYPDF_PATH', 'ocrmypdf'),
        'use_ocrmypdf' => (bool) env('OCR_USE_OCRMYPDF', false),
    ],

    'upload' => [
        // Maximum file size in kilobytes (Laravel validator unit).
        'max_size_kb' => (int) env('OCR_UPLOAD_MAX_KB', 20480),

        // Accepted MIME types for uploads. Anything outside this list is
        // rejected at the controller layer.
        'allowed_mimes' => [
            'application/pdf',
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/webp',
            'image/heic',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Heuristic parser (OcrHeuristicParser)
    |--------------------------------------------------------------------------
    |
    | Deterministic extraction for expenses / TVA — tuned for FR + DZ Arabic.
    |
    */
    'parser' => [
        /** Emit document_kind hint: supplier_invoice | unknown */
        'infer_document_kind' => (bool) env('OCR_PARSER_INFER_KIND', true),
    ],

    'processing' => [
        // Queue name used by ProcessDocumentOcr. Workers should listen on it:
        //   php artisan queue:work --queue=ocr
        'queue' => env('OCR_QUEUE', 'ocr'),

        // Global job timeout in seconds.
        'job_timeout' => (int) env('OCR_JOB_TIMEOUT', 180),

        // Retry attempts on transient failures.
        'tries' => (int) env('OCR_JOB_TRIES', 2),
    ],

];
