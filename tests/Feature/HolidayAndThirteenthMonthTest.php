<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\HolidayPay;
use App\Services\PayrollRun;
use App\Services\ThirteenthMonth;
use App\Support\PayPeriod;
use App\Support\Tardiness;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Both of these are statutory, and both are easy to get quietly wrong: a
 * holiday premium that pays the wrong multiple, or a 13th month worked out
 * from what was paid rather than what was earned.
 */
class HolidayAndThirteenthMonthTest extends TestCase
{
    use DatabaseTransactions;

    private int $employeeId;
    private float $salary = 22000.0;

    protected function setUp(): void
    {
        parent::setUp();

        $token = bin2hex(random_bytes(5));
        $user = User::create(['full_name' => 'Holiday Subject '.$token, 'username' => 'hol'.$token,
            'email' => $token.'@example.test', 'password' => 'Password123!', 'role' => 'employee']);

        $this->employeeId = DB::table('employees')->insertGetId(['user_id' => $user->user_id, 'job_title' => 'Subject',
            'hire_date' => '2019-01-01', 'salary' => $this->salary, 'status' => 'active', 'shift_start' => '08:00:00',
            'created_at' => now(), 'updated_at' => now()]);
    }

    private function employee(): object
    {
        return DB::table('employees')->where('employee_id', $this->employeeId)->first();
    }

    private function holiday(string $date, string $type): void
    {
        DB::table('holidays')->insert(['date' => $date, 'name' => 'Test holiday '.$date, 'type' => $type,
            'created_at' => now(), 'updated_at' => now()]);
    }

    private function worked(string $date): void
    {
        DB::table('hr_attendance')->insert(['employee_id' => $this->employeeId, 'date' => $date,
            'time_in' => $date.' 08:00:00', 'time_out' => $date.' 17:00:00', 'status' => 'present',
            'created_at' => now(), 'updated_at' => now()]);
    }

    // ------------------------------------------------------------ holiday pay

    public function test_working_a_regular_holiday_earns_an_extra_day(): void
    {
        $this->holiday('2020-04-09', 'regular');
        $this->worked('2020-04-09');

        $pay = (new HolidayPay)->forPeriod($this->employee(), '2020-04-01', '2020-04-15');

        $this->assertEquals(round(Tardiness::dailyRate($this->salary), 2), $pay['amount']);
        $this->assertSame(1, $pay['regularDays']);
    }

    public function test_working_a_special_day_earns_thirty_percent(): void
    {
        $this->holiday('2020-04-10', 'special');
        $this->worked('2020-04-10');

        $pay = (new HolidayPay)->forPeriod($this->employee(), '2020-04-01', '2020-04-15');

        $this->assertEquals(round(Tardiness::dailyRate($this->salary) * 0.30, 2), $pay['amount']);
        $this->assertSame(1, $pay['specialDays']);
    }

    public function test_a_holiday_nobody_works_earns_no_premium(): void
    {
        $this->holiday('2020-04-09', 'regular');

        // No attendance for that day at all.
        $pay = (new HolidayPay)->forPeriod($this->employee(), '2020-04-01', '2020-04-15');

        $this->assertEquals(0.0, $pay['amount'],
            'the monthly salary already covers a holiday nobody works');
        $this->assertSame(0, $pay['days']);
    }

    public function test_the_premium_reaches_the_payslip_and_is_named(): void
    {
        $this->holiday('2020-04-09', 'regular');

        // A full cutoff worked, one day of which is the holiday.
        for ($day = \Carbon\Carbon::parse('2020-04-01'); $day->lte(\Carbon\Carbon::parse('2020-04-15')); $day->addDay()) {
            $this->worked($day->toDateString());
        }

        (new PayrollRun)->generate($this->employeeId, PayPeriod::fromStart('2020-04-01'));
        $payslip = DB::table('hr_payroll')->where('employee_id', $this->employeeId)->first();

        $premium = round(Tardiness::dailyRate($this->salary), 2);

        $this->assertEquals($premium, (float) $payslip->holiday_pay);
        $this->assertEquals(round($this->salary / 2 + $premium, 2), (float) $payslip->gross_pay);
        $this->assertStringContainsString('Holiday premium', $payslip->notes);
    }

    // --------------------------------------------------------- 13th month pay

    private function payslip(string $start, float $gross, float $overtime = 0, float $holiday = 0, float $timeDeduction = 0): void
    {
        DB::table('hr_payroll')->insert(['employee_id' => $this->employeeId, 'period_start' => $start,
            'period_end' => $start, 'gross_pay' => $gross, 'deductions' => 0, 'net_pay' => $gross,
            'overtime_pay' => $overtime, 'holiday_pay' => $holiday, 'time_deduction' => $timeDeduction,
            'status' => 'paid', 'kind' => 'regular', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_it_is_a_twelfth_of_the_basic_salary_earned(): void
    {
        // Two clean payslips of 11,000.
        $this->payslip('2020-01-01', 11000);
        $this->payslip('2020-01-16', 11000);

        $figures = (new ThirteenthMonth)->forEmployee($this->employeeId, 2020);

        $this->assertEquals(22000.0, $figures['basic']);
        $this->assertEquals(round(22000 / 12, 2), $figures['amount']);
    }

    public function test_overtime_and_holiday_pay_do_not_inflate_it(): void
    {
        // 11,000 basic with 3,000 of overtime and 1,000 of holiday premium on top.
        $this->payslip('2020-01-01', 15000, overtime: 3000, holiday: 1000);

        $this->assertEquals(11000.0, (new ThirteenthMonth)->forEmployee($this->employeeId, 2020)['basic'],
            'the 13th month is a twelfth of basic salary, not of everything paid');
    }

    public function test_days_not_worked_reduce_it(): void
    {
        // 11,000 gross, but 2,000 of it was never earned.
        $this->payslip('2020-01-01', 11000, timeDeduction: 2000);

        $this->assertEquals(9000.0, (new ThirteenthMonth)->forEmployee($this->employeeId, 2020)['basic']);
    }

    public function test_recording_it_is_once_a_year_and_never_feeds_the_next(): void
    {
        $this->payslip('2020-01-01', 11000);
        $this->payslip('2020-01-16', 11000);

        $service = new ThirteenthMonth;

        $this->assertSame(1, $service->generate(2020));
        $this->assertSame(0, $service->generate(2020), 'running it twice must not pay twice');

        $row = DB::table('hr_payroll')->where('employee_id', $this->employeeId)->where('kind', '13th_month')->first();
        $this->assertEquals(round(22000 / 12, 2), (float) $row->net_pay);
        $this->assertSame('2020-12-24', substr((string) $row->period_start, 0, 10));

        // The payment itself is not salary earned, so the figure does not grow.
        $this->assertEquals(22000.0, $service->forEmployee($this->employeeId, 2020)['basic']);
    }

    public function test_a_regular_december_payslip_still_generates_alongside_it(): void
    {
        $this->payslip('2020-01-01', 11000);
        (new ThirteenthMonth)->generate(2020);

        // The 13th month sits on the 24th; the cutoffs start on the 1st and 16th.
        $this->assertNotNull((new PayrollRun)->generate($this->employeeId, PayPeriod::fromStart('2020-12-16')));
    }

    public function test_anything_above_the_exemption_is_flagged_rather_than_guessed(): void
    {
        // A year's basic big enough to push the 13th month past 90,000.
        $this->payslip('2020-01-01', 1200000);

        $figures = (new ThirteenthMonth)->forEmployee($this->employeeId, 2020);

        $this->assertEquals(10000.0, $figures['taxableExcess']);

        (new ThirteenthMonth)->generate(2020);
        $row = DB::table('hr_payroll')->where('employee_id', $this->employeeId)->where('kind', '13th_month')->first();

        $this->assertStringContainsString('withholding has not been applied', $row->notes);
        $this->assertEquals(0.0, (float) $row->deductions, 'nothing is withheld on a guess');
    }
}
