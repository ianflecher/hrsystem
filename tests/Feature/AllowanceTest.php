<?php

namespace Tests\Feature;

use App\Support\PayrollCalculator;
use Tests\TestCase;

/**
 * A de minimis allowance is paid in full: it reaches the employee untouched,
 * is not taxable income, and is not part of the SSS, PhilHealth or Pag-IBIG
 * base. Getting any of those wrong means withholding the wrong amount.
 */
class AllowanceTest extends TestCase
{
    public function test_an_allowance_is_paid_in_full_and_taxed_on_nothing(): void
    {
        $without = PayrollCalculator::forCutoff(20000);
        $with    = PayrollCalculator::forCutoff(20000, allowance: 1500);

        // It is money received, so it belongs in gross...
        $this->assertEquals(round($without['gross'] + 1500, 2), $with['gross']);

        // ...and it reaches net in full, nothing shaved off it.
        $this->assertEquals(round($without['net'] + 1500, 2), $with['net']);

        // Not taxable, so neither the taxable figure nor the tax moves.
        $this->assertEquals($without['taxable'], $with['taxable'], 'the allowance was taxed');
        $this->assertEquals($without['tax'], $with['tax'], 'tax changed because of an allowance');

        // Not contributory either: basic pay alone is the base.
        foreach (['sss', 'philhealth', 'pagibig', 'employer_sss', 'employer_philhealth', 'employer_pagibig'] as $k) {
            $this->assertEquals($without[$k], $with[$k], "{$k} changed because of an allowance");
        }

        $this->assertEquals(1500, $with['allowance']);
    }

    public function test_basic_pay_still_carries_tax_and_contributions(): void
    {
        // The same total, split differently: more basic means more withheld.
        // Well above the exemption, or the tax is zero either way and the
        // comparison proves nothing.
        $mostlyBasic = PayrollCalculator::forCutoff(65000);
        $withAllowance = PayrollCalculator::forCutoff(60000, allowance: 2500);

        $this->assertGreaterThan($withAllowance['tax'], $mostlyBasic['tax'],
            'putting the money in basic should be taxed more, not less');

        // And the person is better off for the same cost to the company.
        $this->assertGreaterThan($mostlyBasic['net'], $withAllowance['net']);

        // Contributions want a lower band: both of the figures above sit over
        // the SSS ceiling, where the contribution caps out and stops telling
        // you anything.
        $basicOnly = PayrollCalculator::forCutoff(21500);
        $split = PayrollCalculator::forCutoff(20000, allowance: 750);

        $this->assertGreaterThan($split['sss'], $basicOnly['sss'],
            'contributions should follow basic pay, not the allowance');
    }

    public function test_it_holds_for_daily_paid_staff_too(): void
    {
        $without = PayrollCalculator::forNonMonthlyCutoff(7000, 14000);
        $with    = PayrollCalculator::forNonMonthlyCutoff(7000, 14000, allowance: 900);

        $this->assertEquals(round($without['gross'] + 900, 2), $with['gross']);
        $this->assertEquals(round($without['net'] + 900, 2), $with['net']);
        $this->assertEquals($without['tax'], $with['tax']);
        $this->assertEquals($without['sss'], $with['sss']);
    }

    public function test_a_negative_allowance_cannot_be_used_to_shave_pay(): void
    {
        $plain = PayrollCalculator::forCutoff(20000);
        $odd   = PayrollCalculator::forCutoff(20000, allowance: -5000);

        $this->assertEquals($plain['gross'], $odd['gross']);
        $this->assertEquals(0, $odd['allowance']);
    }
}
