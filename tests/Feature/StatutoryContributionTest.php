<?php

namespace Tests\Feature;

use App\Support\PayrollCalculator as P;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The contribution schedules, checked against the published tables rather than
 * against whatever the code happened to do.
 *
 * These are somebody's wage, and a bracket that is one step out is invisible
 * on a payslip - it just quietly takes too much every month.
 */
class StatutoryContributionTest extends TestCase
{
    // ---------------------------------------------------------------- SSS

    /**
     * Each MSC range sits around its credit: 19,750-20,249.99 is all 20,000.
     * Rounding up instead over-charged everybody in a range's lower half.
     */
    public static function mscCases(): array
    {
        return [
            'below the floor'          => [4000.0, 5000.0],
            'still the floor at 5,249' => [5249.0, 5000.0],
            'floor ends at 5,250'      => [5250.0, 5500.0],
            'exactly on a credit'      => [20000.0, 20000.0],
            'lower half rounds down'   => [20100.0, 20000.0],
            'last of the lower half'   => [20249.0, 20000.0],
            'upper half rounds up'     => [20250.0, 20500.0],
            'ceiling starts at 34,750' => [34750.0, 35000.0],
            'above the ceiling'        => [80000.0, 35000.0],
        ];
    }

    #[DataProvider('mscCases')]
    public function test_the_monthly_salary_credit_follows_the_published_ranges(float $salary, float $expected): void
    {
        $this->assertEquals($expected, P::msc($salary),
            "a salary of {$salary} landed in the wrong MSC bracket");
    }

    public function test_sss_is_five_percent_of_the_credit_and_caps_at_1750(): void
    {
        $this->assertEquals(250.00, P::sss(4000), 'the floor credit is not 5,000');
        $this->assertEquals(1000.00, P::sss(20000));
        $this->assertEquals(1750.00, P::sss(35000), 'the maximum employee share is 1,750');
        $this->assertEquals(1750.00, P::sss(200000), 'the ceiling does not hold');
    }

    public function test_the_employer_pays_twice_the_employee_plus_the_ec_fee(): void
    {
        // Below a 15,000 credit the EC fee is 10; at or above it is 30.
        $low = P::employerContributions(12000);
        $this->assertEquals(1200.00, $low['sss'], 'the employer rate is not 10%');
        $this->assertEquals(10.00, $low['ec']);

        $high = P::employerContributions(20000);
        $this->assertEquals(2000.00, $high['sss']);
        $this->assertEquals(30.00, $high['ec']);

        // 15% in total, the employee carrying a third of it.
        $this->assertEquals(round(20000 * 0.15, 2), round(P::sss(20000) + $high['sss'], 2));
    }

    // ---------------------------------------------------------- PhilHealth

    public function test_philhealth_is_half_of_five_percent_within_its_bounds(): void
    {
        $this->assertEquals(250.00, P::philHealth(8000), 'below the 10,000 floor it should still be 250');
        $this->assertEquals(250.00, P::philHealth(10000));
        $this->assertEquals(500.00, P::philHealth(20000));
        $this->assertEquals(2500.00, P::philHealth(100000), 'the ceiling share is 2,500');
        $this->assertEquals(2500.00, P::philHealth(250000), 'the 100,000 ceiling does not hold');

        // The employer matches, so the total is the full 5%.
        $this->assertEquals(500.00, P::employerContributions(20000)['philhealth']);
    }

    // ------------------------------------------------------------ Pag-IBIG

    public function test_pag_ibig_caps_both_shares_at_200(): void
    {
        $this->assertEquals(100.00, P::pagIbig(5000));
        $this->assertEquals(200.00, P::pagIbig(10000), 'the fund salary cap is 10,000');
        $this->assertEquals(200.00, P::pagIbig(60000), 'the cap does not hold above it');

        $this->assertEquals(200.00, P::employerContributions(60000)['pagibig'],
            'the employer share should match, capped the same way');
    }

    // ----------------------------------------------------------------- tax

    /**
     * The brackets are held per cutoff, so a monthly figure is halved. The
     * exemption is 20,833 a month, which is 10,416.50 on a semi-monthly slip.
     */
    public function test_nothing_is_withheld_at_or_below_the_exemption(): void
    {
        $this->assertEquals(0.0, P::tax(10417.0), 'tax was withheld inside the exempt band');
        $this->assertEquals(0.0, P::tax(5000.0));
    }

    public function test_the_first_taxed_band_is_fifteen_percent_of_the_excess(): void
    {
        // 12,417 is 2,000 over the exempt line.
        $this->assertEquals(round(2000 * 0.15, 2), P::tax(12417.0));
    }

    public function test_the_higher_bands_add_a_fixed_amount_to_the_excess(): void
    {
        // Monthly 1,875 + 20% over 33,333 becomes 937.50 + 20% over 16,667.
        $this->assertEquals(round(937.50 + 1000 * 0.20, 2), P::tax(17667.0));

        // Monthly 8,541.67 + 25% over 66,666, as BIR prints it per cutoff.
        $this->assertEquals(round(4270.70 + 1000 * 0.25, 2), P::tax(34333.0));
    }

    // --------------------------------------------------- split across cutoffs

    /**
     * Every premium is split the same way, and the two cutoffs come to exactly
     * what the monthly schedule says.
     *
     * A half-cutoff deduction read as a whole month's looks like an
     * over-charge, and a premium split one way while another is not would
     * either double-charge the month or swing the net between paydays. This
     * asserts neither happens: the divisor is applied to all three, and
     * nothing is left over.
     */
    public function test_the_two_cutoffs_come_to_the_monthly_schedule(): void
    {
        // 15,000 basic plus a 5,000 allowance. SSS and PhilHealth read the
        // basic; Pag-IBIG reads the 20,000 but caps its base at 10,000.
        $basic = 15000.0;
        $compensation = 20000.0;
        $allowance = 2500.0;   // half the monthly 5,000

        $first  = P::forCutoff($basic, isSecondCutoff: false, allowance: $allowance, monthlyCompensation: $compensation);
        $second = P::forCutoff($basic, isSecondCutoff: true,  allowance: $allowance, monthlyCompensation: $compensation);

        foreach (['sss' => 750.00, 'philhealth' => 375.00, 'pagibig' => 200.00] as $k => $monthly) {
            $this->assertEquals($monthly, round($first[$k] + $second[$k], 2),
                "{$k} over the month does not match the monthly schedule");

            // Split evenly, so nobody's net swings between paydays.
            $this->assertEquals($first[$k], $second[$k],
                "{$k} is charged differently on the two cutoffs");

            $this->assertEquals(round($monthly / 2, 2), $first[$k],
                "{$k} was not halved like the others");
        }

        // And the month's pay is the monthly compensation, not more.
        $this->assertEquals(20000.00, round($first['gross'] + $second['gross'], 2));
    }

    /**
     * The brackets are read against the monthly salary, never against one
     * cutoff's pay. Reading 7,500 instead of 15,000 would put somebody at the
     * floor of the SSS table and under-remit half their contribution.
     */
    public function test_the_brackets_are_read_monthly_not_per_cutoff(): void
    {
        $c = P::forCutoff(15000, isSecondCutoff: true, allowance: 2500, monthlyCompensation: 20000);

        // Half of the 750 a month that a 15,000 credit earns.
        $this->assertEquals(375.00, $c['sss']);

        // Read off the 7,500 cutoff instead, the credit would be 7,500 and the
        // half-cutoff figure 187.50 - which is what this must not be.
        $this->assertNotEquals(round(P::sss(7500) / 2, 2), $c['sss'],
            'the bracket was read off one cutoff rather than the month');

        // PhilHealth has a 10,000 floor, so reading 7,500 would hit it and
        // give the floor premium rather than the one actually due.
        $this->assertEquals(187.50, $c['philhealth']);
        $this->assertNotEquals(round(P::philHealth(7500) / 2, 2), $c['philhealth']);
    }

    // ------------------------------------------------------ all together

    /**
     * Contributions come off before tax, and the allowance is inside all of
     * them: this company treats it as ordinary compensation, so it is charged
     * on like any other pay.
     */
    public function test_a_whole_cutoff_hangs_together(): void
    {
        // 20,000 basic plus a 4,000 monthly allowance: 2,000 this cutoff.
        // SSS and PhilHealth read the 20,000; Pag-IBIG reads 24,000 and caps.
        $c = P::forCutoff(monthlySalary: 20000, isSecondCutoff: true,
            allowance: 2000, monthlyCompensation: 24000);

        // Half the month's contributions, because the company splits them.
        $this->assertEquals(500.00, $c['sss'], 'half of 1,000 on a 20,000 credit');
        $this->assertEquals(250.00, $c['philhealth'], 'half of 500');
        $this->assertEquals(100.00, $c['pagibig'], 'half of 200, capped at a 10,000 base');

        $this->assertEquals(10000.00, $c['basic']);
        $this->assertEquals(12000.00, $c['gross'], 'the allowance belongs in gross');

        // Taxable is the whole gross less contributions: the allowance is
        // taxed even though SSS and PhilHealth never saw it.
        $this->assertEquals(12000 - 850, $c['taxable'],
            'the allowance was left untaxed, or contributions were not deducted first');

        // 11,150 clears the 10,417 exemption, so tax is due on the excess.
        $this->assertEquals(round((11150 - 10417) * 0.15, 2), $c['tax']);

        $this->assertEquals(round(850 + $c['tax'], 2), $c['deductions']);
        $this->assertEquals(round(12000 - $c['deductions'], 2), $c['net']);
    }
}
