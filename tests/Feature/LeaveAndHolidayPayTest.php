<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\HolidayPay;
use App\Services\TimeDeductions;
use App\Support\Tardiness;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The five cases that decide whether somebody is paid correctly for a day they
 * did not work an ordinary shift.
 *
 *   leave with pay          nothing deducted
 *   leave without pay       one day deducted
 *   regular holiday, off    nothing deducted - the monthly salary covers it
 *   special non-working, off  one day deducted - no work, no pay
 *   holiday worked          paid double, which for monthly-paid staff means
 *                           the basic plus one more day as premium
 *
 * Asserted against the arithmetic rather than by reading the code, because
 * every one of these is somebody's wage.
 */
class LeaveAndHolidayPayTest extends TestCase
{
    private int $employeeId;
    private int $userId;
    private float $salary = 20000.0;

    /** A fortnight nobody real has payroll in. */
    private string $start = '2021-06-01';
    private string $end = '2021-06-15';

    protected function setUp(): void
    {
        parent::setUp();

        $n = random_int(100000, 999999);
        $u = User::create([
            'full_name' => 'Leave Case', 'username' => "lc{$n}",
            'email' => "lc{$n}@example.test", 'password' => 'x', 'role' => 'employee',
        ]);
        $this->userId = $u->user_id;

        $this->employeeId = DB::table('employees')->insertGetId([
            'user_id' => $u->user_id, 'job_title' => 'Operator', 'hire_date' => '2020-01-01',
            'salary' => $this->salary, 'pay_basis' => 'monthly', 'status' => 'active',
            'shift_start' => '08:00:00', 'shift_end' => '17:00:00',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        DB::table('hr_attendance')->where('employee_id', $this->employeeId)->delete();
        DB::table('leaves')->where('employee_id', $this->employeeId)->delete();
        DB::table('holidays')->whereBetween('date', [$this->start, $this->end])->delete();
        DB::table('employees')->where('employee_id', $this->employeeId)->delete();
        DB::table('users')->where('user_id', $this->userId)->delete();

        parent::tearDown();
    }

    private function employee(): object
    {
        return DB::table('employees')->where('employee_id', $this->employeeId)->first();
    }

    /** Present every working day of the cutoff except the ones named. */
    private function workTheFortnight(array $except = []): void
    {
        for ($d = Carbon::parse($this->start); $d->lte(Carbon::parse($this->end)); $d->addDay()) {
            $date = $d->toDateString();

            if (in_array($date, $except, true)) {
                continue;
            }

            DB::table('hr_attendance')->insert([
                'employee_id' => $this->employeeId, 'date' => $date,
                'time_in' => $date.' 08:00:00', 'time_out' => $date.' 17:00:00',
                'status' => 'present', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    /**
     * `type` is only the broad regular/special split; the detail that decides
     * the multiplier lives in `classification`.
     */
    private function holiday(string $date, string $classification): void
    {
        DB::table('holidays')->insert([
            'date' => $date,
            'name' => str_replace('_', ' ', ucfirst($classification)).' holiday',
            'type' => $classification === 'regular' ? 'regular' : 'special',
            'classification' => $classification,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function leave(string $date, string $type): void
    {
        DB::table('leaves')->insert([
            'employee_id' => $this->employeeId, 'leave_type' => $type,
            'start_date' => $date, 'end_date' => $date, 'status' => 'approved', 'total_days' => 1,
            'reason' => 'test', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function deductions(): array
    {
        return (new TimeDeductions)->forPeriod($this->employee(), $this->start, $this->end);
    }

    // ------------------------------------------------------------------ leave

    public function test_leave_with_pay_costs_them_nothing(): void
    {
        $this->workTheFortnight(except: ['2021-06-08']);
        $this->leave('2021-06-08', 'vacation');

        $t = $this->deductions();

        $this->assertEquals(0.0, $t['total'], 'paid leave was deducted');
        $this->assertSame(0, $t['absentDays'], 'paid leave was counted as absence');
        $this->assertSame(1, $t['leaveDays']);
    }

    public function test_leave_without_pay_costs_them_a_day(): void
    {
        $this->workTheFortnight(except: ['2021-06-08']);
        $this->leave('2021-06-08', 'unpaid');

        $t = $this->deductions();
        $daily = round(Tardiness::dailyRate($this->salary), 2);

        $this->assertEquals($daily, $t['unpaidLeave']);
        $this->assertSame(1, $t['unpaidLeaveDays']);
        $this->assertEquals($daily, $t['total']);

        // Unpaid leave is not absence: the distinction is the whole point.
        $this->assertSame(0, $t['absentDays']);
    }

    public function test_simply_not_turning_up_costs_them_a_day(): void
    {
        $this->workTheFortnight(except: ['2021-06-08']);

        $t = $this->deductions();

        $this->assertSame(1, $t['absentDays']);
        $this->assertEquals(round(Tardiness::dailyRate($this->salary), 2), $t['absence']);
    }

    // ---------------------------------------------------------------- holiday

    public function test_a_regular_holiday_not_worked_is_still_paid(): void
    {
        $this->holiday('2021-06-08', 'regular');
        $this->workTheFortnight(except: ['2021-06-08']);

        $t = $this->deductions();

        $this->assertEquals(0.0, $t['total'], 'they were docked for a regular holiday');
        $this->assertSame(0, $t['absentDays'], 'a regular holiday was counted as absence');
    }

    public function test_a_special_non_working_day_not_worked_follows_no_work_no_pay(): void
    {
        $this->holiday('2021-06-08', 'special_non_working');
        $this->workTheFortnight(except: ['2021-06-08']);

        $t = $this->deductions();

        $this->assertSame(1, $t['absentDays'],
            'a special non-working day should follow no work, no pay');
        $this->assertEquals(round(Tardiness::dailyRate($this->salary), 2), $t['absence']);
    }

    public function test_working_a_regular_holiday_is_paid_double(): void
    {
        $this->holiday('2021-06-08', 'regular');
        $this->workTheFortnight();

        $holiday = (new HolidayPay)->forPeriod($this->employee(), $this->start, $this->end);
        $daily = round(Tardiness::dailyRate($this->salary), 2);

        // The basic already pays the first 100%; the premium is the second.
        $this->assertEqualsWithDelta($daily, $holiday['amount'], 0.01,
            'working a regular holiday should add one day of pay on top of the basic');
        $this->assertSame(1, $holiday['regularDays']);
    }

    public function test_working_a_special_non_working_day_pays_a_third_more(): void
    {
        $this->holiday('2021-06-08', 'special_non_working');
        $this->workTheFortnight();

        $holiday = (new HolidayPay)->forPeriod($this->employee(), $this->start, $this->end);
        $daily = round(Tardiness::dailyRate($this->salary), 2);

        // 130%, of which the basic covers 100, so the premium is 30%.
        $this->assertEqualsWithDelta($daily * 0.30, $holiday['amount'], 0.01);
        $this->assertSame(1, $holiday['specialDays']);
    }

    public function test_a_special_working_day_is_an_ordinary_day(): void
    {
        $this->holiday('2021-06-08', 'special_working');
        $this->workTheFortnight();

        $holiday = (new HolidayPay)->forPeriod($this->employee(), $this->start, $this->end);

        $this->assertEquals(0.0, $holiday['amount'], 'a special working day carries no premium');
    }

    /** The two together: paid leave on one holiday, unpaid on another. */
    public function test_leave_and_holidays_in_the_same_cutoff(): void
    {
        $this->holiday('2021-06-07', 'regular');
        $this->holiday('2021-06-09', 'special_non_working');

        $this->workTheFortnight(except: ['2021-06-07', '2021-06-09', '2021-06-10', '2021-06-11']);
        $this->leave('2021-06-10', 'sick');     // paid
        $this->leave('2021-06-11', 'unpaid');   // not

        $t = $this->deductions();
        $daily = round(Tardiness::dailyRate($this->salary), 2);

        // The regular holiday and the paid sick day cost nothing. The special
        // non-working day and the unpaid leave cost a day each.
        $this->assertSame(1, $t['leaveDays'], 'the paid sick day was not recognised');
        $this->assertSame(1, $t['unpaidLeaveDays']);
        $this->assertSame(1, $t['absentDays'], 'only the special non-working day should be absence');
        $this->assertEqualsWithDelta($daily * 2, $t['total'], 0.01);
    }
}
