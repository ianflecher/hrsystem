<?php

namespace Tests\Unit;

use App\Support\PayPeriod;
use App\Support\PayrollCalculator;
use App\Support\Tardiness;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * The arithmetic, on its own. Payroll is the one part of this system that moves
 * money, so the rules are pinned here rather than only through the screen.
 */
class PayrollCalculationTest extends TestCase
{
    // ---------------------------------------------------------------- lateness

    public function test_five_minutes_late_costs_nothing(): void
    {
        foreach ([0, 1, 5] as $minutes) {
            $this->assertSame(0.0, Tardiness::deduction($minutes, 22000),
                "{$minutes} minutes is inside the grace period");
        }
    }

    public function test_six_to_fourteen_minutes_costs_an_hour(): void
    {
        // 22,000 / 22 days = 1,000 a day; / 8 hours = 125 an hour.
        foreach ([6, 10, 14] as $minutes) {
            $this->assertSame(125.0, Tardiness::deduction($minutes, 22000),
                "{$minutes} minutes should cost one hour");
        }
    }

    public function test_fifteen_minutes_or_more_costs_half_a_day(): void
    {
        foreach ([15, 45, 200] as $minutes) {
            $this->assertSame(500.0, Tardiness::deduction($minutes, 22000),
                "{$minutes} minutes should cost half a day");
        }
    }

    public function test_the_boundary_falls_between_five_and_six(): void
    {
        $this->assertSame(0.0, Tardiness::deduction(5, 22000));
        $this->assertGreaterThan(0.0, Tardiness::deduction(6, 22000));
        $this->assertSame(125.0, Tardiness::deduction(14, 22000));
        $this->assertSame(500.0, Tardiness::deduction(15, 22000));
    }

    public function test_arriving_early_is_not_lateness(): void
    {
        $early = Carbon::parse('2026-01-05 07:40');

        $this->assertSame(0, Tardiness::minutesLate($early, '08:00:00'));
        $this->assertSame(0.0, Tardiness::deduction(0, 22000));
    }

    public function test_lateness_cannot_be_judged_without_a_shift_or_a_clock_in(): void
    {
        $this->assertNull(Tardiness::minutesLate(Carbon::parse('2026-01-05 09:30'), null));
        $this->assertNull(Tardiness::minutesLate(null, '08:00:00'));
        $this->assertSame(0.0, Tardiness::deduction(null, 22000));
    }

    // ------------------------------------------------------------- pay periods

    public function test_a_period_is_the_first_half_or_the_second_half(): void
    {
        $first = PayPeriod::fromStart('2026-09-01');
        $this->assertSame('2026-09-01', $first->start);
        $this->assertSame('2026-09-15', $first->end);
        $this->assertFalse($first->isSecondCutoff);

        $second = PayPeriod::fromStart('2026-09-16');
        $this->assertSame('2026-09-16', $second->start);
        $this->assertSame('2026-09-30', $second->end);
        $this->assertTrue($second->isSecondCutoff);
    }

    public function test_the_second_half_ends_with_the_month_however_long_it_is(): void
    {
        $this->assertSame('2026-02-28', PayPeriod::fromStart('2026-02-16')->end);
        $this->assertSame('2024-02-29', PayPeriod::fromStart('2024-02-16')->end, 'leap year');
        $this->assertSame('2026-01-31', PayPeriod::fromStart('2026-01-16')->end);
    }

    public function test_any_date_in_a_half_resolves_to_that_half(): void
    {
        $this->assertSame('2026-09-01', PayPeriod::fromStart('2026-09-07')->start);
        $this->assertSame('2026-09-16', PayPeriod::fromStart('2026-09-22')->start);
    }

    // ---------------------------------------------------------------- payslips

    public function test_each_payslip_is_half_the_monthly_salary(): void
    {
        $this->assertSame(11000.0, PayrollCalculator::forCutoff(22000, 0, false)['gross']);
        $this->assertSame(11000.0, PayrollCalculator::forCutoff(22000, 0, true)['gross']);
    }

    public function test_contributions_fall_on_the_second_cutoff_only(): void
    {
        $first = PayrollCalculator::forCutoff(22000, 0, false);
        $this->assertSame(0.0, $first['sss']);
        $this->assertSame(0.0, $first['philhealth']);
        $this->assertSame(0.0, $first['pagibig']);

        // 22,000 sits in the 1,350 SSS bracket; PhilHealth is 4% halved = 440.
        $second = PayrollCalculator::forCutoff(22000, 0, true);
        $this->assertSame(1350.0, $second['sss']);
        $this->assertSame(440.0, $second['philhealth']);
        $this->assertSame(100.0, $second['pagibig']);
    }

    public function test_tax_uses_the_semi_monthly_brackets(): void
    {
        // First cutoff: nothing deducted before tax, so taxable is the full
        // 11,000. The semi-monthly exemption is 10,417, leaving 583 taxed at
        // 15% = 87.45.
        $first = PayrollCalculator::forCutoff(22000, 0, false);
        $this->assertEqualsWithDelta(87.45, $first['tax'], 0.01);

        // Second cutoff: 11,000 less 1,890 of contributions is 9,110, which is
        // under the exemption, so no tax at all.
        $second = PayrollCalculator::forCutoff(22000, 0, true);
        $this->assertSame(0.0, $second['tax']);
    }

    public function test_lateness_reduces_the_payslip_and_what_is_taxed(): void
    {
        $clean = PayrollCalculator::forCutoff(22000, 0, false);
        $late  = PayrollCalculator::forCutoff(22000, 500, false);

        $this->assertSame(500.0, $late['late']);
        $this->assertEqualsWithDelta($clean['net'] - 500 + ($clean['tax'] - $late['tax']), $late['net'], 0.01);

        // Time not worked was never earned, so it is not taxed either.
        $this->assertLessThan($clean['tax'], $late['tax']);
    }

    public function test_a_payslip_never_goes_negative_on_tax(): void
    {
        // Someone late enough to wipe out the cutoff still gets no negative tax.
        $c = PayrollCalculator::forCutoff(22000, 11000, false);

        $this->assertSame(0.0, $c['tax']);
        $this->assertSame(0.0, $c['taxable']);
    }

    public function test_the_note_says_when_contributions_are_absent(): void
    {
        $first = PayrollCalculator::forCutoff(22000, 0, false);
        $this->assertStringContainsString('second cutoff', PayrollCalculator::note($first));

        $second = PayrollCalculator::forCutoff(22000, 0, true);
        $this->assertStringContainsString('SSS', PayrollCalculator::note($second));
    }
}
