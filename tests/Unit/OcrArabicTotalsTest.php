<?php

namespace Tests\Unit;

use App\Services\OcrHeuristicParser;
use PHPUnit\Framework\TestCase;

class OcrArabicTotalsTest extends TestCase
{
    public function test_arabic_footer_totals_without_latin_ht_labels(): void
    {
        $parser = new OcrHeuristicParser;

        $text = <<<'TXT'
ش ذ م م بيع مواد البناء
المبلغ قبل الضريبة : 3 115 060,00
ضريبة القيمة المضافة : 591 861,40
المبلغ الاجمالي : 3 706 921,40
TXT;

        $r = $parser->parse($text);

        $this->assertSame(3_115_060.0, $r['total_ht']);
        $this->assertSame(591_861.4, $r['total_vat']);
        $this->assertSame(3_706_921.4, $r['total_ttc']);
    }

    public function test_digit_only_ht_vat_footer_and_scrambled_ttc_tokens(): void
    {
        $parser = new OcrHeuristicParser;

        $text = <<<'TXT'
138 000,00 | 300.00 460 FER ROND 12
3115 060,00

591 861,40

المبلغ الاجمالي 921,40 706 3
TXT;

        $r = $parser->parse($text);

        $this->assertEqualsWithDelta(3_115_060.0, $r['total_ht'], 0.02);
        $this->assertEqualsWithDelta(591_861.4, $r['total_vat'], 0.02);
        $this->assertEqualsWithDelta(3_706_921.4, $r['total_ttc'], 0.02);
    }

    public function test_wajib_alada_ttc_line_and_persian_digits(): void
    {
        $parser = new OcrHeuristicParser;

        $text = <<<'TXT'
فاتورة شراء — ALGERIE TELECOM SPA
المبلغ قبل الضريبة : ٥٧٨,٠٠
ضريبة القيمة المضافة : ١٠٩,٨٢
واجب الأداء : ٦٨٧,٨٢
TXT;

        $r = $parser->parse($text);

        $this->assertEqualsWithDelta(578.0, $r['total_ht'], 0.02);
        $this->assertEqualsWithDelta(109.82, $r['total_vat'], 0.02);
        $this->assertEqualsWithDelta(687.82, $r['total_ttc'], 0.02);
        $this->assertContains('ar', $r['parser_locale_hints']);
    }

    public function test_infer_supplier_kind_from_achat_keywords(): void
    {
        $parser = new OcrHeuristicParser;

        $text = <<<'TXT'
FACTURE ACHAT FOURNISSEUR
Total HT 100,00
TVA 19,00
Total TTC 119,00
TXT;

        $r = $parser->parse($text);

        $this->assertSame('purchase_invoice', $r['document_kind']);

    }
}
