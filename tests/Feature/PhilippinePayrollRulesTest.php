<?php

namespace Tests\Feature;

use App\Services\NightShiftDifferential;
use App\Support\PayrollCalculator;
use App\Support\Statutory;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PhilippinePayrollRulesTest extends TestCase
{
    use DatabaseTransactions;

    public function test_sss_and_employer_contributions_are_separate(): void
    {
        $this->assertSame(1750.0, PayrollCalculator::sss(35000));
        $employer = PayrollCalculator::employerContributions(35000);
        $this->assertSame(3500.0, $employer['sss']);
        $this->assertSame(30.0, $employer['ec']);

        $cutoff = PayrollCalculator::forCutoff(35000, 0, false);
        $this->assertSame(1750.0, $cutoff['employer_sss']);
        $this->assertSame(15.0, $cutoff['employer_ec']);
        $this->assertSame(437.5, $cutoff['employer_philhealth']);
        $this->assertSame(100.0, $cutoff['employer_pagibig']);
    }

    public function test_pagibig_uses_the_two_employee_rate_bands(): void
    {
        $this->assertSame(15.0, PayrollCalculator::pagIbig(1500));
        $this->assertSame(100.0, PayrollCalculator::pagIbig(5000));
        $this->assertSame(200.0, PayrollCalculator::pagIbig(10000));
        $this->assertSame(200.0, PayrollCalculator::pagIbig(25000));
    }

    public function test_bir_semi_monthly_threshold_is_zero_below_250000_annual_equivalent(): void
    {
        $this->assertSame(0.0, PayrollCalculator::tax(10416.67));
        $this->assertSame(937.50, PayrollCalculator::tax(16667.00));
        $this->assertSame(4270.70, PayrollCalculator::tax(33333.00));
    }

    public function test_night_shift_differential_counts_the_10pm_to_6am_window(): void
    {
        $row = (object) ['time_in' => '2026-09-17 21:00:00', 'time_out' => '2026-09-18 07:00:00'];
        $amount = (new NightShiftDifferential)->forAttendance($row, 22000);
        $expected = round(8 * (22000 / 22 / 8) * 0.10, 2);
        $this->assertSame($expected, $amount);
    }


    public function test_night_shift_differential_uses_the_rule_date_without_an_undefined_variable(): void
    {
        $row = (object) ['time_in' => '2026-09-17 21:00:00', 'time_out' => '2026-09-18 07:00:00'];
        $amount = (new NightShiftDifferential)->forAttendance($row, 22000);
        $this->assertSame(round(8 * (22000 / 22 / 8) * 0.10, 2), $amount);
    }

    public function test_mwe_has_no_withholding_for_only_basic_and_qualifying_premiums(): void
    {
        $this->assertSame(0.0, PayrollCalculator::forCutoff(22000, 0, true, 1000, 500, true, 250, null, true)['tax']);
    }

    public function test_mwe_only_exempts_qualifying_pay_and_taxable_extra_is_still_taxed(): void
    {
        $result = PayrollCalculator::forCutoff(22000, 0, true, 0, 0, true, 0, null, true, 25000);
        $this->assertGreaterThan(0.0, $result['tax']);
        $this->assertSame(24075.0, $result['taxable']);
    }

    public function test_rule_snapshot_has_a_version(): void
    {
        $snapshot = Statutory::snapshot('2026-09-18');
        $this->assertNotEmpty($snapshot['version']);
        $this->assertArrayHasKey('sss', $snapshot['rules']);
        $this->assertArrayHasKey('bir', $snapshot['rules']);
    }
}


