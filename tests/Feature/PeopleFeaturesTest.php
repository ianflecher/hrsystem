<?php

namespace Tests\Feature;

use App\Http\Controllers\PeopleController;
use App\Models\User;
use App\Services\PayrollRun;
use App\Support\PayPeriod;
use App\Support\PayrollCalculator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PeopleFeaturesTest extends TestCase
{
    use DatabaseTransactions;

    private User $hr;
    private User $staff;
    private User $other;
    private int $employeeId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hr = $this->user('hr');
        $this->staff = $this->user('employee');
        $this->other = $this->user('employee');
        $this->employeeId = (int) DB::table('employees')->where('user_id', $this->staff->user_id)->value('employee_id');
    }

    private function user(string $role): User
    {
        $token = bin2hex(random_bytes(6));
        $user = User::create(['full_name' => 'Feature '.$token, 'username' => 'feature'.$token,
            'email' => $token.'@example.test', 'password' => 'Password123!', 'role' => $role]);
        DB::table('employees')->insert(['user_id' => $user->user_id, 'job_title' => 'Tester', 'hire_date' => '2018-01-01', 'salary' => 22000,
            'status' => 'active', 'shift_start' => '08:00:00', 'created_at' => now(), 'updated_at' => now()]);
        return $user;
    }

    public function test_all_feature_pages_render_for_the_correct_portal(): void
    {
        foreach (PeopleController::MODULES as $module => $label) {
            $this->actingAs($this->hr)->get('/hr/people/'.$module)->assertOk()->assertSee($label);
            $this->actingAs($this->staff)->get('/employee/people/'.$module)->assertOk();
        }
    }

    public function test_employees_cannot_access_hr_pages(): void
    {
        foreach (array_keys(PeopleController::MODULES) as $module) {
            $this->actingAs($this->staff)->get('/hr/people/'.$module)->assertForbidden();
        }
    }

    public function test_documents_are_private_and_expiry_alerts_render(): void
    {
        Storage::fake('local');
        $this->actingAs($this->hr)->post('/hr/people/documents', ['employee_id' => $this->employeeId,
            'title' => 'Employment contract', 'category' => 'contract', 'expires_on' => today()->addDays(5)->toDateString(),
            'document' => UploadedFile::fake()->create('contract.pdf', 10, 'application/pdf')])->assertRedirect()->assertSessionHasNoErrors();
        $doc = DB::table('employee_documents')->where('employee_id', $this->employeeId)->first();
        $this->assertNotNull($doc);
        Storage::disk('local')->assertExists($doc->path);
        $this->actingAs($this->staff)->get('/people/documents/'.$doc->id.'/download')->assertOk();
        $this->get('/employee/people/documents?expiring=1')->assertOk()->assertSee('Employment contract');
        $this->actingAs($this->other)->get('/people/documents/'.$doc->id.'/download')->assertForbidden();
        $this->get('/employee/people/documents')->assertDontSee('Employment contract');
        $this->post('/employee/people/documents/'.$doc->id, ['action' => 'delete'])->assertForbidden();
    }

    public function test_an_employee_uploads_their_own_document(): void
    {
        Storage::fake('local');

        $this->actingAs($this->staff)->post('/employee/people/documents', [
            'title' => 'My NBI clearance', 'category' => 'id',
            'document' => UploadedFile::fake()->create('nbi.pdf', 10, 'application/pdf'),
        ])->assertRedirect()->assertSessionHasNoErrors();

        // Filed against them, not against whoever they might have named.
        $this->assertDatabaseHas('employee_documents', ['employee_id' => $this->employeeId, 'title' => 'My NBI clearance']);
    }

    public function test_application_documents_land_in_the_vault_when_somebody_is_hired(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $applicant = User::create(['full_name' => 'Applicant '.bin2hex(random_bytes(4)), 'username' => 'appl'.bin2hex(random_bytes(4)),
            'email' => bin2hex(random_bytes(4)).'@example.test', 'password' => 'Password123!', 'role' => 'employee']);

        $applicationId = DB::table('job_applications')->insertGetId(['user_id' => $applicant->user_id,
            'position_applied' => 'Screen Printing Operator', 'years_experience' => 2, 'status' => 'pending',
            'application_date' => today()->toDateString(), 'created_at' => now(), 'updated_at' => now()]);

        Storage::disk('public')->put('application_documents/1/resume.pdf', 'their resume');
        DB::table('application_documents')->insert(['application_id' => $applicationId, 'user_id' => $applicant->user_id,
            'filename' => 'resume.pdf', 'filepath' => 'application_documents/1/resume.pdf', 'filetype' => 'application/pdf',
            'filesize' => 12, 'uploaded_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        $employeeId = DB::table('employees')->insertGetId(['user_id' => $applicant->user_id, 'job_title' => 'Hired',
            'hire_date' => today()->toDateString(), 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);

        $carried = (new \App\Services\DocumentVault)->adoptApplicationDocuments($applicant->user_id, $employeeId);

        $this->assertSame(1, $carried);
        $document = DB::table('employee_documents')->where('employee_id', $employeeId)->first();
        $this->assertSame('resume.pdf', $document->original_name);
        Storage::disk('local')->assertExists($document->path);

        // Running it again must not file the same document twice.
        $this->assertSame(0, (new \App\Services\DocumentVault)->adoptApplicationDocuments($applicant->user_id, $employeeId));
        $this->assertSame(1, DB::table('employee_documents')->where('employee_id', $employeeId)->count());
    }

    private function overtime(): int
    {
        $this->actingAs($this->staff)->post('/employee/people/overtime', ['employee_id' => 999999,
            'starts_at' => '2018-01-02T18:00', 'ends_at' => '2018-01-02T20:00', 'reason' => 'Complete the print run'])->assertRedirect()->assertSessionHasNoErrors();
        return (int) DB::table('overtime_requests')->where('employee_id', $this->employeeId)->value('id');
    }

    public function test_overtime_ownership_overlap_approval_and_display(): void
    {
        $id = $this->overtime();
        $this->assertDatabaseHas('overtime_requests', ['id' => $id, 'employee_id' => $this->employeeId, 'minutes' => 120]);
        $this->post('/employee/people/overtime', ['starts_at' => '2018-01-02T19:00', 'ends_at' => '2018-01-02T21:00', 'reason' => 'Overlapping request'])->assertSessionHasErrors('starts_at');
        $this->post('/employee/people/overtime/'.$id, ['action' => 'approve', 'approved_amount' => 300])->assertForbidden();
        $this->actingAs($this->hr)->post('/hr/people/overtime/'.$id, ['action' => 'approve', 'approved_amount' => 300])->assertRedirect();
        $this->assertDatabaseHas('overtime_requests', ['id' => $id, 'status' => 'approved', 'approved_amount' => 300]);
        $this->get('/hr/people/overtime')->assertOk()->assertSee('300.00');
        $this->post('/hr/people/overtime/'.$id, ['action' => 'approve', 'approved_amount' => 500])->assertStatus(422);
    }

    public function test_supervisors_can_only_review_their_assigned_department(): void
    {
        $id = $this->overtime();
        $supervisor = $this->user('supervisor');
        $this->actingAs($supervisor)->post('/employee/people/overtime/'.$id, ['action' => 'approve', 'approved_amount' => 300])->assertForbidden();
        $department = DB::table('departments')->insertGetId(['department_name' => 'Test team', 'supervisor_id' => $supervisor->user_id]);
        DB::table('employees')->where('employee_id', $this->employeeId)->update(['department_id' => $department]);
        $this->post('/employee/people/overtime/'.$id, ['action' => 'approve', 'approved_amount' => 300])->assertRedirect();
    }

    public function test_the_calendar_draws_itself_from_the_employee_and_the_holidays(): void
    {
        // Sunday off, no assignment entered anywhere.
        DB::table('employees')->where('employee_id', $this->employeeId)
            ->update(['shift_start' => '08:00:00', 'shift_end' => '17:00:00', 'rest_days' => '7']);

        // 2018-01-07 was a Sunday, 2018-01-08 a Monday.
        $this->actingAs($this->staff)->get('/employee/people/shifts?month=2018-01')
            ->assertOk()->assertSee('08:00')->assertSee('Rest day')->assertSee('Sunday');

        // The holiday is the only thing HR enters, and it covers everybody.
        $this->actingAs($this->hr)->post('/hr/people/shifts',
            ['date' => '2018-01-08', 'name' => 'Founding Day', 'type' => 'special'])->assertRedirect()->assertSessionHasNoErrors();
        $this->get('/hr/people/shifts?month=2018-01')->assertOk()->assertSee('Founding Day');
        $this->actingAs($this->staff)->get('/employee/people/shifts?month=2018-01')->assertOk()->assertSee('Founding Day');

        // Entering the same date again replaces it rather than colliding.
        $this->actingAs($this->hr)->post('/hr/people/shifts',
            ['date' => '2018-01-08', 'name' => 'Founding Day (moved)', 'type' => 'regular'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, DB::table('holidays')->where('date', '2018-01-08')->count());

        // An employee can neither add nor remove one.
        $id = DB::table('holidays')->where('date', '2018-01-08')->value('id');
        $this->actingAs($this->staff)->post('/employee/people/shifts', ['date' => '2018-01-09', 'name' => 'Mine', 'type' => 'regular'])->assertForbidden();
        $this->post('/employee/people/shifts/'.$id, ['action' => 'delete'])->assertForbidden();

        $this->actingAs($this->hr)->post('/hr/people/shifts/'.$id, ['action' => 'delete'])->assertRedirect();
        $this->assertDatabaseMissing('holidays', ['id' => $id]);
    }

    public function test_leaving_early_is_deducted_and_named_on_the_payslip(): void
    {
        DB::table('employees')->where('employee_id', $this->employeeId)
            ->update(['shift_start' => '08:00:00', 'shift_end' => '17:00:00', 'rest_days' => null, 'salary' => 22000]);

        // On time in, half an hour early out.
        DB::table('hr_attendance')->insert(['employee_id' => $this->employeeId, 'date' => '2018-01-03',
            'time_in' => '2018-01-03 08:00:00', 'time_out' => '2018-01-03 16:30:00', 'status' => 'present',
            'created_at' => now(), 'updated_at' => now()]);

        // A day with no clock-out at all must cost nothing.
        DB::table('hr_attendance')->insert(['employee_id' => $this->employeeId, 'date' => '2018-01-04',
            'time_in' => '2018-01-04 08:00:00', 'time_out' => null, 'status' => 'present',
            'created_at' => now(), 'updated_at' => now()]);

        (new PayrollRun)->generate($this->employeeId, PayPeriod::fromStart('2018-01-01'));
        $payslip = DB::table('hr_payroll')->where('employee_id', $this->employeeId)->first();

        $half = round(22000 / 22 / 2, 2);
        $this->assertStringContainsString('Undertime (1 day)', $payslip->notes);
        $this->assertStringContainsString(number_format($half, 2), $payslip->notes);
        $this->assertStringNotContainsString('Late', $payslip->notes);
    }

    public function test_late_and_undertime_on_one_day_cost_one_day(): void
    {
        DB::table('employees')->where('employee_id', $this->employeeId)
            ->update(['shift_start' => '08:00:00', 'shift_end' => '17:00:00', 'rest_days' => null, 'salary' => 22000]);

        // An hour late and an hour early: half a day each.
        DB::table('hr_attendance')->insert(['employee_id' => $this->employeeId, 'date' => '2018-01-03',
            'time_in' => '2018-01-03 09:00:00', 'time_out' => '2018-01-03 16:00:00', 'status' => 'late',
            'created_at' => now(), 'updated_at' => now()]);

        (new PayrollRun)->generate($this->employeeId, PayPeriod::fromStart('2018-01-01'));
        $payslip = DB::table('hr_payroll')->where('employee_id', $this->employeeId)->first();

        $half = round(22000 / 22 / 2, 2);
        $this->assertStringContainsString('Late (1 day): PHP '.number_format($half, 2), $payslip->notes);
        $this->assertStringContainsString('Undertime (1 day): PHP '.number_format($half, 2), $payslip->notes);

        // The two together are one whole day off the gross, and no more.
        $expected = PayrollCalculator::forCutoff(22000, round(22000 / 22, 2), false);
        $this->assertEquals($expected['net'], (float) $payslip->net_pay);
    }

    public function test_nobody_is_late_on_a_rest_day_or_a_holiday(): void
    {
        DB::table('employees')->where('employee_id', $this->employeeId)
            ->update(['shift_start' => '08:00:00', 'rest_days' => '7', 'salary' => 22000]);
        DB::table('holidays')->insert(['date' => '2018-01-08', 'name' => 'Founding Day', 'type' => 'regular', 'created_at' => now(), 'updated_at' => now()]);

        foreach (['2018-01-07', '2018-01-08'] as $date) {
            DB::table('hr_attendance')->insert(['employee_id' => $this->employeeId, 'date' => $date,
                'time_in' => $date.' 10:30:00', 'status' => 'late', 'created_at' => now(), 'updated_at' => now()]);
        }

        (new PayrollRun)->generate($this->employeeId, PayPeriod::fromStart('2018-01-01'));
        $notes = DB::table('hr_payroll')->where('employee_id', $this->employeeId)->value('notes');

        // Two and a half hours late on each - but neither was a day they were due.
        $this->assertStringNotContainsString('Late', $notes);
    }

    public function test_the_checklist_screen_lists_the_people_who_need_one(): void
    {
        // Hired in 2018 and still here: settled, so not on the list by default.
        $this->actingAs($this->hr)->get('/hr/people/checklists')->assertOk()->assertDontSee($this->staff->full_name);
        $this->get('/hr/people/checklists?all=1')->assertOk()->assertSee($this->staff->full_name);

        // A new starter appears without anybody having to start anything...
        DB::table('employees')->where('employee_id', $this->employeeId)->update(['hire_date' => today()->subDays(3)->toDateString()]);
        $this->get('/hr/people/checklists')->assertOk()->assertSee($this->staff->full_name)->assertSee("No onboarding checklist");

        // ...and stays while the checklist is open, whatever their hire date.
        $this->post('/hr/people/checklists', ['employee_id' => $this->employeeId, 'type' => 'onboarding', 'due_on' => '2018-02-01'])->assertRedirect();
        DB::table('employees')->where('employee_id', $this->employeeId)->update(['hire_date' => '2018-01-01']);
        $this->get('/hr/people/checklists')->assertOk()->assertSee('Complete orientation');

        // Once it is finished they drop off again.
        foreach (DB::table('checklist_items')->where('checklist_id', DB::table('employee_checklists')->where('employee_id', $this->employeeId)->value('id'))->pluck('id') as $itemId) {
            $id = DB::table('employee_checklists')->where('employee_id', $this->employeeId)->value('id');
            $this->post('/hr/people/checklists/'.$id, ['action' => 'toggle', 'item_id' => $itemId])->assertRedirect();
        }
        $this->post('/hr/people/checklists/'.DB::table('employee_checklists')->where('employee_id', $this->employeeId)->value('id'), ['action' => 'complete'])->assertRedirect();
        $this->get('/hr/people/checklists')->assertOk()->assertDontSee($this->staff->full_name);

        // Somebody who has left needs clearing, so they come back.
        DB::table('employees')->where('employee_id', $this->employeeId)->update(['status' => 'terminated']);
        $this->get('/hr/people/checklists')->assertOk()->assertSee($this->staff->full_name)->assertSee('has left and has not been cleared');
    }

    public function test_checklists_enforce_task_owner_and_completion_gate(): void
    {
        $this->actingAs($this->hr)->post('/hr/people/checklists', ['employee_id' => $this->employeeId, 'type' => 'offboarding', 'due_on' => '2018-02-01'])->assertRedirect();
        $id = DB::table('employee_checklists')->where('employee_id', $this->employeeId)->value('id');
        $this->post('/hr/people/checklists/'.$id, ['action' => 'complete'])->assertStatus(422);
        $item = DB::table('checklist_items')->where('checklist_id', $id)->where('owner', 'hr')->first();
        $this->actingAs($this->staff)->post('/employee/people/checklists/'.$id, ['action' => 'toggle', 'item_id' => $item->id])->assertForbidden();
        $this->actingAs($this->hr)->get('/hr/people/checklists')->assertOk()->assertSee('Return company equipment');
        foreach (DB::table('checklist_items')->where('checklist_id', $id)->pluck('id') as $itemId) {
            $this->post('/hr/people/checklists/'.$id, ['action' => 'toggle', 'item_id' => $itemId])->assertRedirect();
        }
        $this->post('/hr/people/checklists/'.$id, ['action' => 'complete'])->assertRedirect();
        $this->assertNotNull(DB::table('employee_checklists')->where('id', $id)->value('completed_at'));
    }

    public function test_reviews_have_goals_self_assessment_and_locked_final_results(): void
    {
        $this->actingAs($this->hr)->post('/hr/people/reviews', ['employee_id' => $this->employeeId, 'period_start' => '2018-01-01', 'period_end' => '2018-03-31', 'due_on' => '2018-04-15'])->assertRedirect();
        $id = DB::table('performance_reviews')->where('employee_id', $this->employeeId)->value('id');
        $this->post('/hr/people/reviews/'.$id, ['action' => 'goal', 'title' => 'Finish training'])->assertRedirect();
        $goal = DB::table('performance_goals')->where('review_id', $id)->value('id');
        $this->actingAs($this->staff)->post('/employee/people/reviews/'.$id, ['action' => 'progress', 'goal_id' => $goal, 'progress' => 80])->assertRedirect();
        $this->post('/employee/people/reviews/'.$id, ['action' => 'self-assessment', 'self_assessment' => 'Completed most training modules.'])->assertRedirect();
        $this->actingAs($this->hr)->post('/hr/people/reviews/'.$id, ['action' => 'finalize', 'rating' => 4, 'feedback' => 'Strong progress, complete final module.'])->assertRedirect();
        $this->actingAs($this->staff)->get('/employee/people/reviews')->assertOk()->assertSee('4/5')->assertSee('Finish training');
        $this->post('/employee/people/reviews/'.$id, ['action' => 'progress', 'goal_id' => $goal, 'progress' => 100])->assertStatus(422);
        $this->actingAs($this->other)->post('/employee/people/reviews/'.$id, ['action' => 'progress', 'goal_id' => $goal, 'progress' => 100])->assertForbidden();
    }

    public function test_hr_cannot_raise_a_loan_on_somebodys_behalf(): void
    {
        // The form is only on the employee portal, and so is the rule: a request
        // raised by HR would lose the record of who actually asked for it.
        $this->actingAs($this->hr)->get('/hr/people/loans')->assertOk()->assertDontSee('Request a loan');
        $this->post('/hr/people/loans', ['employee_id' => $this->employeeId, 'type' => 'loan',
            'amount' => 5000, 'installment' => 500, 'starts_on' => '2018-01-01', 'reason' => 'On their behalf'])
            ->assertForbidden();
        $this->assertDatabaseCount('employee_loans', 0);
    }

    public function test_loan_and_overtime_integrate_with_payroll_exactly_once(): void
    {
        $overtime = $this->overtime();
        $this->actingAs($this->hr)->post('/hr/people/overtime/'.$overtime, ['action' => 'approve', 'approved_amount' => 300])->assertRedirect();
        $this->actingAs($this->staff)->post('/employee/people/loans', ['type' => 'cash_advance', 'amount' => 1000, 'installment' => 600, 'starts_on' => '2018-01-01', 'reason' => 'Travel expenses'])->assertRedirect();
        $loan = DB::table('employee_loans')->where('employee_id', $this->employeeId)->value('id');
        $this->actingAs($this->hr)->post('/hr/people/loans/'.$loan, ['action' => 'approve'])->assertRedirect();
        $this->post('/hr/people/loans/'.$loan, ['action' => 'disburse'])->assertRedirect();
        $service = app(PayrollRun::class);
        $payroll = $service->generate($this->employeeId, PayPeriod::fromStart('2018-01-01'));
        $this->assertDatabaseHas('hr_payroll', ['payroll_id' => $payroll, 'overtime_pay' => 300, 'loan_deduction' => 600]);
        $this->assertNull($service->generate($this->employeeId, PayPeriod::fromStart('2018-01-01')));
        $next = $service->generate($this->employeeId, PayPeriod::fromStart('2018-01-16'));
        $this->assertDatabaseHas('hr_payroll', ['payroll_id' => $next, 'overtime_pay' => 0, 'loan_deduction' => 400]);
        $this->assertNull(DB::table('loan_installments')->where('loan_id', $loan)->value('paid_at'));
        DB::table('hr_payroll')->whereIn('payroll_id', [$payroll, $next])->update(['status' => 'approved']);
        $service->markPaid('2018-01-01');
        $service->markPaid('2018-01-16');
        $this->assertDatabaseHas('employee_loans', ['id' => $loan, 'status' => 'repaid']);
        $this->assertSame(0, $service->markPaid('2018-01-01'));
        $this->get('/hr/people/loans')->assertOk()->assertSee('Repaid');
    }

}
