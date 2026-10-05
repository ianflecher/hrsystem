<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Volt;
use Tests\TestCase;

/** An HR officer does HR's work but never sees pay; the HR supervisor (admin) does. */
class HrOfficerPayTest extends TestCase
{
    use DatabaseTransactions;

    private function officer(): User
    {
        $t = bin2hex(random_bytes(4));

        return User::create(['full_name' => 'Officer '.$t, 'username' => 'of'.$t, 'email' => $t.'@example.test',
            'password' => 'Password123!', 'role' => 'hr']);
    }

    private function staff(): int
    {
        $t = bin2hex(random_bytes(4));
        $u = User::create(['full_name' => 'Paid '.$t, 'username' => 'pd'.$t, 'email' => 'p'.$t.'@example.test',
            'password' => 'Password123!', 'role' => 'employee']);

        return DB::table('employees')->insertGetId(['user_id' => $u->user_id, 'job_title' => 'Crew',
            'hire_date' => '2025-01-01', 'salary' => 15600, 'daily_rate' => 600, 'pay_basis' => 'daily',
            'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_an_hr_officer_cannot_open_payroll_but_records_loans(): void
    {
        $officer = $this->officer();

        $this->actingAs($officer)->get(route('hr.payroll'))->assertForbidden();
        $this->actingAs($officer)->get(route('people.hr', 'loans'))->assertOk();
        $this->actingAs($officer)->get(route('people.reports.download', ['report' => 'payroll']))->assertForbidden();
        $this->actingAs($officer)->get(route('hr.attendance'))->assertOk();
        $this->actingAs($officer)->get(route('hr.employees'))->assertOk()->assertDontSee('Basic salary');
    }

    public function test_an_hr_officer_sees_no_salary_and_their_edits_leave_it_alone(): void
    {
        $id = $this->staff();

        Volt::actingAs($this->officer())->test('hr.employees')
            ->call('edit', $id)
            ->assertSet('salary', '')
            ->assertDontSee('Basic salary')
            ->set('job_title', 'Senior Crew')
            ->call('save')
            ->assertHasNoErrors();

        $row = DB::table('employees')->where('employee_id', $id)->first();
        $this->assertSame('Senior Crew', $row->job_title);
        $this->assertEquals(600, (float) $row->daily_rate, 'pay is untouched');
        $this->assertEquals(15600, (float) $row->salary);
        $this->actingAs($this->officer())->get(route('hr.operations.employee', $id))->assertOk()->assertDontSee('15,600');
    }

    public function test_an_hr_officer_signs_in_through_the_employee_portal(): void
    {
        $officer = $this->officer();

        Volt::test('auth.employeelogin')->set('username', $officer->username)->set('password', 'Password123!')
            ->call('login')->assertRedirect(route('hr.home'));
        auth()->logout();

        Volt::test('auth.adminlogin')->set('username', $officer->username)->set('password', 'Password123!')
            ->call('login')->assertHasErrors('username');
    }

    public function test_the_hr_supervisor_still_sees_pay(): void
    {
        $admin = User::where('username', 'hr')->firstOrFail();
        $id = $this->staff();

        $this->actingAs($admin)->get(route('hr.payroll'))->assertOk();
        Volt::actingAs($admin)->test('hr.employees')->call('edit', $id)->assertSet('salary', 600.0)->assertSee('Basic salary');
    }
}
