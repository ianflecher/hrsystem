<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A payslip whose lines do not add up to its own total is worse than no
 * payslip: it invites the reader to believe one of the two numbers.
 *
 * The details panel printed the *monthly* salary as an earnings line and then
 * a half-month gross beneath it, so 15,000 of earnings produced a 10,000
 * gross, and the allowance appeared nowhere but a footnote.
 */
class PayslipAddsUpTest extends TestCase
{
    private int $employeeId;
    private int $userId;
    private int $payrollId;

    protected function setUp(): void
    {
        parent::setUp();

        $n = random_int(100000, 999999);
        $u = User::create([
            'full_name' => 'Payslip Reader', 'username' => "psr{$n}",
            'email' => "psr{$n}@example.test", 'password' => 'x', 'role' => 'employee',
        ]);
        $this->userId = $u->user_id;

        $this->employeeId = DB::table('employees')->insertGetId([
            'user_id' => $u->user_id, 'job_title' => 'Operator', 'hire_date' => '2020-01-01',
            'salary' => 15000, 'allowance' => 5000, 'pay_basis' => 'monthly', 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // One cutoff: half the monthly rate, half the monthly allowance.
        $this->payrollId = DB::table('hr_payroll')->insertGetId([
            'employee_id' => $this->employeeId,
            'period_start' => '2020-06-16', 'period_end' => '2020-06-30',
            'basic_pay' => 7500, 'allowance' => 2500, 'overtime_pay' => 0,
            'holiday_pay' => 0, 'nsd_pay' => 0, 'time_deduction' => 0, 'loan_deduction' => 0,
            'gross_pay' => 10000, 'sss' => 500, 'philhealth' => 250, 'pagibig' => 100,
            'tax' => 0, 'deductions' => 850, 'net_pay' => 9150,
            'kind' => 'regular', 'status' => 'calculated',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        DB::table('hr_payroll')->where('employee_id', $this->employeeId)->delete();
        DB::table('employees')->where('employee_id', $this->employeeId)->delete();
        DB::table('users')->where('user_id', $this->userId)->delete();

        parent::tearDown();
    }

    private function printable(): string
    {
        $hr = User::where('username', 'hr')->first();

        return $this->actingAs($hr)->get("/payslip/{$this->payrollId}")->assertOk()->getContent();
    }

    public function test_the_printable_names_the_allowance_instead_of_burying_it_in_basic(): void
    {
        $html = $this->printable();

        $this->assertStringContainsString('Allowance', $html,
            'the allowance is not named on the payslip');

        // Basic is the half month actually earned, not the 15,000 rate and not
        // the 10,000 gross with the allowance folded into it.
        $this->assertStringContainsString('7,500.00', $html);
        $this->assertStringContainsString('2,500.00', $html);
    }

    /** The earnings lines must come to the gross, with nothing unexplained. */
    public function test_the_earnings_lines_come_to_the_gross(): void
    {
        $p = DB::table('hr_payroll')->where('payroll_id', $this->payrollId)->first();

        $named = (float) $p->basic_pay + (float) $p->allowance + (float) $p->overtime_pay
            + (float) $p->holiday_pay + (float) $p->nsd_pay;

        $this->assertEquals((float) $p->gross_pay, round($named, 2),
            'the named earnings do not account for the gross');

        // And nothing is labelled "Other", which is what the payslip falls
        // back to when a component has no line of its own.
        $this->assertStringNotContainsString('Other Earnings', $this->printable());
    }

    /**
     * A component with no line of its own is shown rather than hidden. It
     * would be a bug, but a visible one - silently folding it into basic pay
     * is how the allowance went missing in the first place.
     */
    public function test_an_unexplained_amount_is_shown_rather_than_swallowed(): void
    {
        DB::table('hr_payroll')->where('payroll_id', $this->payrollId)
            ->update(['gross_pay' => 10400]);   // 400 the named lines cannot explain

        $html = $this->printable();

        $this->assertStringContainsString('Other Earnings', $html);
        $this->assertStringContainsString('400.00', $html);
    }

    public function test_the_deductions_still_come_to_the_total(): void
    {
        $p = DB::table('hr_payroll')->where('payroll_id', $this->payrollId)->first();

        $named = (float) $p->sss + (float) $p->philhealth + (float) $p->pagibig
            + (float) $p->tax + (float) $p->time_deduction + (float) $p->loan_deduction;

        $this->assertEquals((float) $p->deductions, round($named, 2));
        $this->assertEquals((float) $p->gross_pay - (float) $p->deductions, (float) $p->net_pay);
    }
}
