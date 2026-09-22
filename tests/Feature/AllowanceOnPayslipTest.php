<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PayrollRun;
use App\Support\PayPeriod;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** The allowance has to survive the whole run and land on the payslip. */
class AllowanceOnPayslipTest extends TestCase
{
    private array $made = [];

    protected function tearDown(): void
    {
        foreach ($this->made as $id) {
            $e = DB::table('employees')->where('user_id', $id)->value('employee_id');
            DB::table('hr_payroll')->where('employee_id', $e)->delete();
            DB::table('hr_attendance')->where('employee_id', $e)->delete();
            DB::table('employees')->where('user_id', $id)->delete();
            DB::table('users')->where('user_id', $id)->delete();
        }
        parent::tearDown();
    }

    public function test_the_allowance_reaches_the_payslip_and_is_charged_on(): void
    {
        $n = random_int(100000, 999999);
        $u = User::create(['full_name' => 'Allowance Person', 'username' => "allw{$n}",
            'email' => "allw{$n}@example.test", 'password' => 'x', 'role' => 'employee']);
        $this->made[] = $u->user_id;

        $employeeId = DB::table('employees')->insertGetId([
            'user_id' => $u->user_id, 'job_title' => 'Operator', 'hire_date' => '2026-01-01',
            'salary' => 20000, 'allowance' => 2000, 'pay_basis' => 'monthly',
            'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);

        // Present every day of the cutoff, so nothing is deducted for time.
        for ($d = \Carbon\Carbon::parse('2026-09-16'); $d->lte(\Carbon\Carbon::parse('2026-09-30')); $d->addDay()) {
            DB::table('hr_attendance')->insert([
                'employee_id' => $employeeId, 'date' => $d->toDateString(),
                'time_in' => $d->toDateString().' 08:00:00', 'time_out' => $d->toDateString().' 17:00:00',
                'status' => 'present', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        app(PayrollRun::class)->generate($employeeId, PayPeriod::fromStart("2026-09-16"));

        $slip = DB::table('hr_payroll')->where('employee_id', $employeeId)->first();
        $this->assertNotNull($slip, 'no payslip was produced');

        fwrite(STDERR, sprintf("\n  basic %s + allowance %s = gross %s, net %s",
            $slip->basic_pay, $slip->allowance, $slip->gross_pay, $slip->net_pay));

        // A monthly allowance arrives half per cutoff, like basic pay.
        $this->assertEquals(1000, $slip->allowance);
        $this->assertEquals(10000, $slip->basic_pay);
        $this->assertEquals(11000, $slip->gross_pay, 'gross should carry the allowance');

        // Contributions follow the whole monthly compensation, so a 20,000
        // salary with a 2,000 allowance is charged as 22,000, not as 20,000.
        $basicOnly = \App\Support\PayrollCalculator::forCutoff(20000);
        $whole = \App\Support\PayrollCalculator::forCutoff(
            20000, allowance: 1000, monthlyStatutoryBase: 22000);

        $this->assertEquals($whole['sss'], $slip->sss, 'the allowance was left out of the SSS base');
        $this->assertGreaterThan((float) $basicOnly['sss'], (float) $slip->sss,
            'the allowance is not being charged on');

        // And it is visible on the payslip rather than buried in gross.
        $this->assertStringContainsString('Allowance', (string) $slip->notes);

        fwrite(STDERR, sprintf("\n  sss on basic alone %s -> on the whole %s\n\n",
            number_format($basicOnly['sss'], 2), number_format((float) $slip->sss, 2)));
    }
}
