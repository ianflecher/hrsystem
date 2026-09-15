<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Payroll is the one thing here that moves money, so these pin down the
 * arithmetic and the states rather than just checking the screen renders.
 */
class PayrollRunTest extends TestCase
{
    private array $createdUserIds = [];
    private string $period;

    protected function setUp(): void
    {
        parent::setUp();
        // A period far enough back that a real run would not collide with it.
        $this->period = '2019-03';
    }

    protected function tearDown(): void
    {
        DB::table('hr_payroll')->where('period_start', $this->period.'-01')->delete();

        foreach ($this->createdUserIds as $id) {
            DB::table('hr_payroll')->whereIn('employee_id', function ($q) use ($id) {
                $q->select('employee_id')->from('employees')->where('user_id', $id);
            })->delete();
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

    private function employee(float $salary, string $status = 'active'): int
    {
        $n = random_int(100000, 999999);

        $user = User::create([
            'full_name' => 'Payroll Subject',
            'username'  => "pay{$n}",
            'email'     => "pay{$n}@example.test",
            'password'  => 'Password!2345',
            'role'      => 'employee',
        ]);
        $this->createdUserIds[] = $user->user_id;

        return DB::table('employees')->insertGetId([
            'user_id'    => $user->user_id,
            'job_title'  => 'Subject',
            'hire_date'  => '2019-01-01',
            'salary'     => $salary,
            'status'     => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function screen()
    {
        return Volt::actingAs($this->hr())->test('hr.payroll')->set('payPeriod', $this->period);
    }

    public function test_a_run_creates_one_payslip_for_each_active_employee(): void
    {
        $a = $this->employee(25000);
        $b = $this->employee(18000);

        $this->screen()->call('generatePeriod');

        foreach ([$a, $b] as $id) {
            $this->assertDatabaseHas('hr_payroll', [
                'employee_id'  => $id,
                'period_start' => $this->period.'-01',
                'status'       => 'calculated',
            ]);
        }
    }

    public function test_tax_is_charged_after_the_statutory_contributions(): void
    {
        // 25,000 gross: SSS 1,350, PhilHealth 500, Pag-IBIG 100 = 1,950.
        // Taxable is therefore 23,050, and the first bracket starts at 20,833,
        // so tax is (23,050 - 20,833) x 15% = 332.55.
        $id = $this->employee(25000);

        $this->screen()->call('generatePeriod');

        $row = DB::table('hr_payroll')->where('employee_id', $id)->first();

        $this->assertEqualsWithDelta(2282.55, (float) $row->deductions, 0.01,
            'deductions should be the three contributions plus tax on what is left');
        $this->assertEqualsWithDelta(22717.45, (float) $row->net_pay, 0.01);
        $this->assertEqualsWithDelta(25000.00, (float) $row->gross_pay, 0.01);
    }

    public function test_someone_below_the_threshold_pays_no_tax(): void
    {
        // 15,000 gross: SSS 900, PhilHealth 300, Pag-IBIG 100 = 1,300.
        // Taxable 13,700, which is under the 20,833 exemption.
        $id = $this->employee(15000);

        $this->screen()->call('generatePeriod');

        $row = DB::table('hr_payroll')->where('employee_id', $id)->first();

        $this->assertEqualsWithDelta(1300.00, (float) $row->deductions, 0.01);
        $this->assertEqualsWithDelta(13700.00, (float) $row->net_pay, 0.01);
    }

    public function test_a_second_run_does_not_duplicate_anyone(): void
    {
        $id = $this->employee(20000);

        $this->screen()->call('generatePeriod');
        $this->screen()->call('generatePeriod');

        $this->assertSame(1, DB::table('hr_payroll')
            ->where('employee_id', $id)
            ->where('period_start', $this->period.'-01')
            ->count(), 'running the period twice must not pay anyone twice');
    }

    public function test_employees_without_a_salary_or_not_active_are_skipped(): void
    {
        $noSalary = $this->employee(0);
        $inactive = $this->employee(20000, 'inactive');

        $this->screen()->call('generatePeriod');

        foreach ([$noSalary, $inactive] as $id) {
            $this->assertDatabaseMissing('hr_payroll', [
                'employee_id'  => $id,
                'period_start' => $this->period.'-01',
            ]);
        }
    }

    public function test_a_payslip_moves_calculated_then_approved_then_paid(): void
    {
        $id = $this->employee(22000);
        $where = ['employee_id' => $id, 'period_start' => $this->period.'-01'];

        $screen = $this->screen();

        $screen->call('generatePeriod');
        $this->assertSame('calculated', DB::table('hr_payroll')->where($where)->value('status'));

        $screen->call('approvePeriod');
        $this->assertSame('approved', DB::table('hr_payroll')->where($where)->value('status'));

        $screen->call('markPeriodPaid');
        $this->assertSame('paid', DB::table('hr_payroll')->where($where)->value('status'));
    }

    public function test_approving_does_not_reach_back_into_an_earlier_period(): void
    {
        $id = $this->employee(20000);

        $this->screen()->call('generatePeriod');

        // A different period is left alone entirely.
        Volt::actingAs($this->hr())->test('hr.payroll')
            ->set('payPeriod', '2019-04')
            ->call('approvePeriod');

        $this->assertSame('calculated', DB::table('hr_payroll')
            ->where('employee_id', $id)
            ->where('period_start', $this->period.'-01')
            ->value('status'));
    }
}
