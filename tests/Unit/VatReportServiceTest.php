<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\VatReportService;
use PHPUnit\Framework\TestCase;

class VatReportServiceTest extends TestCase
{
    public function test_export_filename_suffix_month(): void
    {
        $s = new VatReportService;

        $this->assertSame(
            '2026_03',
            $s->exportFilenameSuffix(['year' => 2026, 'month' => 3, 'quarter' => null]),
        );
    }

    public function test_export_filename_suffix_quarter(): void
    {
        $s = new VatReportService;

        $this->assertSame(
            '2026_Q2',
            $s->exportFilenameSuffix(['year' => 2026, 'month' => null, 'quarter' => 2]),
        );
    }
}
