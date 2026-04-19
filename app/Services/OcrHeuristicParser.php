<?php

namespace App\Services;

/**
 * Deterministic, regex-based parser for OCR text from Algerian invoices / receipts.
 *
 * Rules followed strictly:
 *  - Never invent data. Missing fields return null.
 *  - Output shape is stable (UI relies on it).
 *  - No AI calls. Pure heuristics + cross-validation.
 *  - Handles French + Arabic, all common separator/currency variations.
 *
 * Returned shape:
 * [
 *   'vendor_name'        => ?string,
 *   'reference'          => ?string,
 *   'document_date'      => ?string (YYYY-MM-DD),
 *   'total_ht'           => ?float,
 *   'total_vat'          => ?float,
 *   'total_ttc'          => ?float,
 *   'tva_rate'           => ?float,      // 0 | 9 | 14 | 19 (snapped) or raw
 *   'currency'           => ?string,     // DZD | EUR | USD
 *   'payment_method'     => ?string,     // bank | cash | card | check | null
 *   'vendor_nif'         => ?string,
 *   'vendor_nis'         => ?string,
 *   'vendor_rc'          => ?string,
 *   'account_code_hint'  => ?string,     // SCF class 6 suggestion e.g. "626"
 *   'document_kind'      => ?string,      // supplier_invoice | customer_invoice | null
 *   'parser_locale_hints'=> string[],    // e.g. ['ar','fr'] — which scripts dominated OCR text
 * ]
 */
class OcrHeuristicParser
{
    /** Labels that indicate TTC totals (FR + AR, normalized). */
    private const LABELS_TTC = [
        // French
        'total ttc', 'montant ttc', 'net a payer', 'net à payer',
        'total a payer', 'total à payer', 'montant total',
        'total general', 'total général', 't.t.c', 't t c',
        'a payer', 'à payer', 'grand total', 'total du',
        'net a regler', 'net à régler',
        'reste a payer', 'reste à payer', 'solde a payer', 'solde à payer',
        'net ttc', 'ttc global', 'total net ttc', 'montant net ttc',
        'total ttc global', 'a regler', 'à régler',
        // Arabic
        'المبلغ الإجمالي', 'المجموع الإجمالي', 'الإجمالي شامل الضريبة',
        'الإجمالي شامل الرسم', 'شامل الرسم', 'شامل الضريبة',
        'المبلغ النهائي', 'المجموع النهائي', 'الصافي للدفع',
        'المبلغ المستحق', 'المبلغ المستحق للدفع', 'المستحق للدفع',
        'للدفع', 'الإجمالي', 'المجموع العام', 'بالرسم', 'مع الرسم',
        'المجموع الكلي', 'الصافي', 'صافي الفاتورة', 'صافي المبلغ',
        'الإجمالي الفرعي', 'المجموع الفرعي', 'الاجمالي', 'المجموع',
        'واجب الاداء', 'واجب الأداء', 'المبلغ الواجب', 'الثمن الاجمالي',
    ];

    /** Labels that indicate HT subtotals (FR + AR, normalized). */
    private const LABELS_HT = [
        // French
        'total ht', 'montant ht', 'sous total ht', 'sous-total ht',
        'base ht', 'net ht', 'total hors taxes', 'total hors taxe',
        'hors taxes', 'hors taxe', 'h.t', 'h t ', 'base imposable',
        // Arabic
        'المبلغ قبل الضريبة', 'المبلغ قبل الرسم',
        'المجموع قبل الضريبة', 'المجموع قبل الرسم',
        'قبل الرسم', 'قبل الضريبة',
        'خارج الرسم', 'خارج الضريبة',
        'بدون ضريبة', 'بدون رسم',
        'القيمة الصافية', 'الصافي خارج الضريبة',
        'المجموع بدون رسم', 'المجموع بدون ضريبة', 'المبلغ بدون رسم',
        'المبلغ بدون ضريبة', 'المجموع دون رسم', 'المجموع دون ضريبة',
        'القيمة قبل الرسم', 'القيمة قبل الضريبة', 'المجموع قبل الرسم',
        'مجموع السطور', 'مجموع البنود', 'المجموع الفرعي بدون رسم',
        'montant hors tva', 'montant ht tva', 'sous total ht', 'base taxable',
        'total base ht', 'montant de base ht',
    ];

    /** Labels that indicate VAT amount (FR + AR, normalized). */
    private const LABELS_VAT = [
        // French
        'total tva', 'montant tva', 'tva ', 't.v.a', 't v a',
        'taxe', 'taxes',
        // Arabic
        'الرسم على القيمة المضافة', 'الضريبة على القيمة المضافة',
        'ر.ق.م', 'ض.ق.م', 'ر ق م', 'ض ق م',
        'قيمة الرسم', 'قيمة الضريبة', 'مبلغ الرسم', 'مبلغ الضريبة',
        'الرسم', 'الضريبة',
        'مجموع الرسم', 'مجموع الضريبة', 'رسم القيمة المضافة',
        'ضريبة القيمة المضافة', 'الرسم المستحق', 'الضريبة المستحقة',
        'tva 19', 'tva 9', 'tva 14', 'tva a payer', 'tva à payer',
        'taxe sur la valeur ajoutee', 'taxe sur la valeur ajoutée',
    ];

    /** SCF class 6 suggestion keywords (vendor keyword → account code). */
    private const ACCOUNT_KEYWORDS = [
        // 626 — Frais postaux et de télécommunications
        '626' => [
            'djezzy', 'mobilis', 'ooredoo', 'algerie telecom', 'algérie télécom',
            'telephone', 'téléphone', 'internet', 'adsl', 'fibre',
            'poste', 'dhl', 'fedex', 'ups', 'aramex', 'telecom',
            // Arabic
            'جيزي', 'موبيليس', 'أوريدو', 'اتصالات الجزائر', 'هاتف',
            'أنترنت', 'إنترنت', 'بريد',
        ],
        // 606 — Achats non stockés de matières et fournitures
        '606' => [
            'sonelgaz', 'seaal', 'ade ', 'electricite', 'électricité', 'gaz',
            'eau', 'carburant', 'essence', 'naftal', 'diesel', 'gasoil',
            'papeterie', 'fourniture', 'bureautique', 'pharmacie',
            // Arabic
            'سونلغاز', 'كهرباء', 'غاز', 'ماء', 'مياه', 'نفطال',
            'وقود', 'بنزين', 'مازوت', 'صيدلية', 'قرطاسية', 'لوازم',
        ],
        // 625 — Déplacements, missions et réceptions
        '625' => [
            'hotel', 'hôtel', 'restaurant', 'cafe', 'café', 'brasserie',
            'restauration', 'mission', 'deplacement', 'déplacement',
            // Arabic
            'فندق', 'مطعم', 'مقهى', 'ضيافة', 'مهمة', 'تنقل',
        ],
        // 624 — Transports
        '624' => [
            'transport', 'taxi', 'air algerie', 'air algérie',
            'tassili', 'logistique', 'fret',
            // Arabic
            'نقل', 'سيارة أجرة', 'طاكسي', 'الخطوط الجوية الجزائرية',
            'شحن',
        ],
        // 613 — Locations
        '613' => [
            'location', 'loyer', 'bail', 'crédit-bail', 'credit-bail',
            'leasing',
            // Arabic
            'إيجار', 'كراء', 'أجرة المحل',
        ],
        // 616 — Primes d'assurances
        '616' => [
            'assurance', 'saa', 'caat', 'caar', 'trust', 'cash assurances',
            'alliance assurances',
            // Arabic
            'تأمين', 'شركة التأمين',
        ],
        // 622 — Rémunérations d'intermédiaires et honoraires
        '622' => [
            'avocat', 'notaire', 'expert', 'consultant', 'cabinet',
            'honoraires', 'conseil',
            // Arabic
            'محامي', 'موثق', 'خبير', 'مستشار', 'أتعاب', 'استشارة',
        ],
        // 611 — Sous-traitance
        '611' => [
            'sous-traitance', 'sous traitance', 'subcontract',
            'مقاولة من الباطن',
        ],
        // 631 — Impôts, taxes et versements assimilés
        '631' => [
            'cnas', 'casnos', 'impots', 'impôts', 'taxes locales',
            'vignette',
            // Arabic
            'الضمان الاجتماعي', 'الصندوق الوطني',
            'ضرائب', 'رسوم',
        ],
    ];

    /** Payment method keywords. */
    private const PAYMENT_KEYWORDS = [
        'cash' => [
            'espece', 'espèces', 'especes', 'en espece', 'en espèces',
            'cash', 'comptant', 'payé comptant', 'paye comptant',
            // Arabic
            'نقدا', 'نقد', 'كاش', 'نقدي',
        ],
        'bank' => [
            'virement', 'virement bancaire', 'vir.', 'banque',
            'transfert bancaire', 'bank transfer',
            // Arabic
            'تحويل بنكي', 'تحويل', 'حوالة', 'حوالة بنكية', 'بنك',
        ],
        'card' => [
            'carte bancaire', 'carte', 'cb', 'tpe',
            // Arabic
            'بطاقة بنكية', 'بطاقة',
        ],
        'check' => [
            'cheque', 'chèque',
            // Arabic
            'شيك', 'صك',
        ],
    ];

    public function parse(string $text): array
    {
        $text = OcrTextNormalizer::scrubInvalidUtf8($text);

        $original = $this->unifyLineEndings($text);
        $normalized = $this->normalize($original);
        $lines = $this->splitLines($original);
        $normalizedLines = $this->splitLines($normalized);

        $vendor = $this->guessVendor($lines);

        // Arabic invoice footers often spell HT / TVA / TTC explicitly (no "HT"/"TTC" Latin).
        // Prefer these line-anchored totals over generic label scans that can hit table columns.
        $arTotals = $this->extractArabicSummaryTotals($normalized);

        $totalHt = $arTotals['total_ht'];
        $totalVat = $arTotals['total_vat'];
        $totalTtc = $arTotals['total_ttc'];

        if ($totalHt === null) {
            $totalHt = $this->findAmountByLabels($normalizedLines, self::LABELS_HT, preferLast: true);
        }
        if ($totalVat === null) {
            $totalVat = $this->findAmountByLabels($normalizedLines, self::LABELS_VAT, preferLast: true);
        }
        if ($totalTtc === null) {
            $totalTtc = $this->findAmountByLabels($normalizedLines, self::LABELS_TTC, preferLast: true);
        }

        if ($totalTtc === null) {
            $totalTtc = $this->scanFooterForRepairedTotalTtc($normalized);
        }

        // Footer lines sometimes omit Arabic labels; two clean "3 115 060,00" / "591 861,40" rows are HT + TVA.
        [$guessHt, $guessVat] = $this->inferFooterHtVatFromDigitOnlyLines($normalizedLines);
        if ($totalHt === null && $guessHt !== null) {
            $totalHt = $guessHt;
        }
        if ($totalVat === null && $guessVat !== null) {
            $totalVat = $guessVat;
        }

        $tvaRate = $this->guessTvaRate($normalized, $totalHt, $totalVat);

        [$totalHt, $totalVat, $totalTtc] = $this->reconcileTotals($totalHt, $totalVat, $totalTtc, $tvaRate);
        [$totalHt, $totalVat, $totalTtc] = $this->coerceTotalsArithmeticCoherence($totalHt, $totalVat, $totalTtc);

        $documentKind = $this->shouldInferDocumentKind()
            ? $this->inferDocumentKind($normalized)
            : null;

        return [
            'vendor_name' => $vendor,
            'reference' => $this->guessReference($normalized),
            'document_date' => $this->guessDate($normalized),
            'total_ht' => $totalHt,
            'total_vat' => $totalVat,
            'total_ttc' => $totalTtc,
            'tva_rate' => $tvaRate,
            'currency' => $this->guessCurrency($normalized),
            'payment_method' => $this->guessPaymentMethod($normalized),
            'vendor_nif' => $this->guessIdentifier($normalized, 'nif'),
            'vendor_nis' => $this->guessIdentifier($normalized, 'nis'),
            'vendor_rc' => $this->guessIdentifier($normalized, 'rc'),
            'account_code_hint' => $this->suggestAccountCode($normalized),
            'document_kind' => $documentKind,
            'parser_locale_hints' => $this->inferLocaleHints($original),
        ];
    }

    private function unifyLineEndings(string $text): string
    {
        return str_replace(["\r\n", "\r"], "\n", $text);
    }

    /**
     * Normalize for matching:
     *   - Convert Arabic/Persian digits to Latin
     *   - Lowercase (Latin only; Arabic is case-less)
     *   - Strip French accents
     *   - Normalize Arabic letter variants (alif, ya, ta marbuta)
     *   - Remove Arabic diacritics (tashkeel) and tatweel
     *   - Collapse whitespace
     */
    private function normalize(string $text): string
    {
        $text = $this->arabicDigitsToLatin($text);
        // Arabic / Persian decimal separators (in case text bypassed OCR refiner)
        $text = str_replace(["\u{066B}", '·'], '.', $text);
        $text = mb_strtolower($text, 'UTF-8');

        $accentMap = [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i', 'í' => 'i',
            'ô' => 'o', 'ö' => 'o', 'ó' => 'o', 'õ' => 'o',
            'û' => 'u', 'ü' => 'u', 'ù' => 'u', 'ú' => 'u',
            'ç' => 'c', 'ñ' => 'n',
        ];

        $arabicMap = [
            // Alif variants → bare alif
            'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا',
            // Ya variants
            'ى' => 'ي', 'ئ' => 'ي',
            // Waw hamza
            'ؤ' => 'و',
            // Ta marbuta sometimes read as ha: keep as ta marbuta for matching
        ];

        $text = strtr($text, $accentMap);
        $text = strtr($text, $arabicMap);

        // Persian / Urdu letters often mis-OCR'd on DZ bilingual scans — map to Arabic for keyword match
        $scriptUnify = [
            "\u{06A9}" => "\u{0643}", // Keheh → kaf
            "\u{06CC}" => "\u{064A}", // Farsi yeh → yeh
            "\u{06C1}" => "\u{0647}", // Heh goal → heh
        ];
        $text = strtr($text, $scriptUnify);

        // Remove Arabic diacritics (tashkeel) and tatweel
        $text = preg_replace('/[\x{064B}-\x{0652}\x{0640}]/u', '', $text) ?? $text;

        // BiDi / embedding controls (PDF Arabic↔Latin) — strip so labels and amounts stay in reading order
        $text = preg_replace('/[\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', '', $text) ?? $text;

        // Collapse whitespace
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    private function arabicDigitsToLatin(string $text): string
    {
        $map = [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ];

        return strtr($text, $map);
    }

    /** @return string[] */
    private function splitLines(string $text): array
    {
        return array_values(array_filter(
            array_map('trim', explode("\n", $text)),
            static fn ($line) => $line !== ''
        ));
    }

    // ─────────────────────────────────────────────────────────────
    // Vendor
    // ─────────────────────────────────────────────────────────────

    /** @param string[] $lines Original-case lines. */
    private function guessVendor(array $lines): ?string
    {
        $skipPrefix = '/^(facture|invoice|devis|re[cç]u|bon de|date|nif|nis|n°|n\.?i\.?f|rc\b|mf\b|art\b|tel|t[eé]l|fax|email|e-?mail|adresse|address|page|فاتورة|التاريخ|رقم|هاتف|فاكس|العنوان|تعريف|بيان|وصف|تسمية|الكمية|الوحدة|سعر|ثمن|الصنف|المجموع|الإجمالي|الاجمالي|السطر|ت\.|ت\s|ر\.س|د\.ج)/iu';

        foreach (array_slice($lines, 0, 8) as $line) {
            $line = trim(preg_replace('/\s+/', ' ', $line) ?? $line);

            $len = mb_strlen($line);
            if ($len < 3 || $len > 80) {
                continue;
            }
            if (preg_match($skipPrefix, $line)) {
                continue;
            }
            if (preg_match('/\d{4,}/u', $line)) {
                continue;
            }
            if (preg_match('/^[\-=_*]+$/u', $line)) {
                continue;
            }

            return $line;
        }

        return null;
    }

    // ─────────────────────────────────────────────────────────────
    // Amounts
    // ─────────────────────────────────────────────────────────────

    /**
     * Characters allowed inside a captured OCR amount (PCRE does not support \u{...} — use real UTF-8).
     */
    private function amountCaptureFragment(): string
    {
        static $fragment = null;

        if ($fragment !== null) {
            return $fragment;
        }

        $nbsp = mb_chr(0x00A0, 'UTF-8');
        $nns = mb_chr(0x202F, 'UTF-8');
        $arThousands = mb_chr(0x066C, 'UTF-8');
        $arComma = mb_chr(0x060C, 'UTF-8');

        $fragment = '[\d\s.,٫٬'.preg_quote($nbsp.$nns.$arThousands.$arComma, '/').']+';

        return $fragment;
    }

    /**
     * Extract HT / VAT / TTC from typical Algerian Arabic invoice footers where Latin "HT"/"TTC"
     * never appears — only phrases like "المبلغ قبل الضريبة" and spaced numbers "3 115 060,00".
     *
     * @return array{total_ht: ?float, total_vat: ?float, total_ttc: ?float}
     */
    private function extractArabicSummaryTotals(string $normalized): array
    {
        $out = ['total_ht' => null, 'total_vat' => null, 'total_ttc' => null];

        $cap = $this->amountCaptureFragment();

        $htPatterns = [
            '/المبلغ\s+قبل\s+(?:الضريبة|الرسم)\s*[:؛.…]?\s*\R?\s*('.$cap.')/u',
            '/المبلغ\s+قبل\s+(?:الضريبة|الرسم)\s*[:؛.…]?\s*('.$cap.')/u',
            '/مجموع\s+قبل\s+(?:الضريبة|الرسم)\s*[:؛.…]?\s*\R?\s*('.$cap.')/u',
            '/(?:^|\n)\s*قبل\s+(?:الضريبة|الرسم)\s*[:؛.…]?\s*\R?\s*('.$cap.')/u',
            '/المبلغ\s+ال(?:اساسي|أساسي)\s*[:؛.…]?\s*\R?\s*('.$cap.')/u',
            '/قيمة\s+المبيعات\s+قبل\s+(?:الضريبة|الرسم)\s*[:؛.…]?\s*\R?\s*('.$cap.')/u',
            '/(?:المجموع|المبلغ)\s+دون\s+(?:الرسم|الضريبة)\s*[:؛.…]?\s*\R?\s*('.$cap.')/u',
        ];

        $vatPatterns = [
            '/ضريبة\s+القيمة\s+المضافة\s*[:؛.…]?\s*\R?\s*('.$cap.')/u',
            '/ضريبة\s+القيمة\s+المضافة\s*[:؛.…]?\s*('.$cap.')/u',
            '/الرسم\s+على\s+القيمة\s+المضافة\s*[:؛.…]?\s*\R?\s*('.$cap.')/u',
            '/مبلغ\s+(?:الرسم|الضريبة)\s+على\s+القيمة\s+المضافة\s*[:؛.…]?\s*\R?\s*('.$cap.')/u',
            '/(?:مجموع|مبلغ)\s+(?:الرسم|الضريبة)\s+المستحق(?:ة)?\s*[:؛.…]?\s*\R?\s*('.$cap.')/u',
        ];

        $ttcPatterns = [
            '/المبلغ\s*(?:الإجمالي|الاجمالي|الإجمالية|الاجمالية)\s*[:؛.…]?\s*\R?\s*('.$cap.')/u',
            '/المبلغ\s*(?:الإجمالي|الاجمالي|الإجمالية|الاجمالية)\s*[:؛.…]?\s*('.$cap.')/u',
            '/المجموع\s*(?:الإجمالي|الاجمالي|الكلي|الكلى)\s*[:؛.…]?\s*\R?\s*('.$cap.')/u',
            '/(?:صافي\s+المبلغ|المبلغ\s+المستحق|المبلغ\s+النهائي|الصافي\s+للدفع)\s*[:؛.…]?\s*\R?\s*('.$cap.')/u',
            '/واجب\s+ال(?:اداء|أداء)\s*[:؛.…]?\s*\R?\s*('.$cap.')/u',
            '/(?:المبلغ|المجموع)\s+للدفع\s*[:؛.…]?\s*\R?\s*('.$cap.')/u',
            '/المبلغ\s+ال(?:كامل|تام)\s*[:؛.…]?\s*\R?\s*('.$cap.')/u',
            '/(?:المجموع|المبلغ)\s+شامل\s+(?:الرسم|الضريبة)\s*[:؛.…]?\s*\R?\s*('.$cap.')/u',
        ];

        $out['total_ht'] = $this->lastAmountFromPatterns($normalized, $htPatterns);
        $out['total_vat'] = $this->lastAmountFromPatterns($normalized, $vatPatterns);
        $out['total_ttc'] = $this->lastAmountFromPatterns($normalized, $ttcPatterns);

        return $out;
    }

    /**
     * When the TTC line is RTL-scrambled (e.g. "921,40 706 3" instead of "3 706 921,40"), try to rebuild it.
     */
    private function scanFooterForRepairedTotalTtc(string $normalized): ?float
    {
        $lines = array_reverse($this->splitLines($normalized));
        $lines = array_slice($lines, 0, 18);
        $best = null;

        foreach ($lines as $line) {
            if (! preg_match('/جمال|اجمال|ابلغ|بلغ|صافي|مستحق|واجب|ttc|total\s+ttc|net\s+a\s+payer/u', $line)) {
                continue;
            }

            $v = $this->tryRepairScrambledFrenchAmountLine($line);
            if ($v !== null && $v > 1_000 && ($best === null || $v > $best)) {
                $best = $v;
            }
        }

        if ($best !== null) {
            return $best;
        }

        foreach (array_slice($lines, 0, 10) as $line) {
            $v = $this->tryRepairScrambledFrenchAmountLine($line);
            if ($v !== null && $v > 500_000 && ($best === null || $v > $best)) {
                $best = $v;
            }
        }

        return $best;
    }

    /**
     * If OCR outputs the decimal chunk first then digit groups reversed (RTL + LTR mix), rebuild French-style amount.
     * Example: "921,40 706 3" → "3 706 921,40".
     */
    private function tryRepairScrambledFrenchAmountLine(string $line): ?float
    {
        $line = preg_replace('/[^\d\s.,٫٬]+/u', ' ', $line) ?? $line;
        $line = trim(preg_replace('/\s+/u', ' ', $line) ?? '');

        if ($line === '') {
            return null;
        }

        $tokens = array_values(array_filter(explode(' ', $line), static fn ($t) => $t !== ''));

        if ($tokens === []) {
            return null;
        }

        if (! preg_match('/^\d+,\d{1,2}$/', $tokens[0])) {
            return null;
        }

        $ints = array_slice($tokens, 1);
        if ($ints === []) {
            return $this->normalizeAmount($tokens[0]);
        }

        foreach ($ints as $x) {
            if (! preg_match('/^\d{1,3}$/', $x)) {
                return null;
            }
        }

        $merged = implode(' ', array_merge(array_reverse($ints), [$tokens[0]]));

        return $this->normalizeAmount($merged);
    }

    /**
     * Last lines sometimes contain only "3 115 060,00" / "591 861,40" without the Arabic label (OCR ate it).
     * Pairs consecutive amounts where VAT/HT looks like 9–20% (Algeria) are treated as HT + TVA.
     *
     * @return array{0: ?float, 1: ?float}
     */
    private function inferFooterHtVatFromDigitOnlyLines(array $lines): array
    {
        $tail = array_slice($lines, -28);
        $pool = [];

        foreach ($tail as $line) {
            $t = trim($line);
            if ($t === '' || str_contains($t, '|')) {
                continue;
            }
            if (preg_match('/[a-z]{2,}/i', $t)) {
                continue;
            }
            if (! preg_match('/,\d{1,2}\s*$/u', $t)) {
                continue;
            }

            $v = $this->normalizeAmount($t);
            if ($v !== null && $v >= 50_000) {
                $pool[] = $v;
            }
        }

        if (count($pool) < 2) {
            return [null, null];
        }

        for ($i = count($pool) - 1; $i >= 1; $i--) {
            $ht = $pool[$i - 1];
            $vat = $pool[$i];
            if ($ht <= 0) {
                continue;
            }
            $ratio = $vat / $ht;
            if ($ratio >= 0.04 && $ratio <= 0.24) {
                return [$ht, $vat];
            }
        }

        return [null, null];
    }

    /**
     * When HT + TVA are reliable but TTC is garbled or wrong, prefer arithmetic TTC = HT + TVA.
     */
    private function coerceTotalsArithmeticCoherence(?float $ht, ?float $vat, ?float $ttc): array
    {
        if ($ht === null || $vat === null) {
            return [$ht, $vat, $ttc];
        }

        $sum = round($ht + $vat, 2);

        if ($ttc === null) {
            return [$ht, $vat, $sum];
        }

        $tol = max(2.0, abs($sum) * 0.004);

        if (abs($ttc - $sum) > $tol) {
            return [$ht, $vat, $sum];
        }

        return [$ht, $vat, $ttc];
    }

    /**
     * @param  string[]  $patterns
     */
    private function lastAmountFromPatterns(string $text, array $patterns): ?float
    {
        $bestOffset = -1;
        $bestValue = null;

        foreach ($patterns as $pattern) {
            if (! preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            foreach ($matches[1] as $pair) {
                if (! is_array($pair) || $pair[0] === '' || $pair[0] === null) {
                    continue;
                }

                $raw = trim((string) $pair[0]);
                $offset = (int) ($pair[1] ?? -1);
                $value = $this->normalizeAmount($raw);

                if ($value !== null && $offset >= $bestOffset) {
                    $bestOffset = $offset;
                    $bestValue = $value;
                }
            }
        }

        return $bestValue;
    }

    private function findAmountByLabels(array $lines, array $labels, bool $preferLast = true): ?float
    {
        $matches = [];

        foreach ($lines as $i => $line) {
            foreach ($labels as $label) {
                if (! $this->strContainsCi($line, $label)) {
                    continue;
                }

                $pos = $this->strPosCi($line, $label);
                if ($pos === false) {
                    continue;
                }

                $labelLen = mb_strlen($label);
                $matched = false;

                $after = mb_substr($line, $pos + $labelLen);
                if (($amount = $this->extractFirstAmount($after)) !== null) {
                    $matches[] = ['value' => $amount, 'line' => $i];
                    $matched = true;
                }

                if (! $matched) {
                    $before = mb_substr($line, 0, $pos);
                    if (($amount = $this->extractLastAmount($before)) !== null) {
                        $matches[] = ['value' => $amount, 'line' => $i];
                        $matched = true;
                    }
                }

                if (! $matched) {
                    for ($k = 1; $k <= 2; $k++) {
                        if (! isset($lines[$i + $k])) {
                            break;
                        }
                        $amount = $this->extractFirstAmount($lines[$i + $k]);
                        if ($amount !== null) {
                            $matches[] = ['value' => $amount, 'line' => $i + $k];
                            $matched = true;
                            break;
                        }
                    }
                }

                // RTL / table rows: amount may be on the same line but not directly adjacent to the label
                if (! $matched && preg_match_all($this->amountPattern(), $line, $am) && $am[1] !== []) {
                    $candidates = [];
                    foreach ($am[1] as $raw) {
                        $n = $this->normalizeAmount($raw);
                        if ($n !== null) {
                            $candidates[] = $n;
                        }
                    }
                    if ($candidates !== []) {
                        $matches[] = [
                            'value' => $preferLast ? $candidates[array_key_last($candidates)] : $candidates[0],
                            'line' => $i,
                        ];
                    }
                }
            }
        }

        if ($matches === []) {
            return null;
        }

        $pick = $preferLast ? end($matches) : $matches[0];

        return $pick['value'] ?? null;
    }

    private function strContainsCi(string $haystack, string $needle): bool
    {
        return $this->strPosCi($haystack, $needle) !== false;
    }

    private function strPosCi(string $haystack, string $needle): int|false
    {
        return mb_stripos($haystack, $needle);
    }

    private function extractFirstAmount(string $segment): ?float
    {
        if (preg_match($this->amountPattern(), $segment, $m)) {
            return $this->normalizeAmount($m[1]);
        }

        return null;
    }

    private function extractLastAmount(string $segment): ?float
    {
        if (! preg_match_all($this->amountPattern(), $segment, $ms)) {
            return null;
        }

        $last = end($ms[1]);

        return $last !== false ? $this->normalizeAmount($last) : null;
    }

    private function amountPattern(): string
    {
        // Do not use (?<!\w) before digits: Arabic letters are word chars and amounts are often glued to labels
        // Character class includes Arabic comma (U+060C) and Arabic thousands separator (U+066C) as digit-group separators
        $arComma = mb_chr(0x060C, 'UTF-8');
        $arThousands = mb_chr(0x066C, 'UTF-8');
        $arDecimal = mb_chr(0x066B, 'UTF-8');
        $sepClass = '[ .,'.preg_quote($arComma.$arThousands, '/').']';
        $decClass = '[.,'.preg_quote($arDecimal, '/').']';

        // Decimal fraction: up to 6 digits (OCR sometimes emits 4+ on quantities); thousands: space or Arabic/Latin separators
        return '/(?<![0-9%])([\-]?\d{1,3}(?:'.$sepClass.'?\d{3})*(?:'.$decClass.'\d{1,6})?|\d+(?:'.$decClass.'\d{1,6})?)(?=\s*(?:da|dzd|eur|usd|€|\$|دج|د\.ج|دينار)\b|\s|$|[\p{Arabic}]|[:,؛]|…|\z)/iu';
    }

    private function normalizeAmount(string $raw): ?float
    {
        $raw = trim($raw);
        $raw = str_replace(["\u{066C}", "\u{060C}"], '', $raw);
        $raw = str_replace("\u{066B}", '.', $raw);
        $raw = preg_replace('/\s+/u', '', $raw) ?? $raw;

        if ($raw === '' || $raw === '-' || ! preg_match('/\d/', $raw)) {
            return null;
        }

        $hasComma = str_contains($raw, ',');
        $hasDot = str_contains($raw, '.');

        if ($hasComma && $hasDot) {
            $lastComma = strrpos($raw, ',');
            $lastDot = strrpos($raw, '.');
            if ($lastComma > $lastDot) {
                $raw = str_replace('.', '', $raw);
                $raw = str_replace(',', '.', $raw);
            } else {
                $raw = str_replace(',', '', $raw);
            }
        } elseif ($hasComma) {
            if (preg_match('/,\d{1,8}$/', $raw)) {
                $raw = str_replace(',', '.', $raw);
            } else {
                $raw = str_replace(',', '', $raw);
            }
        } elseif ($hasDot) {
            if (! preg_match('/\.\d{1,3}$/', $raw)) {
                $raw = str_replace('.', '', $raw);
            }
        }

        if (! is_numeric($raw)) {
            return null;
        }

        $value = round((float) $raw, 2);

        if ($value < 0 || $value > 1_000_000_000) {
            return null;
        }

        return $value;
    }

    // ─────────────────────────────────────────────────────────────
    // TVA rate detection & cross-validation
    // ─────────────────────────────────────────────────────────────

    private function guessTvaRate(string $normalized, ?float $ht, ?float $vat): ?float
    {
        $patterns = [
            '/(?:tva|t\.v\.a|t v a|ر\.ق\.م|ض\.ق\.م|الرسم|الضريبة)[^0-9%]{0,12}(\d{1,2}(?:[.,]\d{1,2})?)\s*%/u',
            '/(\d{1,2}(?:[.,]\d{1,2})?)\s*%\s*(?:de\s+)?(?:tva|t\.v\.a|الرسم|الضريبة)/u',
        ];

        foreach ($patterns as $p) {
            if (preg_match($p, $normalized, $m)) {
                $rate = (float) str_replace(',', '.', $m[1]);

                return $this->snapAlgerianVatRate($rate);
            }
        }

        if ($ht && $ht > 0 && $vat !== null && $vat >= 0) {
            return $this->snapAlgerianVatRate(($vat / $ht) * 100);
        }

        return null;
    }

    private function snapAlgerianVatRate(float $rate): ?float
    {
        if ($rate < 0 || $rate > 40) {
            return null;
        }

        foreach ([0.0, 9.0, 14.0, 19.0] as $standard) {
            if (abs($rate - $standard) <= 0.75) {
                return $standard;
            }
        }

        return round($rate, 2);
    }

    /** @return array{0:?float,1:?float,2:?float} */
    private function reconcileTotals(?float $ht, ?float $vat, ?float $ttc, ?float $rate): array
    {
        $r = $rate !== null ? $rate / 100 : null;

        $derive = static function (?float $a, ?float $b, string $op) {
            if ($a === null || $b === null) {
                return null;
            }

            return match ($op) {
                '+' => round($a + $b, 2),
                '-' => round($a - $b, 2),
                '/' => $b == 0.0 ? null : round($a / $b, 2),
                default => null,
            };
        };

        if ($ht !== null && $vat !== null && $ttc === null) {
            $ttc = $derive($ht, $vat, '+');
        } elseif ($ht !== null && $ttc !== null && $vat === null) {
            $vat = $derive($ttc, $ht, '-');
        } elseif ($vat !== null && $ttc !== null && $ht === null) {
            $ht = $derive($ttc, $vat, '-');
        } elseif ($ttc !== null && $r !== null && $ht === null && $vat === null) {
            $ht = $derive($ttc, 1 + $r, '/');
            if ($ht !== null) {
                $vat = round($ttc - $ht, 2);
            }
        } elseif ($ht !== null && $r !== null && $vat === null && $ttc === null) {
            $vat = round($ht * $r, 2);
            $ttc = round($ht + $vat, 2);
        }

        return [$ht, $vat, $ttc];
    }

    // ─────────────────────────────────────────────────────────────
    // Reference / date / identifiers / currency / account / payment
    // ─────────────────────────────────────────────────────────────

    private function guessReference(string $normalized): ?string
    {
        $patterns = [
            // French
            '/\b(?:facture|invoice|fact|devis|bon\s+de\s+commande|bc)\s*(?:n\s*[°o]?|num(?:ero)?|#|:|\-)?\s*([\p{L}\p{N}][\p{L}\p{N}\-\/_]{2,40})/iu',
            '/\bref(?:erence)?\s*[:#\-]?\s*([\p{L}\p{N}][\p{L}\p{N}\-\/_]{2,40})/iu',
            '/\bfac[-\/]?\d{2,}[-\/]?\d{2,}[-\/]?\d{0,6}/iu',
            // Arabic: "فاتورة رقم 123", "رقم الفاتورة : 123", "مرجع ..."
            '/(?:فاتورة|رقم\s+الفاتورة|مرجع|رقم\s+الطلب)\s*(?:رقم|:|#|\-)?\s*([\p{L}\p{N}][\p{L}\p{N}\-\/_\s]{1,40})/u',
            '/(?:رقم|ref)\s*[:#]?\s*([\p{L}\p{N}][\p{L}\p{N}\-\/_]{2,40})/iu',
        ];

        foreach ($patterns as $p) {
            if (preg_match($p, $normalized, $m)) {
                $raw = trim((string) ($m[1] ?? $m[0]));
                $raw = preg_replace('/\s+/u', '', $raw) ?? $raw;

                return strtoupper($raw);
            }
        }

        return null;
    }

    private function guessDate(string $normalized): ?string
    {
        $months = [
            // French
            'janvier' => 1, 'fevrier' => 2, 'mars' => 3, 'avril' => 4,
            'mai' => 5, 'juin' => 6, 'juillet' => 7, 'aout' => 8,
            'septembre' => 9, 'octobre' => 10, 'novembre' => 11, 'decembre' => 12,
            // Arabic — Maghreb French-based names
            'جانفي' => 1, 'فيفري' => 2, 'مارس' => 3, 'أفريل' => 4, 'افريل' => 4,
            'ماي' => 5, 'جوان' => 6, 'جويلية' => 7, 'أوت' => 8, 'اوت' => 8,
            'سبتمبر' => 9, 'أكتوبر' => 10, 'اكتوبر' => 10,
            'نوفمبر' => 11, 'ديسمبر' => 12,
            // Arabic — classical
            'يناير' => 1, 'فبراير' => 2, 'أبريل' => 4, 'ابريل' => 4,
            'مايو' => 5, 'يونيو' => 6, 'يوليو' => 7, 'أغسطس' => 8, 'اغسطس' => 8,
        ];

        $candidates = [];

        if (preg_match_all('/(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{2,4})/u', $normalized, $ms, PREG_SET_ORDER)) {
            foreach ($ms as $m) {
                $y = (int) $m[3];
                $y = $y < 100 ? 2000 + $y : $y;
                $d = $this->formatDate($y, (int) $m[2], (int) $m[1]);
                if ($d) {
                    $candidates[] = $d;
                }
            }
        }

        if (preg_match_all('/(\d{4})[\/\-.](\d{1,2})[\/\-.](\d{1,2})/u', $normalized, $ms, PREG_SET_ORDER)) {
            foreach ($ms as $m) {
                $d = $this->formatDate((int) $m[1], (int) $m[2], (int) $m[3]);
                if ($d) {
                    $candidates[] = $d;
                }
            }
        }

        // Number-month-number (e.g. "12 janvier 2026" or "12 جانفي 2026")
        if (preg_match_all('/(\d{1,2})\s+([\p{L}]+)\s+(\d{4})/u', $normalized, $ms, PREG_SET_ORDER)) {
            foreach ($ms as $m) {
                $monthKey = $m[2];
                if (isset($months[$monthKey])) {
                    $d = $this->formatDate((int) $m[3], $months[$monthKey], (int) $m[1]);
                    if ($d) {
                        $candidates[] = $d;
                    }
                }
            }
        }

        if ($candidates === []) {
            return null;
        }

        $today = date('Y-m-d');
        foreach ($candidates as $d) {
            if ($d <= $today) {
                return $d;
            }
        }

        return $candidates[0];
    }

    private function formatDate(int $y, int $mo, int $d): ?string
    {
        if ($y < 1990 || $y > 2100) {
            return null;
        }
        if (! checkdate($mo, $d, $y)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $y, $mo, $d);
    }

    private function guessCurrency(string $normalized): ?string
    {
        if (preg_match('/\b(dzd|da)\b|دج|د\.ج|دينار/u', $normalized)) {
            return 'DZD';
        }
        if (preg_match('/\beur\b|€|يورو/u', $normalized)) {
            return 'EUR';
        }
        if (preg_match('/\busd\b|\$|دولار/u', $normalized)) {
            return 'USD';
        }

        return null;
    }

    private function guessPaymentMethod(string $normalized): ?string
    {
        foreach (self::PAYMENT_KEYWORDS as $method => $keywords) {
            foreach ($keywords as $kw) {
                if ($this->strContainsCi($normalized, $kw)) {
                    return $method;
                }
            }
        }

        return null;
    }

    private function shouldInferDocumentKind(): bool
    {
        try {
            if (! function_exists('app') || ! function_exists('config')) {
                return true;
            }

            if (! app()->bound('config')) {
                return true;
            }

            return (bool) config('ocr.parser.infer_document_kind', true);
        } catch (\Throwable) {
            return true;
        }
    }

    /**
     * @return list<string>
     */
    private function inferLocaleHints(string $text): array
    {
        $ar = preg_match_all('/\p{Arabic}/u', $text);
        // Latin letters (French + ASCII) without relying on \p{Script=Latin} (IDE / PCRE build variance)
        $lat = preg_match_all('/[A-Za-z\\x{C0}-\\x{02FF}]/u', $text);
        $ar = $ar === false ? 0 : $ar;
        $lat = $lat === false ? 0 : $lat;

        $hints = [];
        if ($ar >= 8) {
            $hints[] = 'ar';
        }
        if ($lat >= 24) {
            $hints[] = 'fr';
        }

        return array_values(array_unique($hints));
    }

    private function inferDocumentKind(string $normalized): ?string
    {
        $supplierScore = 0;
        $customerScore = 0;

        foreach ([
            'fournisseur', 'achat', 'achats', 'charge', 'facture achat',
            'bon de reception', 'bon de réception', 'توريد', 'مورد', 'مشتريات',
            'شراء', 'وصل شراء', 'فاتورة شراء', 'مشتريات المواد',
        ] as $kw) {
            if ($this->strContainsCi($normalized, $kw)) {
                $supplierScore++;
            }
        }

        foreach ([
            'vente', 'ventes', 'client', 'facture client', 'devis client',
            'مبيعات', 'زبون', 'عميل', 'فاتورة بيع',
        ] as $kw) {
            if ($this->strContainsCi($normalized, $kw)) {
                $customerScore++;
            }
        }

        if ($customerScore >= 2 && $customerScore > $supplierScore) {
            return 'customer_invoice';
        }

        if ($supplierScore >= 2 && $supplierScore > $customerScore) {
            return 'supplier_invoice';
        }

        if ($supplierScore >= 1 && $customerScore === 0) {
            return 'supplier_invoice';
        }

        return null;
    }

    private function guessIdentifier(string $normalized, string $type): ?string
    {
        $label = match ($type) {
            'nif' => 'n\.?i\.?f|رقم\s+التعريف\s+الجبائي|المعرف\s+الجبائي|تعريف\s+جبائي',
            'nis' => 'n\.?i\.?s|رقم\s+التعريف\s+الإحصائي|رقم\s+التعريف\s+الاحصائي',
            'rc' => 'r\.?c|registre\s+de\s+commerce|السجل\s+التجاري|ر\.?ت\.?ج|رقم\s+السجل\s+التجاري',
            default => null,
        };

        if (! $label) {
            return null;
        }

        if (preg_match('/(?:'.$label.')\s*[:#\-]?\s*([0-9٠-٩۰-۹]{6,20})/iu', $normalized, $m)) {
            $digits = $this->arabicDigitsToLatin(trim($m[1]));
            $digits = preg_replace('/\D/', '', $digits) ?? '';

            return $digits !== '' ? $digits : null;
        }

        return null;
    }

    private function suggestAccountCode(string $normalized): ?string
    {
        foreach (self::ACCOUNT_KEYWORDS as $code => $keywords) {
            foreach ($keywords as $kw) {
                if ($this->strContainsCi($normalized, $kw)) {
                    return (string) $code;
                }
            }
        }

        return null;
    }
}
