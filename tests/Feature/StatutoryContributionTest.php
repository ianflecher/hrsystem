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

    // ------------------------------------------------------ all together

    /**
     * Contributions come off before tax, and the allowance is outside every
     * one of them: de minimis pay is not taxable and not part of the base.
     */
    public function test_a_whole_cutoff_hangs_together(): void
    {
        $c = P::forCutoff(monthlySalary: 20000, isSecondCutoff: true, allowance: 2000);

        // Half the month's contributions, because the company splits them.
        $this->assertEquals(500.00, $c['sss'], 'half of 1,000');
        $this->assertEquals(250.00, $c['philhealth'], 'half of 500');
        $this->assertEquals(100.00, $c['pagibig'], 'half of 200');

        $this->assertEquals(10000.00, $c['basic']);
        $this->assertEquals(12000.00, $c['gross'], 'the allowance belongs in gross');

        // Taxable is basic less contributions - the allowance is not in it.
        $this->assertEquals(10000 - 850, $c['taxable'],
            'the allowance was taxed, or contributions were not deducted first');

        // And that lands under the exemption, so nothing is withheld.
        $this->assertEquals(0.0, $c['tax']);

        $this->assertEquals(850.00, $c['deductions']);
        $this->assertEquals(11150.00, $c['net'], 'the allowance must reach net untouched');
    }
}
