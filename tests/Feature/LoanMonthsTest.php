<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** A loan starts on a 1-15 cutoff from this month to December, and counts what was paid before. */
class LoanMonthsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_a_loan_starts_this_year_and_counts_what_was_paid_before(): void
    {
        $hr = User::where('username', 'hr')->firstOrFail();
        $t = bin2hex(random_bytes(4));
        $u = User::create(['full_name' => 'Loan '.$t, 'username' => 'ln'.$t, 'email' => $t.'@example.test', 'password' => 'Password123!', 'role' => 'employee']);
        $id = DB::table('employees')->insertGetId(['user_id' => $u->user_id, 'job_title' => 'Crew', 'hire_date' => '2024-01-01', 'salary' => 15600, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($hr)->post(route('people.hr.store', 'loans'), ['employee_id' => $id, 'type' => 'sss', 'monthly' => 1000,
            'starts_on' => now()->startOfYear()->addYear()->toDateString(), 'reason' => 'SSS salary loan'])->assertSessionHasErrors('starts_on');

        $this->actingAs($hr)->post(route('people.hr.store', 'loans'), ['employee_id' => $id, 'type' => 'sss', 'monthly' => 1000,
            'starts_on' => now()->startOfMonth()->addMonthNoOverflow()->toDateString(), 'paid_before' => 6000, 'reason' => 'SSS salary loan'])->assertSessionHasNoErrors();

        $loan = DB::table('employee_loans')->where('employee_id', $id)->first();
        $this->assertEquals(6000, (float) $loan->paid_before);

        $this->actingAs($hr)->post(route('people.hr.action', ['loans', $loan->id]), ['action' => 'paid_before', 'paid_before' => 8000]);
        $this->assertEquals(8000, (float) DB::table('employee_loans')->where('id', $loan->id)->value('paid_before'));
        $this->actingAs($hr)->get(route('people.hr', 'loans'))->assertOk()->assertSee('8,000.00 before this system');

        // Edit: a new amortization and agency, and the change is saved.
        $this->actingAs($hr)->post(route('people.hr.action', ['loans', $loan->id]), ['action' => 'edit', 'type' => 'pagibig', 'monthly' => 1250,
            'starts_on' => now()->startOfMonth()->toDateString(), 'paid_before' => 500, 'reason' => 'Corrected from the notice'])->assertSessionHasNoErrors();
        $edited = DB::table('employee_loans')->where('id', $loan->id)->first();
        $this->assertSame(['pagibig', 1250.0, 500.0], [$edited->type, (float) $edited->installment, (float) $edited->paid_before]);
    }
}
