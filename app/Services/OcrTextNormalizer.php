<?php

namespace App\Services;

/**
 * Post-processes OCR output so Arabic (and mixed FR/AR) reads more reliably in UI and parsers.
 *
 * - Unicode NFKC (presentation forms, compatibility variants → canonical)
 * - Strip invisible / zero-width characters that break alignment
 * - Map Arabic punctuation used as decimal/thousands separators to Latin forms
 * - Normalize exotic line breaks
 */
class OcrTextNormalizer
{
    /**
     * Remove / replace invalid UTF-8 so json_encode, preg /u, and Normalizer do not fail.
     */
    public static function scrubInvalidUtf8(string $text): string
    {
        if ($text === '') {
            return '';
        }

        if (function_exists('mb_scrub')) {
            return mb_scrub($text, 'UTF-8');
        }

        if (function_exists('iconv')) {
            $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $text);

            if ($clean !== false) {
                return $clean;
            }
        }

        return $text;
    }

    public static function refine(string $text): string
    {
        $text = self::scrubInvalidUtf8($text);

        $text = str_replace(["\r\n", "\r", "\x85", "\u{2028}", "\u{2029}"], "\n", $text);

        if (class_exists(\Normalizer::class)) {
            $normalized = \Normalizer::normalize($text, \Normalizer::FORM_KC);
            if (is_string($normalized)) {
                $text = $normalized;
            }
        }

        // Zero-width and BOM (often inserted by OCR / PDF extraction)
        $text = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $text) ?? $text;

        // BiDi / embedding controls (Arabic↔Latin PDFs): these scramble token order in OCR text
        $text = preg_replace('/[\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', '', $text) ?? $text;

        // Arabic decimal separator ٫ (U+066B) and middle dot sometimes misread as decimal
        $text = str_replace(["\u{066B}", '·', '∙'], '.', $text);

        // Arabic comma ، (U+060C) and Arabic date separator ٬ (U+066C) as grouping / punctuation
        $text = str_replace(["\u{060C}", "\u{066C}"], ',', $text);

        // Thin / narrow no-break spaces often appear between digits and letters
        $text = str_replace(["\u{2009}", "\u{202F}", "\u{00A0}"], ' ', $text);

        // Fullwidth digits (some PDF engines / mixed encodings)
        static $fwDigits = null;
        if ($fwDigits === null) {
            $fwDigits = [];
            for ($i = 0; $i <= 9; $i++) {
                $fwDigits[mb_chr(0xFF10 + $i, 'UTF-8')] = (string) $i;
            }
        }
        $text = strtr($text, $fwDigits);

        $text = self::scrubInvalidUtf8(trim($text));

        return $text;
    }
}
