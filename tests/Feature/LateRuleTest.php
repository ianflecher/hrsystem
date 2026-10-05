<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\WorkTimePayroll;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Up to 5 minutes late is grace, 6-15 minutes costs an hour, 16 or more costs
 * half a day - from basic pay only. Staying late does not make it up.
 */
class LateRuleTest extends TestCase
{
    use DatabaseTransactions;

    private function hoursFor(string $in, string $out): array
    {
        $t = bin2hex(random_bytes(4));
        $u = User::create(['full_name' => 'Late '.$t, 'username' => 'lt'.$t, 'email' => $t.'@example.test', 'password' => 'Password123!', 'role' => 'employee']);
        $id = DB::table('employees')->insertGetId(['user_id' => $u->user_id, 'job_title' => 'Sewer', 'hire_date' => '2020-01-01',
            'salary' => 15600, 'daily_rate' => 600, 'pay_basis' => 'daily', 'shift_start' => '08:00:00', 'shift_end' => '17:00:00',
            'rest_days' => '7', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('hr_attendance')->insert(['employee_id' => $id, 'date' => '2020-06-02', 'time_in' => '2020-06-02 '.$in,
            'time_out' => '2020-06-02 '.$out, 'status' => 'present', 'created_at' => now(), 'updated_at' => now()]);
        $employee = DB::table('employees')->where('employee_id', $id)->first();

        return (new WorkTimePayroll)->forPeriod($employee, '2020-06-02', '2020-06-02');
    }

    public function test_the_late_rule(): void
    {
        $this->assertSame([8.0, 0.0], array_values(array_map('floatval', array_intersect_key($this->hoursFor('08:04:00', '17:00:00'), ['hours' => 1, 'late_penalty_hours' => 1]))), 'grace');
        $this->assertSame([7.0, 1.0], array_values(array_map('floatval', array_intersect_key($this->hoursFor('08:10:00', '17:00:00'), ['hours' => 1, 'late_penalty_hours' => 1]))), '6-15 minutes: an hour');
        $this->assertSame([4.0, 4.0], array_values(array_map('floatval', array_intersect_key($this->hoursFor('08:20:00', '18:00:00'), ['hours' => 1, 'late_penalty_hours' => 1]))), '16+: half a day, staying late does not help');
        $this->assertSame([7.0, 0.0], array_values(array_map('floatval', array_intersect_key($this->hoursFor('07:50:00', '16:00:00'), ['hours' => 1, 'late_penalty_hours' => 1]))), 'leaving an hour early costs that hour');
    }
}
