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
        $id = $this->leave('vacation', 30, 'pending');
        $leave = DB::table('leaves')->where('leave_id', $id)->first();

        $verdict = (new LeaveBalances)->canApprove($leave);

        $this->assertTrue($verdict['ok'], 'nothing here invents a company policy');
    }

    public function test_taken_and_pending_days_both_come_off_the_balance(): void
    {
        $this->entitle('vacation', 15);
        $this->leave('vacation', 3, 'approved');
        $this->leave('vacation', 2, 'pending');

        $balance = (new LeaveBalances)->forEmployee($this->employeeId)['vacation'];

        $this->assertEquals(15.0, $balance['entitled']);
        $this->assertEquals(3.0, $balance['used']);
        $this->assertEquals(2.0, $balance['pending']);
        $this->assertEquals(10.0, $balance['remaining'],
            'three pending requests must not each look affordable on their own');
    }

    public function test_a_rejected_request_gives_the_days_back(): void
    {
        $this->entitle('vacation', 10);
        $this->leave('vacation', 4, 'rejected');
        $this->leave('vacation', 1, 'cancelled');

        $this->assertEquals(10.0, (new LeaveBalances)->forEmployee($this->employeeId)['vacation']['remaining']);
    }

    public function test_last_years_leave_does_not_count_against_this_year(): void
    {
        $this->entitle('vacation', 10);
        $this->leave('vacation', 8, 'approved', ($this->year - 1).'-06-01');

        $this->assertEquals(10.0, (new LeaveBalances)->forEmployee($this->employeeId)['vacation']['remaining']);
    }

    public function test_approving_past_the_balance_is_refused(): void
    {
        $this->entitle('vacation', 5);
        $this->leave('vacation', 4, 'approved');
        $id = $this->leave('vacation', 3, 'pending');

        Volt::actingAs($this->hr)->test('hr.leave')->call('approveLeave', $id);

        $this->assertSame('pending', DB::table('leaves')->where('leave_id', $id)->value('status'),
            'the screen can be out of date, so the limit is enforced at the decision');
    }

    public function test_a_request_is_not_counted_against_itself(): void
    {
        $this->entitle('vacation', 5);
        $id = $this->leave('vacation', 5, 'pending');

        Volt::actingAs($this->hr)->test('hr.leave')->call('approveLeave', $id);

        $this->assertSame('approved', DB::table('leaves')->where('leave_id', $id)->value('status'),
            'exactly the whole balance has to be approvable');
    }

    public function test_service_time_gates_the_leave_that_is_earned(): void
    {
        // Five days, but only after a year - and they started last month.
        $this->entitle('vacation', 5, 12);
        DB::table('employees')->where('employee_id', $this->employeeId)
            ->update(['hire_date' => now()->subMonth()->toDateString()]);

        $id = $this->leave('vacation', 1, 'pending');
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

    public function test_hr_sets_and_removes_an_entitlement(): void
    {
        Volt::actingAs($this->hr)->test('hr.leave')
            ->set('entitlementType', 'sick')
            ->set('entitlementDays', 12)
            ->set('entitlementAfterMonths', 6)
            ->call('saveEntitlement')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('leave_entitlements', ['leave_type' => 'sick', 'days_per_year' => 12, 'after_months' => 6]);

        Volt::actingAs($this->hr)->test('hr.leave')->call('removeEntitlement', 'sick');
        $this->assertDatabaseMissing('leave_entitlements', ['leave_type' => 'sick']);
    }

    public function test_the_employee_sees_their_own_balance(): void
    {
        $this->entitle('vacation', 15);
        $this->leave('vacation', 5, 'approved');

        Volt::actingAs($this->staff)->test('employee.leave')
            ->assertSee('Your leave this year')
            ->assertSee('10');
    }
}
