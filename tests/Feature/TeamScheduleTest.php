<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\PayPeriod;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Shift times and rest days are the supervisor's to set, on My team. */
class TeamScheduleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_a_supervisor_sets_their_teams_shift_and_rest_days(): void
    {
        $token = bin2hex(random_bytes(4));
        $boss = User::create(['full_name' => 'Boss '.$token, 'username' => 'bs'.$token,
            'email' => 'b'.$token.'@example.test', 'password' => 'Password123!', 'role' => 'supervisor']);
        $staff = User::create(['full_name' => 'Staff '.$token, 'username' => 'st'.$token,
            'email' => 's'.$token.'@example.test', 'password' => 'Password123!', 'role' => 'employee']);
        $dept = DB::table('departments')->insertGetId(['department_name' => 'Dept '.$token, 'supervisor_id' => $boss->user_id,
            'created_at' => now(), 'updated_at' => now()]);
        foreach ([$boss, $staff] as $user) {
            $ids[] = DB::table('employees')->insertGetId(['user_id' => $user->user_id, 'department_id' => $dept,
                'job_title' => 'X', 'hire_date' => now()->subYear()->toDateString(), 'salary' => 0,
                'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        }
        $cutoff = PayPeriod::recent(1)[0];

        $this->actingAs($boss)->get(route('employee.team'))->assertOk()->assertSee('Shift &amp; rest days', false);

        $this->actingAs($boss)->post(route('employee.team.schedule', $ids[1]), [
            'shift_start' => '09:00', 'shift_end' => '18:00', 'rest_days' => [7],
            'cutoff_rest' => [$cutoff->start],
        ])->assertRedirect();

        $employee = DB::table('employees')->where('employee_id', $ids[1])->first();
        $this->assertSame('09:00:00', $employee->shift_start);
        $this->assertSame('18:00:00', $employee->shift_end);
        $this->assertTrue((bool) DB::table('shift_assignments')->where('employee_id', $ids[1])
            ->where('work_date', $cutoff->start)->value('rest_day') || \App\Support\WorkWeek::restsOn($employee->rest_days, \Carbon\Carbon::parse($cutoff->start)));

        // Official business for their own people.
        $this->actingAs($boss)->post(route('employee.team.ob'), [
            'ob_employee' => $ids[1], 'ob_from' => '2026-09-10', 'ob_to' => '2026-09-11', 'ob_note' => 'Trade fair',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(2, DB::table('hr_attendance')->where('employee_id', $ids[1])->where('status', 'official_business')->count());

        // Not someone outside their team.
        $this->actingAs($staff)->post(route('employee.team.ob'), [
            'ob_employee' => $ids[0], 'ob_from' => '2026-09-10', 'ob_note' => 'x',
        ])->assertForbidden();
        $this->actingAs($staff)->post(route('employee.team.schedule', $ids[0]), ['shift_start' => '07:00'])->assertForbidden();
    }
}
