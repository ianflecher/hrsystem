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

    public function test_contributions_are_split_across_the_two_cutoffs(): void
    {
        // 22,000 a month: SSS is 5% of the 22,000 salary credit = 1,100;
        // PhilHealth is 5% halved with the employer = 550; Pag-IBIG is 2% of
        // compensation capped at 10,000 = 200. Half of each lands per cutoff.
        $first = PayrollCalculator::forCutoff(22000, 0, false);
        $second = PayrollCalculator::forCutoff(22000, 0, true);

        foreach ([$first, $second] as $cutoff) {
            $this->assertSame(550.0, $cutoff['sss']);
            $this->assertSame(275.0, $cutoff['philhealth']);
            $this->assertSame(100.0, $cutoff['pagibig']);
        }
    }

    public function test_the_whole_month_can_be_taken_on_one_cutoff_instead(): void
    {
        // Timing is a company decision, so both are supported. Under
        // 'second_cutoff' the first payslip carries none and the second all.
        $this->withTiming('second_cutoff', function () {
            $first = PayrollCalculator::forCutoff(22000, 0, false);
            $this->assertSame(0.0, $first['sss']);
            $this->assertSame(0.0, $first['philhealth']);
            $this->assertSame(0.0, $first['pagibig']);

            $second = PayrollCalculator::forCutoff(22000, 0, true);
            $this->assertSame(1100.0, $second['sss']);
            $this->assertSame(550.0, $second['philhealth']);
            $this->assertSame(200.0, $second['pagibig']);
        });
    }

    public function test_philhealth_is_the_premium_split_with_the_employer(): void
    {
        // The rate is 5% of the monthly salary, half of it the employee's.
        $this->assertSame(800.0, PayrollCalculator::philHealth(32000));
        $this->assertSame(400.0, PayrollCalculator::philHealth(16000));
    }

    public function test_philhealth_has_a_floor_and_a_ceiling(): void
    {
        // Below the floor everybody pays the floor; above the ceiling, the
        // ceiling. Neither was applied before, at any salary.
        $this->assertSame(250.0, PayrollCalculator::philHealth(4000));
        $this->assertSame(PayrollCalculator::philHealth(100000), PayrollCalculator::philHealth(250000));
    }

    public function test_sss_follows_the_salary_credit_and_stops_at_the_ceiling(): void
    {
        // The credit steps in 500s, so a salary between two steps contributes
        // at the higher one...
        $this->assertSame(PayrollCalculator::sss(24500), PayrollCalculator::sss(24300));

        // ...and above the ceiling everybody pays the same.
        $this->assertSame(1750.0, PayrollCalculator::sss(35000));
        $this->assertSame(1750.0, PayrollCalculator::sss(90000));
    }

    public function test_pagibig_is_a_rate_on_capped_compensation(): void
    {
        // 2% of compensation, capped at the 10,000 maximum fund salary, so
        // anybody earning above it pays a flat 200 a month. The cap was 5,000
        // until February 2024, when Pag-IBIG doubled it; these figures were
        // still the old ones.
        $this->assertSame(200.0, PayrollCalculator::pagIbig(32000));
        $this->assertSame(60.0, PayrollCalculator::pagIbig(3000));
    }

    public function test_tax_uses_the_semi_monthly_brackets(): void
    {
        // 11,000 gross less 875 of contributions is 10,125, under the 10,417
        // exemption, so nothing is withheld.
        $this->assertSame(0.0, PayrollCalculator::forCutoff(22000, 0, false)['tax']);

        // Somebody paid enough to clear the exemption after contributions is.
        $this->assertGreaterThan(0.0, PayrollCalculator::forCutoff(40000, 0, false)['tax']);
    }

    public function test_lateness_reduces_the_payslip_and_what_is_taxed(): void
    {
        // A salary that actually clears the exemption, or there is no tax for
        // the deduction to reduce and the test proves nothing.
        $clean = PayrollCalculator::forCutoff(40000, 0, false);
        $late  = PayrollCalculator::forCutoff(40000, 500, false);

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

    /**
     * The note carries what the figures cannot, and nothing they already say.
     *
     * It used to restate SSS, PhilHealth, Pag-IBIG, tax and the holiday
     * premium, all of which are itemised lines on the payslip a few
     * centimetres above - so the note grew long enough to push the total off
     * a phone screen while telling nobody anything new.
     */
    public function test_the_note_does_not_restate_what_the_payslip_itemises(): void
    {
        $note = PayrollCalculator::note(PayrollCalculator::forCutoff(22000, 0, true));

        foreach (['SSS', 'PhilHealth', 'Pag-IBIG', 'Tax'] as $itemised) {
            $this->assertStringNotContainsString($itemised, $note,
                "{$itemised} has a line of its own; the note should not repeat it");
        }
    }

    public function test_the_note_explains_a_contribution_that_is_absent(): void
    {
        // A zero needs explaining in a way a figure cannot: nothing was taken
        // because of when contributions fall, not because none are owed.
        $this->withTiming('second_cutoff', function () {
            $first = PayrollCalculator::forCutoff(22000, 0, false);
            $this->assertStringContainsString('second cutoff', PayrollCalculator::note($first));
        });
    }

    public function test_the_note_gives_the_days_behind_a_deduction(): void
    {
        // The payslip shows one combined figure for lost time; only the note
        // says how many days it was.
        $c = PayrollCalculator::forCutoff(20000, 1363.64);
        $note = PayrollCalculator::note($c, [
            'absentDays' => 2, 'absence' => 1363.64,
            'unpaidLeaveDays' => 0, 'lateDays' => 0, 'undertimeDays' => 0, 'leaveDays' => 0,
        ]);

        $this->assertStringContainsString('Absent (2 days)', $note);
    }

    /**
     * Runs something with a different contribution timing configured, and puts
     * the setting back afterwards however it ends.
     */
    private function withTiming(string $timing, callable $test): void
    {
        $app = \Illuminate\Container\Container::getInstance();
        $had = $app->bound('config') ? $app->make('config')->get('statutory.timing') : null;

        if ($app->bound('config')) {
            $app->make('config')->set('statutory.timing', $timing);
        } else {
            // No application: give the calculator a config to read.
            $config = new \Illuminate\Config\Repository(['statutory' => ['timing' => $timing]]);
            $app->instance('config', $config);
        }

        try {
            $test();
        } finally {
            if ($had === null) {
                $app->forgetInstance('config');
            } else {
                $app->make('config')->set('statutory.timing', $had);
            }
        }
    }
}
