<?php

namespace Tests\Unit;

use App\Services\NightShiftDifferential;
use App\Support\PayrollCalculator;
use PHPUnit\Framework\TestCase;

class PhilippinePayrollEdgeCaseTest extends TestCase
{
    public function test_mwe_taxable_extra_is_not_removed_from_taxable_income(): void
    {
        $result = PayrollCalculator::forCutoff(22000, 0, true, 0, 0, true, 0, null, true, 25000);
        $this->assertGreaterThan(0, $result['tax']);
        $this->assertSame(24075.0, $result['taxable']);
        $this->assertSame(25000.0, $result['other_taxable']);
    }

    public function test_nsd_window_handles_an_overnight_shift(): void
    {
        $attendance = (object) [
            'time_in' => '2026-09-17 21:00:00',
            'time_out' => '2026-09-18 07:00:00',
        ];

        $service = new NightShiftDifferential();
        $amount = $service->forAttendance($attendance, 22000);

        $this->assertSame(round(8 * (22000 / 22 / 8) * 0.10, 2), $amount);
    }
}
