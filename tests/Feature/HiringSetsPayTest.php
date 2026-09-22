<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Hiring used to write salary 0.00 and status active, which is one "Active
 * employees without salary" exception - and the payroll control centre
 * refuses to approve any period while one exists. So a single hire stopped
 * the whole company's payslips, silently.
 */
class HiringSetsPayTest extends TestCase
{
    private array $made = [];
    private array $apps = [];

    protected function tearDown(): void
    {
        DB::table('job_offers')->whereIn('application_id', $this->apps)->delete();
        DB::table('job_applications')->whereIn('application_id', $this->apps)->delete();
        foreach ($this->made as $id) {
            DB::table('employees')->where('user_id', $id)->delete();
            DB::table('users')->where('user_id', $id)->delete();
        }
        parent::tearDown();
    }

    private function shortlisted(): array
    {
        $n = random_int(100000, 999999);
        $u = User::create(['full_name' => 'Hire Me', 'username' => "hire{$n}",
            'email' => "hire{$n}@example.test", 'password' => 'x', 'role' => 'employee']);
        $this->made[] = $u->user_id;

        $id = DB::table('job_applications')->insertGetId([
            'user_id' => $u->user_id, 'position_applied' => 'Press Operator', 'years_experience' => '',
            'status' => 'shortlisted', 'application_date' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->apps[] = $id;

        return [$u, $id];
    }

    private function blockedCount(): int
    {
        return DB::table('employees')->where('status', 'active')->where(function ($q) {
            $q->where(fn ($x) => $x->where('pay_basis', 'monthly')->where('salary', '<=', 0))
              ->orWhere(fn ($x) => $x->whereIn('pay_basis', ['daily', 'hourly'])->where('daily_rate', '<=', 0));
        })->count();
    }

    public function test_an_offer_refuses_without_a_salary(): void
    {
        $hr = User::where('username', 'hr')->first();
        [$u, $id] = $this->shortlisted();

        Volt::actingAs($hr)->test('hr.applications')
            ->call('openHireModal', $id)
            ->set('hireSalary', '')
            ->set('hireResponsibilities', 'Run the press and check every batch before it leaves.')
            ->set('hireStartsOn', now()->addWeek()->toDateString())
            ->call('confirmHire')
            ->assertHasErrors('hireSalary');

        $this->assertSame('shortlisted',
            DB::table('job_applications')->where('application_id', $id)->value('status'),
            'it offered anyway');

        $this->assertNull(DB::table('employees')->where('user_id', $u->user_id)->first(),
            'an employee record was created without pay');
    }

    public function test_an_accepted_offer_does_not_block_payroll(): void
    {
        $hr = User::where('username', 'hr')->first();
        [$u, $id] = $this->shortlisted();

        $blockedBefore = $this->blockedCount();

        Volt::actingAs($hr)->test('hr.applications')
            ->call('openHireModal', $id)
            ->set('hireSalary', '18500')
            ->set('hireResponsibilities', 'Run the press and check every batch before it leaves.')
            ->set('hireStartsOn', now()->addWeek()->toDateString())
            ->call('confirmHire')
            ->assertHasNoErrors();

        Volt::actingAs($u)->test('applicant.index')->call('acceptOffer')->assertHasNoErrors();

        $employee = DB::table('employees')->where('user_id', $u->user_id)->first();
        $this->assertNotNull($employee, 'nobody was hired');
        $this->assertSame('active', $employee->status);
        $this->assertEquals(18500, $employee->salary);
        $this->assertSame('hired', DB::table('job_applications')->where('application_id', $id)->value('status'));

        $this->assertSame($blockedBefore, $this->blockedCount(),
            'the new hire is blocking payroll approval for everybody');
    }

    public function test_a_daily_paid_offer_needs_a_daily_rate(): void
    {
        $hr = User::where('username', 'hr')->first();
        [$u, $id] = $this->shortlisted();

        $c = Volt::actingAs($hr)->test('hr.applications')
            ->call('openHireModal', $id)
            ->set('hirePayBasis', 'daily')
            ->set('hireSalary', '')
            ->set('hireDailyRate', '')
            ->set('hireResponsibilities', 'Run the press and check every batch before it leaves.')
            ->set('hireStartsOn', now()->addWeek()->toDateString());

        $c->call('confirmHire')->assertHasErrors('hireDailyRate');

        $c->set('hireDailyRate', '650')->call('confirmHire')->assertHasNoErrors();

        Volt::actingAs($u)->test('applicant.index')->call('acceptOffer')->assertHasNoErrors();

        $employee = DB::table('employees')->where('user_id', $u->user_id)->first();
        $this->assertEquals(650, $employee->daily_rate);
        $this->assertSame('daily', $employee->pay_basis);
        $this->assertSame($this->blockedCount(), $this->blockedCount());
    }
}
