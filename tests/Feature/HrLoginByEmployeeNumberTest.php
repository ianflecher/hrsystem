<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * HR can sign in with the employee number on their ID card.
 *
 * The staff portal already accepted email, username or employee number; the
 * HR sign-in took only the first two. And the HR person had two accounts - an
 * admin one for the back office, and an employee one from the masterlist
 * holding her employee number - so her number led to the wrong account
 * entirely.
 */
class HrLoginByEmployeeNumberTest extends TestCase
{
    private array $users = [];
    private array $employees = [];

    protected function tearDown(): void
    {
        DB::table('employees')->whereIn('employee_id', $this->employees)->delete();
        DB::table('users')->whereIn('user_id', $this->users)->delete();
        parent::tearDown();
    }

    private function account(string $role, string $number): string
    {
        $n = random_int(100000, 999999);

        $id = DB::table('users')->insertGetId([
            'full_name' => 'Login Person', 'username' => "lg{$n}",
            'email' => "lg{$n}@example.test", 'password' => Hash::make('correct-horse-9'),
            'role' => $role, 'must_change_password' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->users[] = $id;

        $this->employees[] = DB::table('employees')->insertGetId([
            'user_id' => $id, 'employee_no' => $number, 'job_title' => 'HR Supervisor',
            'hire_date' => '2021-01-09', 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return "lg{$n}";
    }

    private function number(): string
    {
        return 'TEST-'.random_int(100000, 999999);
    }

    public function test_hr_signs_in_with_their_employee_number(): void
    {
        $number = $this->number();
        $this->account('admin', $number);

        Volt::test('auth.adminlogin')
            ->set('username', $number)
            ->set('password', 'correct-horse-9')
            ->call('login')
            ->assertHasNoErrors();

        $this->assertAuthenticated();
    }

    /** Case should not matter: people type ic-00006 as often as IC-00006. */
    public function test_the_number_is_not_case_sensitive(): void
    {
        $number = $this->number();
        $this->account('admin', $number);

        Volt::test('auth.adminlogin')
            ->set('username', strtolower($number))
            ->set('password', 'correct-horse-9')
            ->call('login')
            ->assertHasNoErrors();

        $this->assertAuthenticated();
    }

    public function test_username_still_works(): void
    {
        $username = $this->account('admin', $this->number());

        Volt::test('auth.adminlogin')
            ->set('username', $username)
            ->set('password', 'correct-horse-9')
            ->call('login')
            ->assertHasNoErrors();

        $this->assertAuthenticated();
    }

    public function test_the_wrong_password_is_still_refused(): void
    {
        $number = $this->number();
        $this->account('admin', $number);

        Volt::test('auth.adminlogin')
            ->set('username', $number)
            ->set('password', 'not-the-password')
            ->call('login')
            ->assertHasErrors('username');

        $this->assertGuest();
    }

    /**
     * An employee number does not open the HR back office for somebody who
     * is not HR. Accepting the number widened how people are found, not who
     * is let in.
     */
    public function test_an_ordinary_employees_number_does_not_open_hr(): void
    {
        $number = $this->number();
        $this->account('employee', $number);

        Volt::test('auth.adminlogin')
            ->set('username', $number)
            ->set('password', 'correct-horse-9')
            ->call('login')
            ->assertHasErrors('username');

        $this->assertGuest();
    }

    /**
     * The hr role existed and nobody could use it: the sign-in only let
     * 'admin' through, and sent everybody not literally called 'hr' to the
     * system admin dashboard.
     */
    public function test_an_hr_officer_signs_in_and_lands_on_hr(): void
    {
        $number = $this->number();
        $this->account('hr', $number);

        Volt::test('auth.employeelogin')
            ->set('username', $number)
            ->set('password', 'correct-horse-9')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('hr.home'));

        $this->assertAuthenticated();
    }

    /** The real account: one person, one login, carrying her number. */
    public function test_the_hr_account_carries_her_employee_number(): void
    {
        $hr = DB::table('users as u')
            ->join('employees as e', 'e.user_id', '=', 'u.user_id')
            ->where('u.username', 'hr')
            ->select('e.employee_no')
            ->first();

        if (! $hr) {
            $this->markTestSkipped('the hr account has no employee record here');
        }

        $this->assertSame('IC-00006', $hr->employee_no);
        $this->assertSame(1, DB::table('employees')->where('employee_no', 'IC-00006')->count(),
            'IC-00006 is on more than one account again');
    }
}
