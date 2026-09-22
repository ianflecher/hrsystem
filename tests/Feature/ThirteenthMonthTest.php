<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ThirteenthMonth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 13th month pay, against PD 851.
 *
 * One twelfth of the *basic salary earned* in the calendar year. Every word of
 * that is load-bearing: not the rate, not what was paid out, and not anything
 * that is not basic salary - which by name excludes allowances, overtime and
 * every premium.
 */
class ThirteenthMonthTest extends TestCase
{
    private int $employeeId;
    private int $userId;
    private int $year = 2019;   // Long past, so no real payroll is near it.

    protected function setUp(): void
    {
        parent::setUp();

        $n = random_int(100000, 999999);
        $u = User::create([
            'full_name' => 'Thirteenth Case', 'username' => "tm{$n}",
            'email' => "tm{$n}@example.test", 'password' => 'x', 'role' => 'employee',
        ]);
        $this->userId = $u->user_id;

        $this->employeeId = DB::table('employees')->insertGetId([
            'user_id' => $u->user_id, 'job_title' => 'Operator', 'hire_date' => '2015-01-01',
            'salary' => 15000, 'allowance' => 5000, 'pay_basis' => 'monthly', 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        DB::table('hr_payroll')->where('employee_id', $this->employeeId)->delete();
        DB::table('employees')->where('employee_id', $this->employeeId)->delete();
        DB::table('users')->where('user_id', $this->userId)->delete();

        parent::tearDown();
    }

    /** One cutoff's payslip. Defaults to a plain one: basic only, nothing else. */
    private function payslip(string $start, string $end, array $figures = []): void
    {
        $f = $figures + [
            'basic_pay' => 7500, 'allowance' => 0, 'overtime_pay' => 0, 'holiday_pay' => 0,
            'nsd_pay' => 0, 'time_deduction' => 0, 'other_taxable_compensation' => 0,
            'kind' => 'regular', 'status' => 'calculated',
        ];

        $gross = $f['basic_pay'] + $f['allowance'] + $f['overtime_pay']
            + $f['holiday_pay'] + $f['nsd_pay'] + $f['other_taxable_compensation'];

        DB::table('hr_payroll')->insert($f + [
            'employee_id' => $this->employeeId,
            'period_start' => $start, 'period_end' => $end,
            'gross_pay' => $gross, 'deductions' => 0, 'net_pay' => $gross - $f['time_deduction'],
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** A full year of plain cutoffs at 7,500 each: 180,000 basic earned. */
    private function aFullYear(array $figures = []): void
    {
        for ($m = 1; $m <= 12; $m++) {
            $mm = str_pad((string) $m, 2, '0', STR_PAD_LEFT);
            $last = date('t', mktime(0, 0, 0, $m, 1, $this->year));

            $this->payslip("{$this->year}-{$mm}-01", "{$this->year}-{$mm}-15", $figures);
            $this->payslip("{$this->year}-{$mm}-16", "{$this->year}-{$mm}-{$last}", $figures);
        }
    }

    private function figures(): array
    {
        return (new ThirteenthMonth)->forEmployee($this->employeeId, $this->year);
    }

    public function test_a_full_year_pays_one_months_basic_salary(): void
    {
        $this->aFullYear();

        $f = $this->figures();

        $this->assertEquals(180000.00, $f['basic']);
        $this->assertEquals(15000.00, $f['amount'], 'a full year should pay one month');
        $this->assertSame(24, $f['payslips']);
    }

    /**
     * The allowance is in gross_pay because it is money the person receives,
     * but PD 851 counts basic salary and names allowances among the things it
     * excludes. Leaving it in paid a twelfth of it out again every December.
     */
    public function test_the_allowance_is_not_part_of_the_base(): void
    {
        $this->aFullYear(['allowance' => 2500]);

        $f = $this->figures();

        $this->assertEquals(180000.00, $f['basic'], 'the allowance inflated the base');
        $this->assertEquals(15000.00, $f['amount']);
    }

    public function test_overtime_the_holiday_premium_and_the_night_differential_are_excluded(): void
    {
        $this->aFullYear(['overtime_pay' => 900, 'holiday_pay' => 600, 'nsd_pay' => 300]);

        $this->assertEquals(15000.00, $this->figures()['amount'],
            'premium pay was treated as basic salary');
    }

    public function test_everything_excluded_at_once_still_leaves_only_the_basic(): void
    {
        $this->aFullYear([
            'allowance' => 2500, 'overtime_pay' => 900, 'holiday_pay' => 600,
            'nsd_pay' => 300, 'other_taxable_compensation' => 1000,
        ]);

        $this->assertEquals(15000.00, $this->figures()['amount']);
    }

    /**
     * Days not worked come out of it: somebody absent for a fortnight earned
     * less basic salary, so they earn a smaller 13th month. That is the rule
     * working, not a deduction from the 13th month.
     */
    public function test_unpaid_days_lower_it_because_less_was_earned(): void
    {
        $this->aFullYear();

        // One cutoff where they were away half the time.
        DB::table('hr_payroll')->where('employee_id', $this->employeeId)
            ->where('period_start', "{$this->year}-03-01")
            ->update(['time_deduction' => 3750]);

        $f = $this->figures();

        $this->assertEquals(176250.00, $f['basic']);
        $this->assertEquals(round(176250 / 12, 2), $f['amount']);
    }

    /** Hired in July: half a year earned, half a 13th month. */
    public function test_somebody_hired_mid_year_is_paid_pro_rata(): void
    {
        for ($m = 7; $m <= 12; $m++) {
            $mm = str_pad((string) $m, 2, '0', STR_PAD_LEFT);
            $last = date('t', mktime(0, 0, 0, $m, 1, $this->year));
            $this->payslip("{$this->year}-{$mm}-01", "{$this->year}-{$mm}-15");
            $this->payslip("{$this->year}-{$mm}-16", "{$this->year}-{$mm}-{$last}");
        }

        $f = $this->figures();

        $this->assertEquals(90000.00, $f['basic']);
        $this->assertEquals(7500.00, $f['amount'], 'half a year should pay half a month');
    }

    /** Somebody who left is still owed what they earned before they went. */
    public function test_somebody_who_has_left_is_still_owed_theirs(): void
    {
        $this->aFullYear();
        DB::table('employees')->where('employee_id', $this->employeeId)->update(['status' => 'inactive']);

        $row = collect((new ThirteenthMonth)->forYear($this->year))
            ->firstWhere('employee_id', $this->employeeId);

        $this->assertNotNull($row, 'a separated employee was dropped from the run');
        $this->assertEquals(15000.00, $row['amount']);
    }

    public function test_nothing_is_deducted_from_it(): void
    {
        $this->aFullYear();

        (new ThirteenthMonth)->generate($this->year);

        $slip = DB::table('hr_payroll')->where('employee_id', $this->employeeId)
            ->where('kind', ThirteenthMonth::KIND)->first();

        $this->assertNotNull($slip);
        $this->assertEquals(0, $slip->deductions, 'contributions were taken out of the 13th month');
        $this->assertEquals(0, $slip->sss ?? 0);
        $this->assertEquals(0, $slip->philhealth ?? 0);
        $this->assertEquals(0, $slip->pagibig ?? 0);
        $this->assertEquals(0, $slip->tax ?? 0);
        $this->assertEquals($slip->gross_pay, $slip->net_pay, 'it must arrive whole');

        // On or before 24 December.
        $this->assertSame("{$this->year}-12-24", $slip->period_start);
    }

    /** Last year's 13th month is not this year's salary. */
    public function test_the_13th_month_is_never_its_own_base(): void
    {
        $this->aFullYear();
        (new ThirteenthMonth)->generate($this->year);

        $this->assertEquals(15000.00, $this->figures()['amount'],
            'the 13th month counted itself as basic salary earned');
    }

    public function test_it_is_recorded_once_however_often_the_run_is_pressed(): void
    {
        $this->aFullYear();

        $this->assertSame(1, (new ThirteenthMonth)->generate($this->year));
        $this->assertSame(0, (new ThirteenthMonth)->generate($this->year), 'it paid twice');

        $this->assertSame(1, DB::table('hr_payroll')->where('employee_id', $this->employeeId)
            ->where('kind', ThirteenthMonth::KIND)->count());
    }

    public function test_the_excess_over_90000_is_flagged_rather_than_guessed_at(): void
    {
        // A year big enough to clear the ceiling: 1,200,000 basic -> 100,000.
        $this->aFullYear(['basic_pay' => 50000]);

        $f = $this->figures();

        $this->assertEquals(100000.00, $f['amount']);
        $this->assertEquals(10000.00, $f['taxableExcess'],
            'only the excess above 90,000 is taxable');

        (new ThirteenthMonth)->generate($this->year);
        $slip = DB::table('hr_payroll')->where('employee_id', $this->employeeId)
            ->where('kind', ThirteenthMonth::KIND)->first();

        $this->assertStringContainsString('10,000.00', $slip->notes);
        $this->assertStringContainsString('90,000', $slip->notes);

        // Flagged, not withheld - getting it right needs the whole year's pay.
        $this->assertEquals(0, $slip->deductions);
    }

    public function test_nothing_earned_means_nobody_is_paid(): void
    {
        $f = $this->figures();

        $this->assertEquals(0.0, $f['amount']);
        $this->assertSame(0, (new ThirteenthMonth)->generate($this->year));
    }
}
