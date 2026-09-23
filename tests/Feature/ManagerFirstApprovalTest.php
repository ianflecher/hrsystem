<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Supervisors and leaders review their own team first, then HR.
 *
 * The part worth testing hardest is not the happy path but the boundary: a
 * leave reason is often medical, and a supervisor who can open another
 * department's requests is reading things about people who do not report to
 * them. The approval order is a workflow bug; the scope is a privacy one.
 */
class ManagerFirstApprovalTest extends TestCase
{
    private array $users = [];
    private array $departments = [];
    private array $employees = [];

    protected function tearDown(): void
    {
        DB::table('leaves')->whereIn('employee_id', $this->employees)->delete();
        DB::table('overtime_requests')->whereIn('employee_id', $this->employees)->delete();
        DB::table('shift_assignments')->whereIn('employee_id', $this->employees)->delete();
        DB::table('employees')->whereIn('employee_id', $this->employees)->delete();
        DB::table('departments')->whereIn('department_id', $this->departments)->delete();
        DB::table('users')->whereIn('user_id', $this->users)->delete();

        parent::tearDown();
    }

    private function department(string $name): int
    {
        $id = DB::table('departments')->insertGetId([
            'department_name' => $name.' '.random_int(100000, 999999),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->departments[] = $id;

        return $id;
    }

    /** @return array{0: User, 1: int} */
    private function person(string $name, string $role, ?int $departmentId): array
    {
        $n = random_int(100000, 999999);

        $u = User::create([
            'full_name' => $name, 'username' => "mf{$n}",
            'email' => "mf{$n}@example.test", 'password' => 'x', 'role' => $role,
        ]);
        $this->users[] = $u->user_id;

        $employeeId = DB::table('employees')->insertGetId([
            'user_id' => $u->user_id, 'job_title' => 'Operator', 'hire_date' => '2026-01-01',
            'department_id' => $departmentId, 'salary' => 15000, 'status' => 'active',
            'shift_start' => '08:00:00', 'shift_end' => '17:00:00', 'rest_days' => '7',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->employees[] = $employeeId;

        return [$u, $employeeId];
    }

    private function supervisorOf(int $departmentId, string $name = 'Team Supervisor'): User
    {
        [$u] = $this->person($name, 'supervisor', $departmentId);

        DB::table('departments')->where('department_id', $departmentId)
            ->update(['supervisor_id' => $u->user_id]);

        return $u;
    }

    private function leaveFor(int $employeeId, string $status = 'pending'): int
    {
        return DB::table('leaves')->insertGetId([
            'employee_id' => $employeeId, 'leave_type' => 'sick',
            'start_date' => '2026-10-01', 'end_date' => '2026-10-01', 'total_days' => 1,
            'status' => $status, 'reason' => 'A private medical matter',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    // ------------------------------------------------------------- the scope

    public function test_a_supervisor_sees_their_own_team(): void
    {
        $dept = $this->department('Sewing');
        $supervisor = $this->supervisorOf($dept);
        [$member] = $this->person('Their Own Sewer', 'employee', $dept);

        $html = $this->actingAs($supervisor)->get('/employee/team')->assertOk()->getContent();

        $this->assertStringContainsString('Their Own Sewer', $html,
            'a supervisor cannot see somebody who reports to them');
    }

    /** The one that matters: somebody else's department is not theirs to read. */
    public function test_a_supervisor_does_not_see_another_departments_people(): void
    {
        $mine = $this->department('Sewing');
        $theirs = $this->department('Supply Chain');

        $supervisor = $this->supervisorOf($mine);
        [$outsider] = $this->person('Not Their Report', 'employee', $theirs);

        $html = $this->actingAs($supervisor)->get('/employee/team')->assertOk()->getContent();

        $this->assertStringNotContainsString('Not Their Report', $html,
            'a supervisor can see people from a department they do not run');
    }

    public function test_a_supervisor_cannot_open_another_departments_employee(): void
    {
        $mine = $this->department('Sewing');
        $theirs = $this->department('Supply Chain');

        $supervisor = $this->supervisorOf($mine);
        [, $outsiderId] = $this->person('Other Department Person', 'employee', $theirs);

        $this->actingAs($supervisor)->get('/employee/team/'.$outsiderId)
            ->assertForbidden();
    }

    public function test_a_supervisor_cannot_decide_another_departments_leave(): void
    {
        $mine = $this->department('Sewing');
        $theirs = $this->department('Supply Chain');

        $supervisor = $this->supervisorOf($mine);
        [, $outsiderId] = $this->person('Other Department Person', 'employee', $theirs);
        $leaveId = $this->leaveFor($outsiderId);

        $this->actingAs($supervisor)
            ->post('/employee/team/leave/'.$leaveId, ['action' => 'approve'])
            ->assertForbidden();

        $this->assertSame('pending',
            DB::table('leaves')->where('leave_id', $leaveId)->value('status'),
            'a supervisor decided leave for somebody who does not report to them');
    }

    /** An ordinary employee has no team screen at all. */
    public function test_an_ordinary_employee_cannot_reach_the_team_screen(): void
    {
        $dept = $this->department('Sewing');
        [$sewer] = $this->person('Ordinary Sewer', 'employee', $dept);

        $this->actingAs($sewer)->get('/employee/team')->assertForbidden();
    }

    // -------------------------------------------------------------- the order

    public function test_leave_goes_to_the_supervisor_before_hr(): void
    {
        $dept = $this->department('Sewing');
        $supervisor = $this->supervisorOf($dept);
        [, $memberId] = $this->person('Sewer On Leave', 'employee', $dept);
        $leaveId = $this->leaveFor($memberId);

        $this->actingAs($supervisor)
            ->post('/employee/team/leave/'.$leaveId, ['action' => 'approve'])
            ->assertRedirect();

        $row = DB::table('leaves')->where('leave_id', $leaveId)->first();

        $this->assertSame('pending_hr', $row->status,
            'the supervisor approving should hand it to HR, not finish it');
        $this->assertEquals($supervisor->user_id, $row->manager_reviewed_by,
            'the record does not say who reviewed it first');
        $this->assertNotNull($row->manager_reviewed_at);
    }

    /**
     * A supervisor turning it down ends it. Sending a refusal on to HR would
     * ask two people to say no to the same request.
     */
    public function test_a_supervisor_declining_ends_it(): void
    {
        $dept = $this->department('Sewing');
        $supervisor = $this->supervisorOf($dept);
        [, $memberId] = $this->person('Sewer On Leave', 'employee', $dept);
        $leaveId = $this->leaveFor($memberId);

        $this->actingAs($supervisor)->post('/employee/team/leave/'.$leaveId, [
            'action' => 'reject', 'note' => 'We are short that week.',
        ])->assertRedirect();

        $this->assertSame('rejected',
            DB::table('leaves')->where('leave_id', $leaveId)->value('status'));
    }

    public function test_hr_finishes_what_the_supervisor_passed_on(): void
    {
        $hr = User::where('username', 'hr')->first();
        $dept = $this->department('Sewing');
        $supervisor = $this->supervisorOf($dept);
        [, $memberId] = $this->person('Sewer On Leave', 'employee', $dept);
        $leaveId = $this->leaveFor($memberId);

        $this->actingAs($supervisor)->post('/employee/team/leave/'.$leaveId, ['action' => 'approve']);

        $this->assertSame('pending_hr',
            DB::table('leaves')->where('leave_id', $leaveId)->value('status'));

        // HR finishes leave on its own screen rather than the generic people
        // endpoint, which only knows pending_hr for overtime.
        \Livewire\Volt\Volt::actingAs($hr)->test('hr.leave')->call('approveLeave', $leaveId);

        $this->assertSame('approved',
            DB::table('leaves')->where('leave_id', $leaveId)->value('status'),
            'HR could not finish a request the supervisor had passed on');
    }
}
