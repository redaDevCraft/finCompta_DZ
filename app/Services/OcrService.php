<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use RuntimeException;

class OcrService
{
    public function extractText(string $fileContents, string $mimeType): string
    {
        return match ($mimeType) {
            'application/pdf' => $this->extractFromPdf($fileContents),
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/heic' => $this->extractFromImage($fileContents, $mimeType),
            default => throw new RuntimeException("Unsupported mime type: {$mimeType}"),
        };
    }

    protected function extractFromPdf(string $fileContents): string
    {
        $tmpPdf = tempnam(sys_get_temp_dir(), 'ocr_') . '.pdf';
        $outPdf = tempnam(sys_get_temp_dir(), 'ocr_out_') . '.pdf';
        $sidecar = tempnam(sys_get_temp_dir(), 'ocr_txt_') . '.txt';

        try {
            file_put_contents($tmpPdf, $fileContents);

            try {
                Process::run([
                    'ocrmypdf',
                    '--force-ocr',
                    '--sidecar',
                    $sidecar,
                    '-l',
                    'fra+ara',
                    $tmpPdf,
                    $outPdf,
                ])->throw();

                if (is_file($sidecar)) {
                    return trim(file_get_contents($sidecar));
                }
            } catch (\Throwable $e) {
                return $this->extractPdfWithTesseract($tmpPdf);
            }

            return '';
        } finally {
            foreach ([$tmpPdf, $outPdf, $sidecar] as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
        }
    }

    protected function extractPdfWithTesseract(string $tmpPdf): string
    {
        $imgBase = tempnam(sys_get_temp_dir(), 'ocr_img_');
        $generatedImages = [];
        $generatedTexts = [];

        try {
            Process::run([
                'pdftoppm',
                '-jpeg',
                '-r',
                '300',
                $tmpPdf,
                $imgBase,
            ])->throw();

            $generatedImages = glob($imgBase . '*.jpg') ?: [];
            sort($generatedImages);

            $parts = [];

            foreach ($generatedImages as $imagePath) {
                $outBase = tempnam(sys_get_temp_dir(), 'ocr_txt_');
                @unlink($outBase);

                Process::run([
                    'tesseract',
                    $imagePath,
                    $outBase,
                    '-l',
                    'fra+ara',
                    '--oem',
                    '1',
                ])->throw();

                $txtPath = $outBase . '.txt';
                $generatedTexts[] = $txtPath;

                if (is_file($txtPath)) {
                    $parts[] = trim(file_get_contents($txtPath));
                }
            }

            return trim(implode("\n\n", array_filter($parts)));
        } finally {
            foreach ($generatedImages as $imagePath) {
                if (is_file($imagePath)) {
                    @unlink($imagePath);
                }
            }

            foreach ($generatedTexts as $txtPath) {
                if (is_file($txtPath)) {
                    @unlink($txtPath);
                }
            }

            if (is_file($imgBase)) {
                @unlink($imgBase);
            }
        }
    }

    protected function extractFromImage(string $fileContents, string $mimeType): string
    {
        $tmpImage = tempnam(sys_get_temp_dir(), 'ocr_img_') . $this->extensionFromMime($mimeType);

        try {
            file_put_contents($tmpImage, $fileContents);

            try {
                return $this->extractWithPaddle($tmpImage);
            } catch (\Throwable $e) {
                return $this->extractWithTesseract($tmpImage);
            }
        } finally {
            if (is_file($tmpImage)) {
                @unlink($tmpImage);
            }
        }
    }

    protected function extractWithPaddle(string $imagePath): string
    {
        $script = base_path('scripts/paddle_ocr.py');

        if (!is_file($script)) {
            throw new RuntimeException('PaddleOCR script not found');
        }

        $result = Process::run([
            'python3',
            $script,
            $imagePath,
        ]);

        $result->throw();

        return trim($result->output());
    }

    protected function extractWithTesseract(string $imagePath): string
    {
        $outBase = tempnam(sys_get_temp_dir(), 'ocr_txt_');
        @unlink($outBase);

        $txtPath = $outBase . '.txt';

        try {
            Process::run([
                'tesseract',
                $imagePath,
                $outBase,
                '-l',
                'fra+ara',
                '--oem',
                '1',
            ])->throw();

            return is_file($txtPath)
                ? trim(file_get_contents($txtPath))
                : '';
        } finally {
            if (is_file($txtPath)) {
                @unlink($txtPath);
            }

            if (is_file($outBase)) {
                @unlink($outBase);
            }
        }
    }

    protected function extensionFromMime(string $mimeType): string
    {
        return match ($mimeType) {
            'image/jpeg', 'image/jpg' => '.jpg',
            'image/png' => '.png',
            'image/heic' => '.heic',
            default => '.bin',
        };
    }
}