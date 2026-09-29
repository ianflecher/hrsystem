<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SalaryHistory;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * A raise is logged, not overwritten.
 *
 * employee_salary_history existed and the employee profile already read it,
 * but nothing had ever written to it: a raise replaced the old figure and the
 * old figure was gone. When somebody disputes a payslip, or an inspector asks
 * what a person was earning in March, the record has to exist.
 */
class SalaryHistoryTest extends TestCase
{
    private int $employeeId;
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();

        $n = random_int(100000, 999999);
        $u = User::create([
            'full_name' => 'Raise Person', 'username' => "rp{$n}",
            'email' => "rp{$n}@example.test", 'password' => 'x', 'role' => 'employee',
        ]);
        $this->userId = $u->user_id;

        $this->employeeId = DB::table('employees')->insertGetId([
            'user_id' => $u->user_id, 'job_title' => 'Operator', 'hire_date' => '2026-01-01',
            'salary' => 15000, 'allowance' => 5000, 'pay_basis' => 'monthly', 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        DB::table('employee_salary_history')->where('employee_id', $this->employeeId)->delete();
        DB::table('audit_logs')->where('table_name', 'employees')->where('record_id', $this->employeeId)->delete();
        DB::table('employees')->where('employee_id', $this->employeeId)->delete();
        DB::table('users')->where('user_id', $this->userId)->delete();

        parent::tearDown();
    }

    private function rows()
    {
        return DB::table('employee_salary_history')->where('employee_id', $this->employeeId)
            ->orderBy('id')->get();
    }

    public function test_a_raise_leaves_the_old_figure_behind_it(): void
    {
        SalaryHistory::record($this->employeeId, 15000, 5000, effectiveFrom: '2026-01-01', reason: 'Starting pay');
        SalaryHistory::record($this->employeeId, 18000, 5000, effectiveFrom: '2026-07-01', reason: 'Annual increase');

        $rows = $this->rows();

        $this->assertCount(2, $rows, 'the raise overwrote the old figure');

        // The old rate is closed off the day before the new one starts.
        $this->assertEquals(15000, $rows[0]->salary);
        $this->assertSame('2026-06-30', $rows[0]->effective_until);

        // The current one stays open.
        $this->assertEquals(18000, $rows[1]->salary);
        $this->assertNull($rows[1]->effective_until);
        $this->assertSame('Annual increase', $rows[1]->reason);
    }

    public function test_the_allowance_is_part_of_what_is_recorded(): void
    {
        SalaryHistory::record($this->employeeId, 15000, 5000);
        SalaryHistory::record($this->employeeId, 15000, 7000, reason: 'Allowance increase');

        $rows = $this->rows();

        $this->assertCount(2, $rows, 'an allowance change was not logged');
        $this->assertEquals(7000, $rows[1]->allowance);
    }

    /** Editing a shift should not litter the log with entries saying nothing. */
    public function test_nothing_is_logged_when_the_pay_has_not_moved(): void
    {
        $this->assertTrue(SalaryHistory::record($this->employeeId, 15000, 5000));
        $this->assertFalse(SalaryHistory::record($this->employeeId, 15000, 5000),
            'an unchanged pay wrote a log entry');

        $this->assertCount(1, $this->rows());
    }

    public function test_what_somebody_was_on_at_a_given_date_can_be_read_back(): void
    {
        SalaryHistory::record($this->employeeId, 15000, 5000, effectiveFrom: '2026-01-01');
        SalaryHistory::record($this->employeeId, 18000, 5000, effectiveFrom: '2026-07-01');

        $this->assertEquals(15000, SalaryHistory::onDate($this->employeeId, '2026-03-15')->salary,
            'March read back the wrong rate');
        $this->assertEquals(15000, SalaryHistory::onDate($this->employeeId, '2026-06-30')->salary,
            'the last day on the old rate read back wrong');
        $this->assertEquals(18000, SalaryHistory::onDate($this->employeeId, '2026-07-01')->salary);
        $this->assertEquals(18000, SalaryHistory::onDate($this->employeeId, '2026-12-31')->salary);

        // Before they were ever paid, there is nothing to read.
        $this->assertNull(SalaryHistory::onDate($this->employeeId, '2025-12-31'));
    }

    public function test_a_change_is_also_written_to_the_audit_log(): void
    {
        SalaryHistory::record($this->employeeId, 15000, 5000);
        SalaryHistory::record($this->employeeId, 18000, 5000, reason: 'Promotion');

        $entry = DB::table('audit_logs')->where('table_name', 'employees')
            ->where('record_id', $this->employeeId)->where('action', 'pay_changed')
            ->orderByDesc('id')->first();

        $this->assertNotNull($entry, 'a pay change left no audit entry');

        $before = json_decode($entry->old_values, true);
        $after = json_decode($entry->new_values, true);

        $this->assertEquals(15000, $before['salary'], 'the audit entry lost what they were on');
        $this->assertEquals(18000, $after['salary']);
        $this->assertSame('Promotion', $after['reason']);
    }

    /** The whole point: HR raising somebody through the screen is logged. */
    public function test_raising_somebody_through_the_screen_is_logged(): void
    {
        $hr = User::where('username', 'hr')->first();

        Volt::actingAs($hr)->test('hr.employees')
            ->call('edit', $this->employeeId)
            ->set('salary', '700')
            ->set('payChangeReason', 'Annual increase')
            ->call('save')
            ->assertHasNoErrors();

        // Entered by the day; the monthly figure is 26 days of it.
        $this->assertEquals(18200,
            DB::table('employees')->where('employee_id', $this->employeeId)->value('salary'));
        $this->assertEquals(700,
            DB::table('employees')->where('employee_id', $this->employeeId)->value('daily_rate'));

        $rows = $this->rows();
        $this->assertCount(1, $rows, 'the raise was not logged');
        $this->assertEquals(18200, $rows[0]->salary);
        $this->assertSame('Annual increase', $rows[0]->reason);

        // And it says who did it.
        $this->assertEquals($hr->user_id, $rows[0]->changed_by);
    }

    public function test_an_edit_that_leaves_pay_alone_is_not_logged(): void
    {
        $hr = User::where('username', 'hr')->first();

        Volt::actingAs($hr)->test('hr.employees')
            ->call('edit', $this->employeeId)
            ->set('job_title', 'Senior Operator')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertCount(0, $this->rows(), 'changing a job title wrote a pay entry');
    }
}
