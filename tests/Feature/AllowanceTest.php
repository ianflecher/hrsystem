<?php

namespace Tests\Feature;

use App\Support\PayrollCalculator;
use Tests\TestCase;

/**
 * The allowance is ordinary compensation: it is taxed, it is contributory, and
 * it is part of the 13th month. It keeps its own line on the payslip so people
 * can see what their pay is made of, but nothing treats it differently.
 *
 * This is the company's choice rather than the only lawful one - a genuine de
 * minimis benefit may be excluded from all three. Including it withholds more
 * and pays more into SSS and PhilHealth, which is the safe direction: it buys
 * a larger pension and better benefits, and it cannot be an under-remittance.
 *
 * The contribution base is monthly and is passed separately, because the
 * allowance argument here is only this cutoff's half of it.
 */
class AllowanceTest extends TestCase
{
    public function test_an_allowance_is_taxed_and_contributory(): void
    {
        // 20,000 basic plus a 3,000 monthly allowance, arriving 1,500 per
        // cutoff. The base charged on is the whole 23,000.
        $without = PayrollCalculator::forCutoff(20000);
        $with    = PayrollCalculator::forCutoff(20000, allowance: 1500, monthlyStatutoryBase: 23000);

        // It is money received, so it belongs in gross.
        $this->assertEquals(round($without['gross'] + 1500, 2), $with['gross']);

        // Taxable now, so the taxable figure rises with it.
        $this->assertGreaterThan($without['taxable'], $with['taxable'],
            'the allowance was left out of taxable income');

        // Contributory too: a higher base means higher contributions.
        foreach (['sss', 'philhealth'] as $k) {
            $this->assertGreaterThan($without[$k], $with[$k],
                "{$k} ignored the allowance");
        }

        // Pag-IBIG caps its base at 10,000, so at this salary it cannot move
        // either way. It follows the allowance below the cap.
        $this->assertEquals($without['pagibig'], $with['pagibig']);
        $small = PayrollCalculator::forCutoff(8000, allowance: 500);
        $smallWithBase = PayrollCalculator::forCutoff(8000, allowance: 500, monthlyStatutoryBase: 9000);
        $this->assertGreaterThan($small['pagibig'], $smallWithBase['pagibig'],
            'Pag-IBIG ignored the allowance below its cap');

        // And the employer pays more alongside them.
        $this->assertGreaterThan($without['employer_sss'], $with['employer_sss']);

        // Still reported separately, so a payslip can say what the pay is of.
        $this->assertEquals(1500, $with['allowance']);
    }

    /**
     * Because contributions now follow the total, moving money between basic
     * and allowance no longer changes what is withheld. That is the point:
     * the split is presentational, not a way to lower anybody's deductions.
     */
    public function test_the_split_between_basic_and_allowance_changes_nothing(): void
    {
        // 24,000 all as basic, against 21,000 basic plus a 3,000 allowance.
        $allBasic = PayrollCalculator::forCutoff(24000);
        $split    = PayrollCalculator::forCutoff(21000, allowance: 1500, monthlyStatutoryBase: 24000);

        foreach (['sss', 'philhealth', 'pagibig', 'employer_sss', 'employer_philhealth', 'employer_pagibig'] as $k) {
            $this->assertEquals($allBasic[$k], $split[$k],
                "{$k} still depends on how the pay is labelled");
        }

        // Same money in, same tax on it, same money out.
        $this->assertEquals($allBasic['gross'], $split['gross']);
        $this->assertEquals($allBasic['taxable'], $split['taxable']);
        $this->assertEquals($allBasic['tax'], $split['tax']);
        $this->assertEquals($allBasic['net'], $split['net']);
    }

    public function test_it_holds_for_daily_paid_staff_too(): void
    {
        $without = PayrollCalculator::forNonMonthlyCutoff(7000, 14000);
        $with    = PayrollCalculator::forNonMonthlyCutoff(7000, 15800, allowance: 900);   // base carries the allowance

        $this->assertEquals(round($without['gross'] + 900, 2), $with['gross']);
        $this->assertGreaterThan($without['taxable'], $with['taxable']);
        $this->assertGreaterThan($without['sss'], $with['sss'],
            'the allowance was left out of the contribution base');
    }

    public function test_a_negative_allowance_cannot_be_used_to_shave_pay(): void
    {
        $plain = PayrollCalculator::forCutoff(20000);
        $odd   = PayrollCalculator::forCutoff(20000, allowance: -5000);

        $this->assertEquals($plain['gross'], $odd['gross']);
        $this->assertEquals(0, $odd['allowance']);
    }
}
