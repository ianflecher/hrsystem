<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * HR creates staff accounts, so for a short window a second person knows the
 * password. These cover that window closing: the account is made, the password
 * is shown once, and it cannot be used for anything until it is replaced.
 */
class EmployeeAccountsTest extends TestCase
{
    private array $createdUserIds = [];

    protected function tearDown(): void
    {
        foreach ($this->createdUserIds as $id) {
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

    private function track(string $username): User
    {
        $user = User::where('username', $username)->firstOrFail();
        $this->createdUserIds[] = $user->user_id;

        return $user;
    }

    public function test_hr_creates_an_account_alongside_the_employee_record(): void
    {
        $n = random_int(100000, 999999);

        $component = Volt::actingAs($this->hr())
            ->test('hr.employees')
            ->call('openCreate')
            ->set('full_name', 'New Starter')
            ->set('username', "starter{$n}")
            ->set('email', "starter{$n}@example.test")
            ->set('job_title', 'Screen Printing Operator')
            ->set('biometric_id', "starter{$n}")
            ->set('hire_date', now()->toDateString())
            ->set('salary', '18000')
            ->set('status', 'active')
            ->set('role', 'employee')
            ->call('save')
            ->assertHasNoErrors();

        $user = $this->track("starter{$n}");

        $this->assertDatabaseHas('employees', [
            'user_id'   => $user->user_id,
            'job_title' => 'Screen Printing Operator',
            'biometric_id' => "starter{$n}",
            'status'    => 'active',
        ]);

        // The generated password is shown once, and it actually works.
        $issued = $component->get('issuedPassword');
        $this->assertNotEmpty($issued, 'a first password should be shown to hand over');
        $this->assertTrue(Hash::check($issued, $user->fresh()->password));

        // ...and the account cannot be used until it is replaced.
        $this->assertEquals(1, DB::table('users')->where('user_id', $user->user_id)->value('must_change_password'));
    }

    public function test_a_duplicate_username_is_refused(): void
    {
        Volt::actingAs($this->hr())
            ->test('hr.employees')
            ->call('openCreate')
            ->set('full_name', 'Clash')
            ->set('username', 'hr')
            ->set('email', 'clash'.random_int(1000, 9999).'@example.test')
            ->set('job_title', 'Anything')
            ->set('hire_date', now()->toDateString())
            ->call('save')
            ->assertHasErrors('username');
    }

    public function test_an_account_owing_a_password_change_is_held_at_that_screen(): void
    {
        $user = $this->makeStarter();

        $this->actingAs($user)->get('/employee/dashboard')->assertRedirect(route('password.change'));
        $this->actingAs($user)->get('/employee/payroll')->assertRedirect(route('password.change'));

        // The screen itself has to stay reachable, or the redirect would loop.
        $this->actingAs($user)->get('/password/change')->assertOk();
    }

    public function test_changing_the_password_releases_the_account(): void
    {
        $user = $this->makeStarter();

        Volt::actingAs($user)
            ->test('auth.change-password')
            ->set('current_password', 'IssuedPass123')
            ->set('password', 'TheirOwnPass456')
            ->set('password_confirmation', 'TheirOwnPass456')
            ->call('save')
            ->assertHasNoErrors();

        $fresh = $user->fresh();
        $this->assertEquals(0, DB::table('users')->where('user_id', $user->user_id)->value('must_change_password'));
        $this->assertTrue(Hash::check('TheirOwnPass456', $fresh->password));

        $this->actingAs($fresh)->get('/employee/dashboard')->assertOk();
    }

    public function test_the_new_password_cannot_be_the_issued_one(): void
    {
        $user = $this->makeStarter();

        Volt::actingAs($user)
            ->test('auth.change-password')
            ->set('current_password', 'IssuedPass123')
            ->set('password', 'IssuedPass123')
            ->set('password_confirmation', 'IssuedPass123')
            ->call('save')
            ->assertHasErrors('password');

        $this->assertEquals(1, DB::table('users')->where('user_id', $user->user_id)->value('must_change_password'));
    }

    public function test_removing_somebody_never_paid_deletes_them_after_confirming(): void
    {
        $user = $this->makeStarter();
        $employeeId = DB::table('employees')->where('user_id', $user->user_id)->value('employee_id');

        $component = Volt::actingAs($this->hr())->test('hr.employees')
            ->call('confirmRemove', $employeeId);

        // Nothing happens on asking - only on confirming.
        $this->assertTrue($component->get('removing')['deletes']);
        $this->assertDatabaseHas('employees', ['employee_id' => $employeeId]);

        $component->call('remove')->assertSet('removing', null);

        $this->assertDatabaseMissing('employees', ['employee_id' => $employeeId]);
        $this->assertDatabaseMissing('users', ['user_id' => $user->user_id]);
    }

    public function test_somebody_with_payslips_is_deactivated_rather_than_deleted(): void
    {
        $user = $this->makeStarter();
        $employeeId = DB::table('employees')->where('user_id', $user->user_id)->value('employee_id');

        DB::table('hr_payroll')->insert(['employee_id' => $employeeId, 'period_start' => '2018-01-01', 'period_end' => '2018-01-15',
            'gross_pay' => 9000, 'deductions' => 0, 'net_pay' => 9000, 'status' => 'paid', 'created_at' => now(), 'updated_at' => now()]);

        Volt::actingAs($this->hr())->test('hr.employees')
            ->call('confirmRemove', $employeeId)
            ->assertSet('removing.deletes', false)
            ->call('remove');

        // The payslip is a record the company keeps, so the person stays too.
        $this->assertDatabaseHas('employees', ['employee_id' => $employeeId, 'status' => 'inactive']);
        $this->assertDatabaseHas('hr_payroll', ['employee_id' => $employeeId]);

        // But the account no longer opens with the password they were given.
        $this->assertFalse(Hash::check('IssuedPass123', $user->fresh()->password));
    }

    public function test_hr_cannot_remove_their_own_account(): void
    {
        $hr = $this->hr();
        $employeeId = DB::table('employees')->where('user_id', $hr->user_id)->value('employee_id');

        // The seeded HR account is a sign-in, not a person on the payroll, so
        // it has no employee record until somebody makes one through the app.
        // This test is about what happens when it does, so it makes one - and
        // takes it away again rather than leaving a salaryless employee behind
        // for the payroll control centre to complain about.
        $temporary = false;
        if (! $employeeId) {
            $employeeId = DB::table('employees')->insertGetId([
                'user_id' => $hr->user_id, 'job_title' => 'HR Supervisor',
                'hire_date' => now()->toDateString(), 'salary' => 30000,
                'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
            ]);
            $temporary = true;
        }

        try {
            Volt::actingAs($hr)->test('hr.employees')
                ->call('confirmRemove', $employeeId)
                ->assertSet('removing', null);

            $this->assertDatabaseHas('users', ['user_id' => $hr->user_id]);
        } finally {
            if ($temporary) {
                DB::table('employees')->where('employee_id', $employeeId)->delete();
            }
        }
    }

    public function test_the_employees_screen_is_closed_to_guests(): void
    {
        $this->get('/hr/employees')->assertRedirect('/admin/login');
    }

    private function makeStarter(): User
    {
        $n = random_int(100000, 999999);

        $user = User::create([
            'full_name' => 'Held Starter',
            'username'  => "held{$n}",
            'email'     => "held{$n}@example.test",
            'password'  => 'IssuedPass123',
            'role'      => 'employee',
        ]);

        DB::table('users')->where('user_id', $user->user_id)->update(['must_change_password' => true]);

        DB::table('employees')->insert([
            'user_id'    => $user->user_id,
            'job_title'  => 'Held',
            'hire_date'  => now()->toDateString(),
            'status'     => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->createdUserIds[] = $user->user_id;

        return $user->fresh();
    }
}
