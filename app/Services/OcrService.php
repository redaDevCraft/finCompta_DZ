<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Local OCR service backed by Tesseract.
 *
 * - Images are OCR'd directly by Tesseract.
 * - PDFs are rasterized via Poppler's pdftoppm, then each page is OCR'd.
 *   A digital-PDF fast-path via ocrmypdf (text extraction) is available
 *   when OCR_USE_OCRMYPDF=true.
 *
 * The service is deliberately AI-free. Structured parsing is performed
 * in a separate OcrHeuristicParser.
 */
class OcrService
{
    public function extractText(string $fileContents, string $mimeType): string
    {
        $raw = match (true) {
            $mimeType === 'application/pdf' => $this->extractFromPdf($fileContents),
            str_starts_with($mimeType, 'image/') => $this->extractFromImage($fileContents, $mimeType),
            default => throw new RuntimeException("Unsupported mime type for OCR: {$mimeType}"),
        };

        return OcrTextNormalizer::refine($raw);
    }

    protected function extractFromImage(string $fileContents, string $mimeType): string
    {
        $tmpImage = $this->writeTempFile($fileContents, $this->extensionFromMime($mimeType));

        try {
            $raw = $this->runTesseract($tmpImage);
            $raw = $this->mergeArabicBoost($raw, $tmpImage);

            return $raw;
        } finally {
            $this->safeUnlink($tmpImage);
        }
    }

    protected function extractFromPdf(string $fileContents): string
    {
        $tmpPdf = $this->writeTempFile($fileContents, '.pdf');

        try {
            if ((bool) config('ocr.pdf.use_ocrmypdf')) {
                $native = $this->tryOcrMyPdf($tmpPdf);
                if ($native !== null && $native !== '') {
                    return $native;
                }
            }

            return $this->rasterizePdfAndOcr($tmpPdf);
        } finally {
            $this->safeUnlink($tmpPdf);
        }
    }

    protected function tryOcrMyPdf(string $pdfPath): ?string
    {
        $bin = (string) config('ocr.pdf.ocrmypdf_bin');
        $sidecar = tempnam(sys_get_temp_dir(), 'ocr_txt_').'.txt';
        $outPdf = tempnam(sys_get_temp_dir(), 'ocr_out_').'.pdf';

        try {
            $result = Process::run([
                $bin,
                '--force-ocr',
                '--sidecar',
                $sidecar,
                '-l',
                $this->tesseractLangForOcrMyPdf(),
                $pdfPath,
                $outPdf,
            ]);

            if ($result->failed()) {
                return null;
            }

            return is_file($sidecar) ? trim((string) file_get_contents($sidecar)) : null;
        } catch (\Throwable $e) {
            return null;
        } finally {
            $this->safeUnlink($sidecar);
            $this->safeUnlink($outPdf);
        }
    }

    protected function rasterizePdfAndOcr(string $pdfPath): string
    {
        $bin = (string) config('ocr.pdf.pdftoppm_bin');
        $dpi = (int) config('ocr.pdf.dpi', 300);

        $imgBase = tempnam(sys_get_temp_dir(), 'ocr_img_');

        try {
            $result = Process::run([
                $bin,
                '-jpeg',
                '-r',
                (string) $dpi,
                $pdfPath,
                $imgBase,
            ]);

            if ($result->failed()) {
                throw new RuntimeException(
                    'Impossible de convertir le PDF en images. Vérifiez que Poppler (pdftoppm) est installé. '
                    .$result->errorOutput()
                );
            }

            $images = glob($imgBase.'*.jpg') ?: [];
            sort($images);

            if ($images === []) {
                throw new RuntimeException('Aucune page générée à partir du PDF.');
            }

            $parts = [];
            foreach ($images as $index => $imagePath) {
                try {
                    $pageText = $this->runTesseract($imagePath);
                    $pageText = $this->mergeArabicBoost($pageText, $imagePath);
                    if ($pageText !== '') {
                        $parts[] = '--- Page '.($index + 1)." ---\n".$pageText;
                    }
                } finally {
                    $this->safeUnlink($imagePath);
                }
            }

            return trim(implode("\n\n", $parts));
        } finally {
            $this->safeUnlink($imgBase);
        }
    }

    /**
     * @param  array{lang?:string,oem?:int,psm?:int}|null  $overrides
     */
    protected function runTesseract(string $imagePath, ?array $overrides = null): string
    {
        $bin = (string) config('ocr.tesseract.bin');
        $lang = (string) ($overrides['lang'] ?? config('ocr.tesseract.lang', 'eng'));
        $oem = (int) ($overrides['oem'] ?? config('ocr.tesseract.oem', 1));
        $psm = (int) ($overrides['psm'] ?? config('ocr.tesseract.psm', 3));
        $timeout = (int) config('ocr.tesseract.timeout', 90);

        $outBase = tempnam(sys_get_temp_dir(), 'ocr_txt_');
        @unlink($outBase);
        $txtPath = $outBase.'.txt';

        try {
            $cmd = [
                $bin,
                $imagePath,
                $outBase,
                '-l', $lang,
                '--oem', (string) $oem,
                '--psm', (string) $psm,
            ];

            foreach ((array) config('ocr.tesseract.config', []) as $cfgKey => $cfgVal) {
                if ($cfgKey === '' || $cfgVal === null || $cfgVal === '') {
                    continue;
                }
                $cmd[] = '-c';
                $cmd[] = $cfgKey.'='.$cfgVal;
            }

            $result = Process::timeout($timeout)->run($cmd);

            if ($result->failed()) {
                throw new RuntimeException(
                    'Tesseract a échoué: '.trim($result->errorOutput() ?: $result->output())
                );
            }

            if (! is_file($txtPath)) {
                return '';
            }

            $text = (string) file_get_contents($txtPath);

            return trim($text);
        } finally {
            $this->safeUnlink($txtPath);
            $this->safeUnlink($outBase);
        }
    }

    protected function tesseractLangForOcrMyPdf(): string
    {
        return str_replace('+', '+', (string) config('ocr.tesseract.lang', 'eng'));
    }

    /**
     * Ratio of Arabic script codepoints to total string length (UTF-8 safe).
     */
    protected function arabicScriptRatio(string $text): float
    {
        $text = trim($text);
        if ($text === '') {
            return 0.0;
        }

        $len = mb_strlen($text);
        if ($len < 1) {
            return 0.0;
        }

        $matches = preg_match_all('/\p{Arabic}/u', $text);

        return ($matches !== false && $matches > 0) ? $matches / $len : 0.0;
    }

    /**
     * Append a second OCR pass when primary text is Latin-heavy but the document
     * may carry Arabic totals (common on Algerian supplier invoices).
     */
    protected function mergeArabicBoost(string $primary, string $imagePath): string
    {
        if (! (bool) config('ocr.tesseract.arabic_boost_pass', false)) {
            return $primary;
        }

        $threshold = (float) config('ocr.tesseract.arabic_boost_min_primary_ratio', 0.06);
        if ($this->arabicScriptRatio($primary) >= $threshold) {
            return $primary;
        }

        $boostLang = (string) config('ocr.tesseract.arabic_boost_lang', 'ara+fra+eng');
        $boostPsm = (int) config('ocr.tesseract.arabic_boost_psm', 4);

        try {
            $boost = $this->runTesseract($imagePath, [
                'lang' => $boostLang,
                'psm' => $boostPsm,
            ]);
        } catch (\Throwable) {
            return $primary;
        }

        if ($boost === '' || $boost === $primary) {
            return $primary;
        }

        if ($this->arabicScriptRatio($boost) <= $this->arabicScriptRatio($primary)) {
            return $primary;
        }

        return rtrim($primary)."\n\n--- OCR ({$boostLang}, psm={$boostPsm}) ---\n".$boost;
    }

    protected function writeTempFile(string $contents, string $extension): string
    {
        $path = tempnam(sys_get_temp_dir(), 'ocr_in_').$extension;
        file_put_contents($path, $contents);

        return $path;
    }

    protected function safeUnlink(?string $path): void
    {
        if ($path && is_file($path)) {
            @unlink($path);
        }
    }

    protected function extensionFromMime(string $mimeType): string
    {
        return match ($mimeType) {
            'image/jpeg', 'image/jpg' => '.jpg',
            'image/png' => '.png',
            'image/webp' => '.webp',
            'image/heic' => '.heic',
            default => '.bin',
        };
    }
}
