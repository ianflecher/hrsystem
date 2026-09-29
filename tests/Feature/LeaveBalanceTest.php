<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\LeaveBalances;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Approved leave is paid, so a balance is the difference between a day off and
 * a day of the company's money. These pin down what is counted, what is not,
 * and that the limit is enforced where the decision is made rather than only
 * drawn on the screen.
 */
class LeaveBalanceTest extends TestCase
{
    use DatabaseTransactions;

    private User $staff;
    private User $hr;
    private int $employeeId;
    private int $year;

    protected function setUp(): void
    {
        parent::setUp();

        $this->year = (int) now()->year;
        $this->hr = User::where('username', 'hr')->firstOrFail();

        $token = bin2hex(random_bytes(5));
        $this->staff = User::create(['full_name' => 'Leave '.$token, 'username' => 'lv'.$token,
            'email' => $token.'@example.test', 'password' => 'Password123!', 'role' => 'employee']);

        $this->employeeId = DB::table('employees')->insertGetId(['user_id' => $this->staff->user_id,
            'job_title' => 'Subject', 'hire_date' => now()->subYears(3)->toDateString(), 'salary' => 22000,
            'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function entitle(string $type, float $days, int $afterMonths = 0): void
    {
        DB::table('leave_entitlements')->updateOrInsert(['leave_type' => $type],
            ['days_per_year' => $days, 'after_months' => $afterMonths, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function leave(string $type, float $days, string $status, ?string $start = null): int
    {
        $start = $start ?: $this->year.'-06-01';

        return DB::table('leaves')->insertGetId(['employee_id' => $this->employeeId, 'leave_type' => $type,
            'start_date' => $start, 'end_date' => $start, 'total_days' => $days, 'reason' => 'Testing',
            'status' => $status, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_a_type_with_no_entitlement_set_is_not_limited(): void
    {
        // Explicit rather than assumed: the table may already hold a policy,
        // and this test is about what happens when it does not.
        DB::table('leave_entitlements')->where('leave_type', 'paternity')->delete();

        $id = $this->leave('paternity', 30, 'pending');
        $leave = DB::table('leaves')->where('leave_id', $id)->first();

        $verdict = (new LeaveBalances)->canApprove($leave);

        $this->assertTrue($verdict['ok'], 'nothing here invents a company policy');
    }

    public function test_taken_and_pending_days_both_come_off_the_balance(): void
    {
        $this->entitle('paternity', 15);
        $this->leave('paternity', 3, 'approved');
        $this->leave('paternity', 2, 'pending');

        $balance = (new LeaveBalances)->forEmployee($this->employeeId)['paternity'];

        $this->assertEquals(15.0, $balance['entitled']);
        $this->assertEquals(3.0, $balance['used']);
        $this->assertEquals(2.0, $balance['pending']);
        $this->assertEquals(10.0, $balance['remaining'],
            'three pending requests must not each look affordable on their own');
    }

    public function test_a_rejected_request_gives_the_days_back(): void
    {
        $this->entitle('paternity', 10);
        $this->leave('paternity', 4, 'rejected');
        $this->leave('paternity', 1, 'cancelled');

        $this->assertEquals(10.0, (new LeaveBalances)->forEmployee($this->employeeId)['paternity']['remaining']);
    }

    public function test_last_years_leave_does_not_count_against_this_year(): void
    {
        $this->entitle('paternity', 10);
        $this->leave('paternity', 8, 'approved', ($this->year - 1).'-06-01');

        $this->assertEquals(10.0, (new LeaveBalances)->forEmployee($this->employeeId)['paternity']['remaining']);
    }

    public function test_approving_past_the_balance_is_refused(): void
    {
        $this->entitle('paternity', 5);
        $this->leave('paternity', 4, 'approved');
        $id = $this->leave('paternity', 3, 'pending');

        Volt::actingAs($this->hr)->test('hr.leave')->call('approveLeave', $id);

        $this->assertSame('pending', DB::table('leaves')->where('leave_id', $id)->value('status'),
            'the screen can be out of date, so the limit is enforced at the decision');
    }

    public function test_a_request_is_not_counted_against_itself(): void
    {
        $this->entitle('paternity', 5);
        $id = $this->leave('paternity', 5, 'pending');

        Volt::actingAs($this->hr)->test('hr.leave')->call('approveLeave', $id);

        $this->assertSame('approved', DB::table('leaves')->where('leave_id', $id)->value('status'),
            'exactly the whole balance has to be approvable');
    }

    public function test_service_time_gates_the_leave_that_is_earned(): void
    {
        // Five days, but only after a year - and they started last month.
        $this->entitle('paternity', 5, 12);
        DB::table('employees')->where('employee_id', $this->employeeId)
            ->update(['hire_date' => now()->subMonth()->toDateString()]);

        $id = $this->leave('paternity', 1, 'pending');
        Volt::actingAs($this->hr)->test('hr.leave')->call('approveLeave', $id);

        $this->assertSame('pending', DB::table('leaves')->where('leave_id', $id)->value('status'));
    }

    public function test_unpaid_leave_is_never_limited(): void
    {
        $this->entitle('unpaid', 1);
        $this->leave('unpaid', 20, 'approved');
        $id = $this->leave('unpaid', 10, 'pending');

        $balance = (new LeaveBalances)->forEmployee($this->employeeId)['unpaid'];
        $this->assertNull($balance['entitled'], 'it costs the company nothing to grant');

        Volt::actingAs($this->hr)->test('hr.leave')->call('approveLeave', $id);
        $this->assertSame('approved', DB::table('leaves')->where('leave_id', $id)->value('status'));
    }

    /** Employees: seven paid days a year, shared by sick, vacation and emergency. */
    public function test_employees_share_seven_paid_days_across_the_three_types(): void
    {
        $this->leave('sick', 3, 'approved');
        $this->leave('vacation', 2, 'approved');

        $balances = (new LeaveBalances)->forEmployee($this->employeeId);
        $this->assertEquals(2.0, $balances['emergency']['remaining'], 'sick and vacation came off the same seven days');
        $this->assertEquals(2.0, $balances['vacation']['remaining']);

        $this->assertSame('paid', (new LeaveBalances)->payStatusForRequest($this->employeeId, 'emergency', 2, 'paid'));
        $this->assertSame('unpaid', (new LeaveBalances)->payStatusForRequest($this->employeeId, 'vacation', 3, 'paid'), 'past the seven days it is unpaid');
    }

    /** Supervisors: 4 sick, 4 vacation and 1 emergency, each on its own. */
    public function test_supervisors_have_their_own_days_per_type(): void
    {
        DB::table('users')->where('user_id', DB::table('employees')->where('employee_id', $this->employeeId)->value('user_id'))
            ->update(['role' => 'supervisor']);
        $this->leave('sick', 4, 'approved');

        $balances = (new LeaveBalances)->forEmployee($this->employeeId);
        $this->assertEquals(0.0, $balances['sick']['remaining']);
        $this->assertEquals(4.0, $balances['vacation']['remaining'], 'sick leave does not touch vacation');
        $this->assertEquals(1.0, $balances['emergency']['remaining']);
        $this->assertSame('unpaid', (new LeaveBalances)->payStatusForRequest($this->employeeId, 'sick', 1, 'paid'));
        $this->assertSame('unpaid', (new LeaveBalances)->payStatusForRequest($this->employeeId, 'emergency', 2, 'paid'));
    }

    public function test_a_long_bereavement_pays_three_days_and_files_the_rest_unpaid(): void
    {
        Volt::actingAs($this->staff)->test('employee.leave')
            ->set('leave_type', 'bereavement')->set('pay_status', 'paid')
            ->set('start_date', $this->year.'-08-03')->set('end_date', $this->year.'-08-07')
            ->set('reason', 'Funeral')->call('submit')->assertHasNoErrors();

        $rows = DB::table('leaves')->where('employee_id', $this->employeeId)->orderBy('start_date')->get();
        $this->assertCount(2, $rows);
        $this->assertSame(['paid', $this->year.'-08-05', 3.0], [$rows[0]->pay_status, (string) $rows[0]->end_date, (float) $rows[0]->total_days]);
        $this->assertSame(['unpaid', $this->year.'-08-06', 2.0], [$rows[1]->pay_status, (string) $rows[1]->start_date, (float) $rows[1]->total_days]);
    }

    public function test_hr_sets_and_removes_an_entitlement(): void
    {
        Volt::actingAs($this->hr)->test('hr.leave')
            ->set('entitlementType', 'paternity')
            ->set('entitlementDays', 12)
            ->set('entitlementAfterMonths', 6)
            ->call('saveEntitlement')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('leave_entitlements', ['leave_type' => 'paternity', 'days_per_year' => 12, 'after_months' => 6]);

        Volt::actingAs($this->hr)->test('hr.leave')->call('removeEntitlement', 'paternity');
        $this->assertDatabaseMissing('leave_entitlements', ['leave_type' => 'paternity']);
    }

    public function test_the_employee_sees_their_own_balance(): void
    {
        $this->entitle('paternity', 15);
        $this->leave('paternity', 5, 'approved');

        Volt::actingAs($this->staff)->test('employee.leave')
            ->assertSee('Your leave this year')
            ->assertSee('10');
    }
}
