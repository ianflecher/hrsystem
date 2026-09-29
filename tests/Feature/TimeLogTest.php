<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Staff the scanner cannot record yet: their supervisor enters the day, or
 * they send it in themselves and it counts once the supervisor approves it.
 */
class TimeLogTest extends TestCase
{
    private array $userIds = [];
    private array $employeeIds = [];
    private ?int $departmentId = null;

    protected function tearDown(): void
    {
        DB::table('time_log_requests')->whereIn('employee_id', $this->employeeIds)->delete();
        DB::table('hr_attendance')->whereIn('employee_id', $this->employeeIds)->delete();
        DB::table('shift_assignments')->whereIn('employee_id', $this->employeeIds)->delete();
        DB::table('employees')->whereIn('employee_id', $this->employeeIds)->delete();
        if ($this->departmentId) {
            DB::table('departments')->where('department_id', $this->departmentId)->delete();
        }
        DB::table('users')->whereIn('user_id', $this->userIds)->delete();
        parent::tearDown();
    }

    private function person(string $role, ?string $scanner): array
    {
        $n = random_int(100000, 999999);
        $user = User::create(['full_name' => "Log Person {$n}", 'username' => "log{$n}", 'email' => "log{$n}@example.test",
            'password' => 'Password!2345', 'role' => $role]);
        $this->userIds[] = $user->user_id;
        $id = DB::table('employees')->insertGetId(['user_id' => $user->user_id, 'job_title' => 'Sewer', 'hire_date' => '2020-01-01',
            'department_id' => $this->departmentId, 'biometric_id' => $scanner, 'status' => 'active',
            'created_at' => now(), 'updated_at' => now()]);
        $this->employeeIds[] = $id;

        return [$user, $id];
    }

    public function test_a_supervisor_enters_and_approves_days_for_staff_without_a_scanner(): void
    {
        $this->departmentId = DB::table('departments')->insertGetId(['department_name' => 'Timelog Test '.random_int(1000, 9999), 'created_at' => now(), 'updated_at' => now()]);
        [$supervisor] = $this->person('supervisor', 'S'.random_int(100000, 999999));
        DB::table('departments')->where('department_id', $this->departmentId)->update(['supervisor_id' => $supervisor->user_id]);
        [$seasonal, $seasonalId] = $this->person('employee', null);
        [, $scannedId] = $this->person('employee', 'X'.random_int(100000, 999999));

        // The employee sends a day in; it does not count yet.
        \Livewire\Volt\Volt::actingAs($seasonal)->test('employee.attendance')
            ->set('logDate', '2020-06-01')->set('logIn', '08:05')->set('logOut', '17:02')
            ->call('submitTimeLog')->assertHasNoErrors();
        $log = DB::table('time_log_requests')->where('employee_id', $seasonalId)->sole();
        $this->assertSame('pending', $log->status);
        $this->assertFalse(DB::table('hr_attendance')->where('employee_id', $seasonalId)->exists(), 'not counted before approval');

        // Their supervisor approves it: now it is the day's attendance.
        $this->actingAs($supervisor)->post(route('employee.team.timelog', $log->id), ['action' => 'approve'])->assertRedirect();
        $day = DB::table('hr_attendance')->where('employee_id', $seasonalId)->whereDate('date', '2020-06-01')->sole();
        $this->assertSame('2020-06-01 08:05:00', (string) $day->time_in);
        $this->assertSame('2020-06-01 17:02:00', (string) $day->time_out);
        $this->assertStringContainsString('approved by', $day->notes);

        // Or the supervisor enters a day straight away, shift and times.
        $this->actingAs($supervisor)->post(route('employee.team.attendance'), [
            'employee_id' => $seasonalId, 'date' => '2020-06-02', 'shift_start' => '08:00', 'shift_end' => '17:00',
            'time_in' => '08:20', 'time_out' => '17:00',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $entered = DB::table('hr_attendance')->where('employee_id', $seasonalId)->whereDate('date', '2020-06-02')->sole();
        $this->assertSame('late', $entered->status, '08:20 on an 08:00 shift is late');
        $this->assertStringContainsString('Entered by', $entered->notes);

        // Somebody the scanner records is not theirs to write.
        $this->actingAs($supervisor)->post(route('employee.team.attendance'), [
            'employee_id' => $scannedId, 'date' => '2020-06-02', 'shift_start' => '08:00', 'shift_end' => '17:00', 'time_in' => '08:00',
        ])->assertStatus(422);
        $this->assertFalse(DB::table('hr_attendance')->where('employee_id', $scannedId)->exists());
    }
}
