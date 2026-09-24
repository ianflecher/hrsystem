<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The other two things a supervisor was given: their team's attendance, and
 * the shift calendar they draw up before HR signs it off.
 *
 * A shift that is only proposed must not be treated as the real one. Payroll
 * reads the calendar to decide who was due in, so a pending shift counting as
 * approved would mark somebody absent for a day nobody had agreed they work.
 */
class ManagerShiftAndAttendanceTest extends TestCase
{
    private array $users = [];
    private array $departments = [];
    private array $employees = [];

    protected function tearDown(): void
    {
        DB::table('shift_assignments')->whereIn('employee_id', $this->employees)->delete();
        DB::table('hr_attendance')->whereIn('employee_id', $this->employees)->delete();
        DB::table('employees')->whereIn('employee_id', $this->employees)->delete();
        DB::table('departments')->whereIn('department_id', $this->departments)->delete();
        DB::table('users')->whereIn('user_id', $this->users)->delete();

        parent::tearDown();
    }

    private function department(): int
    {
        $id = DB::table('departments')->insertGetId([
            'department_name' => 'Shift Dept '.random_int(100000, 999999),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->departments[] = $id;

        return $id;
    }

    /** @return array{0: User, 1: int} */
    private function person(string $name, string $role, int $departmentId): array
    {
        $n = random_int(100000, 999999);

        $u = User::create([
            'full_name' => $name, 'username' => "ms{$n}",
            'email' => "ms{$n}@example.test", 'password' => 'x', 'role' => $role,
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

    private function supervisorOf(int $departmentId): User
    {
        [$u] = $this->person('Shift Supervisor', 'supervisor', $departmentId);

        DB::table('departments')->where('department_id', $departmentId)
            ->update(['supervisor_id' => $u->user_id]);

        return $u;
    }

    /** What the supervisor was asked for: their team's attendance. */
    public function test_a_supervisor_sees_their_teams_attendance(): void
    {
        $dept = $this->department();
        $supervisor = $this->supervisorOf($dept);
        [, $memberId] = $this->person('Present Sewer', 'employee', $dept);

        DB::table('hr_attendance')->insert([
            'employee_id' => $memberId, 'date' => '2026-09-23',
            'time_in' => '2026-09-23 08:00:00', 'time_out' => '2026-09-23 17:00:00',
            'status' => 'present', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $html = $this->actingAs($supervisor)->get('/employee/team/'.$memberId)
            ->assertOk()->getContent();

        $this->assertStringContainsString('Present Sewer', $html);
    }

    /**
     * A supervisor sees their people without seeing what they earn. Pay, loans,
     * documents and salary history stay with HR - being somebody's supervisor
     * is not a reason to know their salary or read their payslips.
     */
    public function test_a_supervisor_does_not_see_their_teams_pay(): void
    {
        $dept = $this->department();
        $supervisor = $this->supervisorOf($dept);
        [, $memberId] = $this->person('Paid Sewer', 'employee', $dept);

        DB::table('employee_salary_history')->insert([
            'employee_id' => $memberId, 'salary' => 44444, 'allowance' => 0,
            'pay_basis' => 'monthly', 'effective_from' => '2026-01-01',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $html = $this->actingAs($supervisor)->get('/employee/team/'.$memberId)
            ->assertOk()->getContent();

        $this->assertStringNotContainsString('44,444', $html,
            'a supervisor can see what their team member earns');

        DB::table('employee_salary_history')->where('employee_id', $memberId)->delete();
    }

    // ------------------------------------------------------------- the shifts

    /**
     * The point of the tier. A shift the supervisor has drawn up but HR has
     * not signed off is not yet the roster, and nothing that reads the
     * calendar should treat it as one.
     */
    public function test_a_pending_shift_is_not_treated_as_the_real_one(): void
    {
        $dept = $this->department();
        [$member, $memberId] = $this->person('Rostered Sewer', 'employee', $dept);

        DB::table('shift_assignments')->insert([
            'employee_id' => $memberId, 'work_date' => '2026-10-05',
            'starts_at' => '06:00:00', 'ends_at' => '15:00:00', 'rest_day' => 0, 'label' => 'Early',
            'status' => 'pending_hr',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $employee = DB::table('employees')->where('employee_id', $memberId)->first();
        $shift = \App\Support\ShiftSchedule::forEmployeeDate($employee, '2026-10-05');

        $this->assertNotSame('06:00:00', $shift['start'],
            'a shift still waiting on HR was used as the real roster');
        $this->assertSame('08:00:00', $shift['start'],
            'it should fall back to their standing shift until HR approves');
    }

    public function test_an_approved_shift_is_used(): void
    {
        $dept = $this->department();
        [, $memberId] = $this->person('Rostered Sewer', 'employee', $dept);

        DB::table('shift_assignments')->insert([
            'employee_id' => $memberId, 'work_date' => '2026-10-06',
            'starts_at' => '06:00:00', 'ends_at' => '15:00:00', 'rest_day' => 0, 'label' => 'Early',
            'status' => 'approved',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $employee = DB::table('employees')->where('employee_id', $memberId)->first();
        $shift = \App\Support\ShiftSchedule::forEmployeeDate($employee, '2026-10-06');

        $this->assertSame('06:00:00', $shift['start'],
            'an approved shift should be the one that counts');
    }

    /**
     * Rows written before the tier existed are still the roster.
     *
     * The column was added NOT NULL DEFAULT 'approved', so every shift that
     * already existed became an approved one rather than silently dropping out
     * of the calendar the day the column appeared. Inserting without a status
     * is how a row from before the change looks.
     */
    public function test_a_shift_from_before_the_change_still_counts(): void
    {
        $dept = $this->department();
        [, $memberId] = $this->person('Rostered Sewer', 'employee', $dept);

        DB::table('shift_assignments')->insert([
            'employee_id' => $memberId, 'work_date' => '2026-10-07',
            'starts_at' => '06:00:00', 'ends_at' => '15:00:00', 'rest_day' => 0, 'label' => 'Early',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertSame('approved',
            DB::table('shift_assignments')->where('employee_id', $memberId)
                ->where('work_date', '2026-10-07')->value('status'),
            'an existing shift did not survive the column being added');

        $employee = DB::table('employees')->where('employee_id', $memberId)->first();
        $shift = \App\Support\ShiftSchedule::forEmployeeDate($employee, '2026-10-07');

        $this->assertSame('06:00:00', $shift['start'],
            'an older shift with no status was dropped from the roster');
    }
}
