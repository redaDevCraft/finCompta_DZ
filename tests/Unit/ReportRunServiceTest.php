<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Reports\ReportRunService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ReportRunServiceTest extends TestCase
{
    public function test_queue_throws_for_unknown_type(): void
    {
        $service = new ReportRunService;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown report type');

        $service->queue('00000000-0000-0000-0000-000000000000', null, 'not_a_real_type', []);
    }
}
