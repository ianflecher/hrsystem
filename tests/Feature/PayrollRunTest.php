<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Payroll is the one thing here that moves money, so these pin down the
 * arithmetic and the states rather than just checking the screen renders.
 */
class PayrollRunTest extends TestCase
{
    private array $createdUserIds = [];
    private string $period;

    protected function setUp(): void
    {
        parent::setUp();
        // A period far enough back that a real run would not collide with it.
        $this->period = '2019-03-16';
    }

    protected function tearDown(): void
    {
        DB::table('hr_payroll')->where('period_start', $this->period)->delete();

        foreach ($this->createdUserIds as $id) {
            DB::table('hr_payroll')->whereIn('employee_id', function ($q) use ($id) {
                $q->select('employee_id')->from('employees')->where('user_id', $id);
            })->delete();
            DB::table('hr_attendance')->whereIn('employee_id', function ($q) use ($id) {
                $q->select('employee_id')->from('employees')->where('user_id', $id);
            })->delete();
            DB::table('employees')->where('user_id', $id)->delete();
            DB::table('users')->where('user_id', $id)->delete();
        }

        parent::tearDown();
    }

    private function hr(): User
    {
        $hr = User::where('username', 'hr')->first();

        if (! $hr) {
            $this->markTestSkipped('Seeded HR user not found; run `php artisan db:seed`.');
        }

        return $hr;
    }

    private function employee(float $salary, string $status = 'active'): int
    {
        $n = random_int(100000, 999999);

        $user = User::create([
            'full_name' => 'Payroll Subject',
            'username'  => "pay{$n}",
            'email'     => "pay{$n}@example.test",
            'password'  => 'Password!2345',
            'role'      => 'employee',
        ]);
        $this->createdUserIds[] = $user->user_id;

        $employeeId = DB::table('employees')->insertGetId([
            'user_id'    => $user->user_id,
            'job_title'  => 'Subject',
            'hire_date'  => '2019-01-01',
            'salary'     => $salary,
            'status'     => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // These tests are about contributions and tax, so the person turns up
        // every day: absence is charged now, and a subject with no attendance
        // at all would have most of the payslip eaten before tax was reached.
        $this->attend($employeeId, '2019-03-01', '2019-03-31');

        return $employeeId;
    }

    /** A clean day worked, for every date in the range. */
    private function attend(int $employeeId, string $from, string $to): void
    {
        for ($day = \Carbon\Carbon::parse($from); $day->lte(\Carbon\Carbon::parse($to)); $day->addDay()) {
            DB::table('hr_attendance')->insert([
                'employee_id' => $employeeId,
                'date'        => $day->toDateString(),
                'time_in'     => $day->toDateString().' 08:00:00',
                'time_out'    => $day->toDateString().' 17:00:00',
                'status'      => 'present',
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }
    }

    private function screen()
    {
        return Volt::actingAs($this->hr())->test('hr.payroll')->set('payPeriod', $this->period);
    }

    public function test_a_run_creates_one_payslip_for_each_active_employee(): void
    {
        $a = $this->employee(25000);
        $b = $this->employee(18000);

        $this->screen()->call('generatePeriod');

        foreach ([$a, $b] as $id) {
            $this->assertDatabaseHas('hr_payroll', [
                'employee_id'  => $id,
                'period_start' => $this->period,
                'status'       => 'calculated',
            ]);
        }
    }

    public function test_both_cutoffs_carry_half_the_monthly_contributions(): void
    {
        // 25,000 a month is 12,500 a payslip. Half of each monthly
        // contribution lands here: SSS 625 + PhilHealth 312.50 + Pag-IBIG 50 =
        // 987.50, leaving 11,512.50 taxable. The semi-monthly exemption is
        // 10,417, so 1,095.50 is taxed at 15% = 164.33.
        $id = $this->employee(25000);

        $this->screen()->call('generatePeriod');
        $row = DB::table('hr_payroll')->where('employee_id', $id)->where('period_start', $this->period)->first();

        $this->assertNotNull($row);
        $this->assertEqualsWithDelta(12500.00, (float) $row->gross_pay, 0.01);
        $this->assertEqualsWithDelta(625.00, (float) $row->sss, 0.01);
        $this->assertEqualsWithDelta(312.50, (float) $row->philhealth, 0.01);
        $this->assertEqualsWithDelta(50.00, (float) $row->pagibig, 0.01);
        $this->assertEqualsWithDelta(1151.83, (float) $row->deductions, 0.01);
        $this->assertEqualsWithDelta(11348.17, (float) $row->net_pay, 0.02);
    }

    public function test_the_other_cutoff_carries_the_same(): void
    {
        // The two payslips are the same size now, which is the point of
        // splitting the contributions rather than landing them all at once.
        $id = $this->employee(25000);

        $this->screen()->call('generatePeriod');
        $this->screen()->set('payPeriod', '2019-03-01')->call('generatePeriod');

        $first = DB::table('hr_payroll')->where('employee_id', $id)->where('period_start', '2019-03-01')->first();
        $second = DB::table('hr_payroll')->where('employee_id', $id)->where('period_start', $this->period)->first();

        $this->assertNotNull($first);
        $this->assertEqualsWithDelta((float) $second->deductions, (float) $first->deductions, 0.01);

        DB::table('hr_payroll')->where('period_start', '2019-03-01')->delete();
    }

    public function test_someone_below_the_threshold_pays_no_tax(): void
    {
        // 15,000 a month is 7,500 a payslip, well under the 10,417 exemption.
        // Half the monthly contributions still come off: SSS 375 +
        // PhilHealth 187.50 + Pag-IBIG 50 = 612.50.
        $id = $this->employee(15000);

        $this->screen()->call('generatePeriod');
        $row = DB::table('hr_payroll')->where('employee_id', $id)->where('period_start', $this->period)->first();

        $this->assertEqualsWithDelta(0.00, (float) $row->tax, 0.01);
        $this->assertEqualsWithDelta(612.50, (float) $row->deductions, 0.01);
        $this->assertEqualsWithDelta(6887.50, (float) $row->net_pay, 0.01);
    }

    public function test_lateness_comes_off_the_payslip(): void
    {
        // 22,000 a month: 1,000 a day, 125 an hour. Two arrivals, one 8 minutes
        // late (an hour) and one 40 minutes late (half a day) = 625.
        $id = $this->employee(22000);
        DB::table('employees')->where('employee_id', $id)->update(['shift_start' => '08:00:00']);

        // The fixture already has them turning up on time every day; these
        // three arrivals replace the clean ones.
        foreach (['2019-03-18 08:08:00', '2019-03-19 08:40:00', '2019-03-20 08:03:00'] as $arrival) {
            DB::table('hr_attendance')->where('employee_id', $id)->where('date', substr($arrival, 0, 10))
                ->update(['time_in' => $arrival, 'updated_at' => now()]);
        }

        $this->screen()->call('generatePeriod');

        $row = DB::table('hr_payroll')->where('employee_id', $id)->first();

        $this->assertStringContainsString('Late (2 days): PHP 625.00', $row->notes,
            'the third arrival was inside the grace period');

        DB::table('hr_attendance')->where('employee_id', $id)->delete();
    }

    public function test_the_screen_warns_about_missing_attendance_before_generating(): void
    {
        $id = $this->employee(22000);
        DB::table('employees')->where('employee_id', $id)->update(['rest_days' => null]);

        // The fixture attends every day, so there is nothing to warn about yet.
        $this->assertSame(0, $this->screen()->get('attendanceGaps')['days']);

        DB::table('hr_attendance')->where('employee_id', $id)
            ->whereIn('date', ['2019-03-18', '2019-03-19'])->delete();

        $gaps = $this->screen()->get('attendanceGaps');
        $this->assertSame(2, $gaps['days']);
        $this->assertSame(1, $gaps['people']);
    }

    public function test_the_breakdown_names_every_deduction_it_shows(): void
    {
        $id = $this->employee(16000);
        DB::table('employees')->where('employee_id', $id)
            ->update(['shift_start' => '08:00:00', 'rest_days' => null]);

        // One day an hour late. At 16,000 a month the daily rate is 727.27,
        // so half a day is 363.64.
        DB::table('hr_attendance')->where('employee_id', $id)->where('date', '2019-03-18')
            ->update(['time_in' => '2019-03-18 09:00:00', 'status' => 'late']);

        $screen = $this->screen();
        $screen->call('generatePeriod')->call('viewPayrollDetails', $id);

        $breakdown = $screen->get('payrollBreakdown');
        $row = DB::table('hr_payroll')->where('employee_id', $id)->where('period_start', $this->period)->first();

        // The lateness has a name of its own...
        $this->assertEqualsWithDelta(363.64, $breakdown['time'], 0.01);

        // ...and nothing is left over: "other" means unaccounted for, and an
        // unaccounted deduction is a bug, not a category.
        $this->assertEqualsWithDelta(0.0, $breakdown['other_deductions'], 0.01);
        $this->assertEqualsWithDelta((float) $row->deductions, $breakdown['total_deductions'], 0.01);
    }

    public function test_a_second_run_does_not_duplicate_anyone(): void
    {
        $id = $this->employee(20000);

        $this->screen()->call('generatePeriod');
        $this->screen()->call('generatePeriod');

        $this->assertSame(1, DB::table('hr_payroll')
            ->where('employee_id', $id)
            ->where('period_start', $this->period)
            ->count(), 'running the period twice must not pay anyone twice');
    }

    public function test_employees_without_a_salary_or_not_active_are_skipped(): void
    {
        $noSalary = $this->employee(0);
        $inactive = $this->employee(20000, 'inactive');

        $this->screen()->call('generatePeriod');

        foreach ([$noSalary, $inactive] as $id) {
            $this->assertDatabaseMissing('hr_payroll', [
                'employee_id'  => $id,
                'period_start' => $this->period,
            ]);
        }
    }

    public function test_a_payslip_moves_calculated_then_approved_then_paid(): void
    {
        $id = $this->employee(22000);
        $where = ['employee_id' => $id, 'period_start' => $this->period];

        $screen = $this->screen();

        $screen->call('generatePeriod');
        $this->assertSame('calculated', DB::table('hr_payroll')->where($where)->value('status'));

        $screen->call('approvePeriod');
        $this->assertSame('approved', DB::table('hr_payroll')->where($where)->value('status'));

        $screen->call('markPeriodPaid');
        $this->assertSame('paid', DB::table('hr_payroll')->where($where)->value('status'));
    }

    public function test_approving_does_not_reach_back_into_an_earlier_period(): void
    {
        $id = $this->employee(20000);

        $this->screen()->call('generatePeriod');

        // A different period is left alone entirely.
        Volt::actingAs($this->hr())->test('hr.payroll')
            ->set('payPeriod', '2019-04-16')
            ->call('approvePeriod');

        $this->assertSame('calculated', DB::table('hr_payroll')
            ->where('employee_id', $id)
            ->where('period_start', $this->period)
            ->value('status'));
    }
}
