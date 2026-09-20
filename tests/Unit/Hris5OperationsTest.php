<?php

namespace Tests\Unit;

use App\Support\PayrollCalculator;
use PHPUnit\Framework\TestCase;

class Hris5OperationsTest extends TestCase
{
    public function test_daily_payroll_can_be_calculated_from_paid_days(): void
    {
        $result = PayrollCalculator::forNonMonthlyCutoff(750 * 10, 750 * 26, 0, false, 0, 0, false);
        $this->assertSame(7500.0, $result['gross']);
        $this->assertSame(7500.0, $result['net']);
    }

    public function test_hourly_payroll_can_be_calculated_from_hours(): void
    {
        $result = PayrollCalculator::forNonMonthlyCutoff(100 * 80, 100 * 8 * 26, 0, false, 0, 0, false);
        $this->assertSame(8000.0, $result['gross']);
        $this->assertSame(8000.0, $result['net']);
    }

    public function test_non_monthly_payroll_keeps_pay_components_separate(): void
    {
        $result = PayrollCalculator::forNonMonthlyCutoff(7500, 19500, 0, false, 1000, 500, false, 250);
        $this->assertSame(9250.0, $result['gross']);
        $this->assertSame(9250.0, $result['net']);
        $this->assertSame(1000.0, $result['overtime']);
        $this->assertSame(500.0, $result['holiday']);
        $this->assertSame(250.0, $result['nsd']);
    }
}
