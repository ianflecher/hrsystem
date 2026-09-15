<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PayrollRun;
use App\Services\ThirteenthMonth;
use App\Support\PayPeriod;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A payslip is the one page an employee is most likely to show somebody else -
 * a landlord, a lender - so it has to add up, and it must never be the wrong
 * person's.
 */
class PayslipTest extends TestCase
{
    use DatabaseTransactions;

    private User $staff;
    private User $other;
    private User $hr;
    private int $employeeId;
    private int $payrollId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hr = User::where('username', 'hr')->firstOrFail();
        $this->staff = $this->person();
        $this->other = $this->person();

        $this->employeeId = (int) DB::table('employees')->where('user_id', $this->staff->user_id)->value('employee_id');

        for ($day = \Carbon\Carbon::parse('2021-05-01'); $day->lte(\Carbon\Carbon::parse('2021-05-15')); $day->addDay()) {
            DB::table('hr_attendance')->insert(['employee_id' => $this->employeeId, 'date' => $day->toDateString(),
                'time_in' => $day->toDateString().' 08:00:00', 'time_out' => $day->toDateString().' 17:00:00',
                'status' => 'present', 'created_at' => now(), 'updated_at' => now()]);
        }

        $this->payrollId = (int) (new PayrollRun)->generate($this->employeeId, PayPeriod::fromStart('2021-05-01'));
    }

    private function person(): User
    {
        $token = bin2hex(random_bytes(5));

        $user = User::create(['full_name' => 'Payslip '.$token, 'username' => 'slip'.$token,
            'email' => $token.'@example.test', 'password' => 'Password123!', 'role' => 'employee']);

        DB::table('employees')->insert(['user_id' => $user->user_id, 'job_title' => 'Screen Printing Operator',
            'hire_date' => '2020-01-01', 'salary' => 22000, 'status' => 'active', 'shift_start' => '08:00:00',
            'shift_end' => '17:00:00', 'created_at' => now(), 'updated_at' => now()]);

        return $user;
    }

    public function test_an_employee_can_open_their_own_payslip(): void
    {
        $this->actingAs($this->staff)->get('/payslip/'.$this->payrollId)
            ->assertOk()
            ->assertSee($this->staff->full_name)
            ->assertSee('Screen Printing Operator')
            ->assertSee('Imprint Customs PH')
            ->assertSee('Net pay');
    }

    public function test_it_is_nobody_elses_to_open(): void
    {
        $this->actingAs($this->other)->get('/payslip/'.$this->payrollId)->assertForbidden();
    }

    public function test_a_guest_is_sent_to_sign_in(): void
    {
        $this->get('/payslip/'.$this->payrollId)->assertRedirect();
    }

    public function test_hr_can_open_anybodys(): void
    {
        $this->actingAs($this->hr)->get('/payslip/'.$this->payrollId)->assertOk()->assertSee($this->staff->full_name);
    }

    public function test_the_figures_on_it_add_up(): void
    {
        $row = DB::table('hr_payroll')->where('payroll_id', $this->payrollId)->first();

        // Whatever is shown as withheld has to equal the total it shows.
        $parts = (float) $row->sss + (float) $row->philhealth + (float) $row->pagibig
            + (float) $row->tax + (float) $row->time_deduction + (float) $row->loan_deduction;

        $this->assertEqualsWithDelta((float) $row->deductions, $parts, 0.01,
            'every peso withheld must be named on the payslip');
        $this->assertEqualsWithDelta((float) $row->gross_pay - (float) $row->deductions, (float) $row->net_pay, 0.01);

        $this->actingAs($this->staff)->get('/payslip/'.$this->payrollId)
            ->assertSee(number_format((float) $row->net_pay, 2))
            ->assertSee(number_format((float) $row->gross_pay, 2));
    }

    public function test_a_thirteenth_month_payslip_reads_as_one(): void
    {
        (new ThirteenthMonth)->generate(2021);

        $id = DB::table('hr_payroll')->where('employee_id', $this->employeeId)
            ->where('kind', ThirteenthMonth::KIND)->value('payroll_id');

        $this->actingAs($this->staff)->get('/payslip/'.$id)
            ->assertOk()
            ->assertSee('13th month pay')
            ->assertDontSee('Basic pay for the period');
    }

    public function test_a_payslip_that_does_not_exist_is_a_404(): void
    {
        $this->actingAs($this->hr)->get('/payslip/99999999')->assertNotFound();
    }
}
