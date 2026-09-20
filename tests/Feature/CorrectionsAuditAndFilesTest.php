<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Auditor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Three things that were dead ends: a dispute nobody could see, a change
 * nobody could trace, and a file nobody could open.
 */
class CorrectionsAuditAndFilesTest extends TestCase
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

        $this->payrollId = DB::table('hr_payroll')->insertGetId([
            'employee_id' => $this->employeeId, 'period_start' => '2022-03-01', 'period_end' => '2022-03-15',
            'gross_pay' => 11000, 'deductions' => 1000, 'net_pay' => 10000, 'status' => 'paid',
            'kind' => 'regular', 'notes' => 'Absent (1 day): PHP 1,000.00',
            'created_at' => now(), 'updated_at' => now()]);
    }

    private function person(): User
    {
        $token = bin2hex(random_bytes(5));

        $user = User::create(['full_name' => 'Case '.$token, 'username' => 'case'.$token,
            'email' => $token.'@example.test', 'password' => 'Password123!', 'role' => 'employee']);

        DB::table('employees')->insert(['user_id' => $user->user_id, 'job_title' => 'Subject',
            'hire_date' => '2020-01-01', 'salary' => 22000, 'status' => 'active',
            'created_at' => now(), 'updated_at' => now()]);

        return $user;
    }

    // ------------------------------------------------------------ corrections

    public function test_a_dispute_carries_what_is_actually_wrong(): void
    {
        Volt::actingAs($this->staff)->test('employee.payroll')
            ->call('openCorrection', $this->payrollId)
            ->set('correctionDescription', 'I was here on 8 March but it is counted as absent.')
            ->call('requestCorrection')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('payroll_corrections', [
            'payroll_id' => $this->payrollId,
            'status'     => 'pending',
            'description'=> 'I was here on 8 March but it is counted as absent.',
        ]);
    }

    public function test_an_empty_dispute_is_refused(): void
    {
        Volt::actingAs($this->staff)->test('employee.payroll')
            ->call('openCorrection', $this->payrollId)
            ->set('correctionDescription', 'nope')
            ->call('requestCorrection')
            ->assertHasErrors('correctionDescription');
    }

    public function test_hr_sees_the_dispute_and_can_answer_it(): void
    {
        $id = $this->raise();

        Volt::actingAs($this->hr)->test('hr.payroll')
            ->assertSee('Correction requests')
            ->assertSee('counted as absent')
            ->call('answerCorrection', $id)
            ->set('resolutionNotes', 'Attendance corrected and the payslip reissued.')
            ->call('closeCorrection', 'approved')
            ->assertHasNoErrors();

        $row = DB::table('payroll_corrections')->where('correction_id', $id)->first();

        $this->assertSame('approved', $row->status);
        $this->assertSame($this->hr->user_id, $row->resolved_by);
        $this->assertNotNull($row->resolved_at);
    }

    public function test_an_answer_without_a_reason_is_refused(): void
    {
        $id = $this->raise();

        Volt::actingAs($this->hr)->test('hr.payroll')
            ->call('answerCorrection', $id)
            ->set('resolutionNotes', '')
            ->call('closeCorrection', 'rejected')
            ->assertHasErrors('resolutionNotes');

        $this->assertSame('pending', DB::table('payroll_corrections')->where('correction_id', $id)->value('status'));
    }

    public function test_the_employee_sees_the_reply(): void
    {
        $id = $this->raise();

        DB::table('payroll_corrections')->where('correction_id', $id)->update([
            'status' => 'approved', 'resolution_notes' => 'Corrected, you will see it next cutoff.']);

        Volt::actingAs($this->staff)->test('employee.payroll')
            ->assertSee('Corrected, you will see it next cutoff.');
    }

    private function raise(): int
    {
        return DB::table('payroll_corrections')->insertGetId([
            'payroll_id' => $this->payrollId, 'employee_id' => $this->employeeId,
            'requested_by' => $this->staff->user_id, 'status' => 'pending',
            'description' => 'I was here on 8 March but it is counted as absent.',
            'created_at' => now(), 'updated_at' => now()]);
    }

    // ----------------------------------------------------------------- audit

    public function test_an_attendance_edit_records_who_changed_it(): void
    {
        $attendanceId = DB::table('hr_attendance')->insertGetId([
            'employee_id' => $this->employeeId, 'date' => '2022-03-08',
            'time_in' => '2022-03-08 08:00:00', 'status' => 'present',
            'created_at' => now(), 'updated_at' => now()]);

        Volt::actingAs($this->hr)->test('hr.attendance')
            ->call('editAttendance', $attendanceId, 'absent', null, null, 'Marked absent by HR');

        $entry = Auditor::history('hr_attendance', $attendanceId)->first();

        $this->assertNotNull($entry, 'a change to somebody hours has to be answerable later');
        $this->assertSame($this->hr->user_id, $entry->user_id);
        $this->assertStringContainsString('present', (string) $entry->old_values);
        $this->assertStringContainsString('absent', (string) $entry->new_values);
    }

    public function test_approving_payroll_is_recorded(): void
    {
        DB::table('hr_payroll')->where('payroll_id', $this->payrollId)->update(['status' => 'calculated']);

        // Approval now goes through the payroll control centre, which refuses
        // while the period has exceptions - and a fortnight with nobody
        // clocked in is one of them. Both people in this fixture turn up every
        // day so the period is clean and the approval can actually happen.
        foreach ([$this->staff, $this->other] as $person) {
            $employeeId = (int) DB::table('employees')->where('user_id', $person->user_id)->value('employee_id');
            for ($day = \Carbon\Carbon::parse('2022-03-01'); $day->lte(\Carbon\Carbon::parse('2022-03-15')); $day->addDay()) {
                DB::table('hr_attendance')->insert([
                    'employee_id' => $employeeId, 'date' => $day->toDateString(),
                    'time_in' => $day->toDateString().' 08:00:00',
                    'time_out' => $day->toDateString().' 17:00:00',
                    'status' => 'present', 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        Volt::actingAs($this->hr)->test('hr.payroll')
            ->set('payPeriod', '2022-03-01')
            ->call('approvePeriod');

        $entry = Auditor::history('hr_payroll', $this->payrollId)->first();

        $this->assertNotNull($entry);
        $this->assertStringContainsString('approved', (string) $entry->new_values);
    }

    public function test_a_failed_audit_write_never_breaks_the_change_itself(): void
    {
        // A table name far past the column width would throw on insert.
        Auditor::record('update', str_repeat('x', 500), 1, null, ['a' => 'b']);

        $this->assertTrue(true, 'losing the record of a change is bad; losing the change is worse');
    }

    // ----------------------------------------------------------------- files

    public function test_an_application_document_downloads_and_is_not_public_to_everybody(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('application_documents/1/id.pdf', '%PDF-1.4 fake');

        $applicationId = DB::table('job_applications')->insertGetId(['user_id' => $this->staff->user_id,
            'position_applied' => 'Operator', 'years_experience' => 1, 'status' => 'pending',
            'application_date' => today()->toDateString(), 'created_at' => now(), 'updated_at' => now()]);

        $documentId = DB::table('application_documents')->insertGetId([
            'application_id' => $applicationId, 'user_id' => $this->staff->user_id,
            'filename' => 'id.pdf', 'filepath' => 'application_documents/1/id.pdf',
            'filetype' => 'application/pdf', 'filesize' => 13, 'uploaded_at' => now(),
            'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($this->staff)->get('/applications/documents/'.$documentId)->assertOk()->assertDownload('id.pdf');
        $this->actingAs($this->hr)->get('/applications/documents/'.$documentId)->assertOk();
        $this->actingAs($this->other)->get('/applications/documents/'.$documentId)->assertForbidden();
    }

    public function test_a_resume_downloads_with_the_type_read_from_the_file(): void
    {
        $applicationId = DB::table('job_applications')->insertGetId(['user_id' => $this->staff->user_id,
            'position_applied' => 'Operator', 'years_experience' => 1, 'status' => 'pending',
            'resume_data' => json_encode(['filename' => 'cv.pdf', 'data' => base64_encode('%PDF-1.4 a real looking pdf')]),
            'application_date' => today()->toDateString(), 'created_at' => now(), 'updated_at' => now()]);

        $response = $this->actingAs($this->hr)->get('/applications/'.$applicationId.'/resume');

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));

        $this->actingAs($this->other)->get('/applications/'.$applicationId.'/resume')->assertForbidden();
    }

    public function test_a_missing_file_is_a_404_rather_than_an_error(): void
    {
        Storage::fake('public');

        $documentId = DB::table('application_documents')->insertGetId([
            'application_id' => DB::table('job_applications')->insertGetId(['user_id' => $this->staff->user_id,
                'position_applied' => 'Operator', 'years_experience' => 1, 'status' => 'pending',
                'application_date' => today()->toDateString(), 'created_at' => now(), 'updated_at' => now()]),
            'user_id' => $this->staff->user_id, 'filename' => 'gone.pdf',
            'filepath' => 'application_documents/1/gone.pdf', 'filetype' => 'application/pdf',
            'filesize' => 1, 'uploaded_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($this->hr)->get('/applications/documents/'.$documentId)->assertNotFound();
    }
}
