<?php

namespace Tests\Feature;

use App\Support\PayrollCalculator;
use Tests\TestCase;

/**
 * Where the allowance counts and where it does not.
 *
 * There is no single answer, which is the whole difficulty: each schedule
 * names its own base, and one base for all of them is wrong in one direction
 * or the other every time.
 *
 *   SSS         basic salary alone
 *   PhilHealth  basic salary alone
 *   Pag-IBIG    total compensation - but capped at 10,000, so above that
 *               salary the allowance cannot move it either
 *   tax         basic plus any allowance over the de minimis limits
 *   13th month  basic salary alone (see ThirteenthMonthTest)
 *
 * $monthlyCompensation is basic plus regular allowances and is passed monthly,
 * because the schedules are monthly while everything else here is one cutoff.
 */
class AllowanceTest extends TestCase
{
    public function test_sss_and_philhealth_ignore_the_allowance(): void
    {
        // The same 15,000 basic, once alone and once with a 5,000 allowance.
        $without = PayrollCalculator::forCutoff(15000);
        $with    = PayrollCalculator::forCutoff(15000, allowance: 2500, monthlyCompensation: 20000);

        $this->assertEquals($without['sss'], $with['sss'],
            'SSS moved because of an allowance');
        $this->assertEquals($without['philhealth'], $with['philhealth'],
            'PhilHealth moved because of an allowance');

        // The employer's shares follow the same bases.
        $this->assertEquals($without['employer_sss'], $with['employer_sss']);
        $this->assertEquals($without['employer_philhealth'], $with['employer_philhealth']);

        // 15,000 basic: 750 SSS and 375 PhilHealth a month, halved per cutoff.
        $this->assertEquals(375.00, $with['sss']);
        $this->assertEquals(187.50, $with['philhealth']);
    }

    /**
     * Pag-IBIG legally reads total compensation, and practically never sees
     * the allowance: the base is capped at 10,000, so anybody whose basic pay
     * alone reaches that is already at the 200 maximum.
     */
    public function test_pagibig_reads_compensation_but_the_cap_usually_hides_it(): void
    {
        $atTheCap = PayrollCalculator::forCutoff(15000, allowance: 2500, monthlyCompensation: 20000);
        $this->assertEquals(100.00, $atTheCap['pagibig'], 'half of the 200 maximum');

        // Below the cap it does follow the allowance: 8,000 basic is 160 a
        // month on its own, and 9,000 of compensation is 180.
        $basicOnly = PayrollCalculator::forCutoff(8000);
        $withAllowance = PayrollCalculator::forCutoff(8000, allowance: 500, monthlyCompensation: 9000);

        $this->assertEquals(80.00, $basicOnly['pagibig']);
        $this->assertEquals(90.00, $withAllowance['pagibig'],
            'Pag-IBIG ignored the allowance below its cap');
    }

    /** Tax is the one that does see it: the allowance stays in taxable pay. */
    public function test_the_allowance_is_taxable(): void
    {
        $without = PayrollCalculator::forCutoff(15000);
        $with    = PayrollCalculator::forCutoff(15000, allowance: 2500, monthlyCompensation: 20000);

        $this->assertEquals(round($without['gross'] + 2500, 2), $with['gross']);
        $this->assertEquals(round($without['taxable'] + 2500, 2), $with['taxable'],
            'the allowance was left out of taxable pay');

        // Still its own line, so a payslip can say what the pay is made of.
        $this->assertEquals(2500, $with['allowance']);
    }

    /**
     * Because SSS and PhilHealth read basic pay alone, moving money out of
     * basic and into an allowance lowers both. That is lawful for a genuine de
     * minimis benefit and not for salary under another name, and it is why the
     * split is a decision rather than a presentation choice.
     */
    public function test_moving_pay_into_an_allowance_lowers_sss_and_philhealth(): void
    {
        $allBasic = PayrollCalculator::forCutoff(20000);
        $split    = PayrollCalculator::forCutoff(15000, allowance: 2500, monthlyCompensation: 20000);

        $this->assertGreaterThan($split['sss'], $allBasic['sss']);
        $this->assertGreaterThan($split['philhealth'], $allBasic['philhealth']);

        // Same money either way, and taxed the same either way.
        $this->assertEquals($allBasic['gross'], $split['gross']);

        // So the person takes home more, and buys less pension with it.
        $this->assertGreaterThan($allBasic['net'], $split['net']);
    }

    public function test_it_holds_for_daily_paid_staff_too(): void
    {
        $without = PayrollCalculator::forNonMonthlyCutoff(7000, 14000);
        $with    = PayrollCalculator::forNonMonthlyCutoff(7000, 14000, allowance: 900, monthlyCompensation: 15800);

        $this->assertEquals(round($without['gross'] + 900, 2), $with['gross']);
        $this->assertEquals($without['sss'], $with['sss'], 'SSS moved because of an allowance');
        $this->assertEquals($without['philhealth'], $with['philhealth']);
        $this->assertGreaterThan($without['taxable'], $with['taxable']);
    }

    public function test_a_negative_allowance_cannot_be_used_to_shave_pay(): void
    {
        $plain = PayrollCalculator::forCutoff(20000);
        $odd   = PayrollCalculator::forCutoff(20000, allowance: -5000);

        $this->assertEquals($plain['gross'], $odd['gross']);
        $this->assertEquals(0, $odd['allowance']);
    }
}
