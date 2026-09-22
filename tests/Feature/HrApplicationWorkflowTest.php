<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * The HR half of the careers loop: an application filed on the applicant portal
 * is reviewed, scheduled for interview, and acted on from the HR back office.
 */
class HrApplicationWorkflowTest extends TestCase
{
    private array $createdUserIds = [];

    protected function tearDown(): void
    {
        foreach ($this->createdUserIds as $id) {
            // Interviews cascade with the application, but one where this
            // person was the interviewer hangs off somebody else's.
            DB::table('application_interviews')->where('interviewer_id', $id)->delete();
            DB::table('job_offers')->whereIn('application_id',
                DB::table('job_applications')->where('user_id', $id)->pluck('application_id'))->delete();
            DB::table('application_documents')->where('user_id', $id)->delete();
            DB::table('job_applications')->where('user_id', $id)->delete();
            DB::table('employees')->where('user_id', $id)->delete();
            DB::table('users')->where('user_id', $id)->delete();
        }

        parent::tearDown();
    }

    public function test_hr_sees_a_filed_application_and_can_move_it_through_the_pipeline(): void
    {
        [$hr, $applicant, $applicationId] = $this->scenario();

        $component = Volt::actingAs($hr)->test('hr.applications');
        $component->assertSee('Crew Member');

        // Mark reviewed.
        $component->call('updateApplicationStatus', $applicationId, 'reviewed');
        $this->assertSame('reviewed', DB::table('job_applications')->where('application_id', $applicationId)->value('status'));

        // Schedule an interview.
        $component->call('openInterviewModal', $applicationId)
            ->set('interviewDate', now()->addDays(3)->format('Y-m-d'))
            ->set('interviewTime', '14:30')
            ->set('interviewerId', $hr->user_id)
            ->set('interviewType', 'in_person')
            ->call('saveInterviewSchedule')
            ->assertHasNoErrors();

        // The interview is its own row now, so a second round can be added
        // later without writing over this one.
        $row = DB::table('application_interviews')->where('application_id', $applicationId)->first();
        $this->assertNotNull($row, 'scheduling should create an interview');
        $this->assertSame('scheduled', $row->status);
        $this->assertSame(1, (int) $row->round);
        $this->assertSame((int) $hr->user_id, (int) $row->interviewer_id);
        $this->assertStringContainsString('14:30', $row->scheduled_at);
    }

    /**
     * Hiring is the candidate's move, not HR's: an offer is sent, and accepting
     * it is what promotes them. HR marking the status by hand is refused,
     * because nobody should be hired on terms they were never shown.
     */
    public function test_accepting_an_offer_promotes_the_applicant_to_employee(): void
    {
        [$hr, $applicant, $applicationId] = $this->scenario();

        // By hand, with no offer on the table: refused.
        Volt::actingAs($hr)->test('hr.applications')
            ->call('updateApplicationStatus', $applicationId, 'hired');

        $this->assertNotSame('hired',
            DB::table('job_applications')->where('application_id', $applicationId)->value('status'),
            'hired without an offer anybody had accepted');

        // Through an offer, which they then accept.
        Volt::actingAs($hr)->test('hr.applications')
            ->call('openHireModal', $applicationId)
            ->set('hireSalary', '19000')
            ->set('hireResponsibilities', 'Run the press and check every batch before it leaves.')
            ->set('hireStartsOn', now()->addWeek()->toDateString())
            ->call('confirmHire')
            ->assertHasNoErrors();

        Volt::actingAs($applicant)->test('applicant.index')->call('acceptOffer')->assertHasNoErrors();

        $this->assertSame('hired',
            DB::table('job_applications')->where('application_id', $applicationId)->value('status'));

        $this->assertSame('employee',
            DB::table('users')->where('user_id', $applicant->user_id)->value('role'));

        $this->assertEquals(19000,
            DB::table('employees')->where('user_id', $applicant->user_id)->value('salary'));
    }

    public function test_a_role_outside_the_allowed_set_is_rejected(): void
    {
        [$hr, $applicant] = $this->scenario();

        $component = Volt::actingAs($hr)->test('hr.applications');
        $component->call('openRoleChangeModal', $applicant->user_id)
            ->set('newRole', 'superuser')
            ->call('changeUserRole')
            ->assertHasErrors('newRole');

        $this->assertSame(
            'employee',
            DB::table('users')->where('user_id', $applicant->user_id)->value('role'),
            'an unknown role must not reach the column'
        );
    }

    public function test_hr_can_promote_someone_to_supervisor(): void
    {
        [$hr, $applicant] = $this->scenario();

        Volt::actingAs($hr)
            ->test('hr.applications')
            ->call('openRoleChangeModal', $applicant->user_id)
            ->set('newRole', 'supervisor')
            ->call('changeUserRole')
            ->assertHasNoErrors();

        $this->assertSame(
            'supervisor',
            DB::table('users')->where('user_id', $applicant->user_id)->value('role')
        );
    }

    /** @return array{0: User, 1: User, 2: int} */
    private function scenario(): array
    {
        $hr = User::where('username', 'hr')->first();

        if (! $hr) {
            $this->markTestSkipped('Seeded HR user not found; run `php artisan db:seed`.');
        }

        $n = random_int(100000, 999999);

        $applicant = User::create([
            'full_name' => 'Pipeline Applicant',
            'username'  => "pipeline{$n}",
            'email'     => "pipeline{$n}@example.test",
            'password'  => 'Password!2345',
            'role'      => 'employee',
        ]);
        $this->createdUserIds[] = $applicant->user_id;

        $applicationId = DB::table('job_applications')->insertGetId([
            'user_id'          => $applicant->user_id,
            'position_applied' => 'Crew Member',
            'years_experience' => '2',
            'status'           => 'pending',
            'application_date' => now(),
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        return [$hr, $applicant, $applicationId];
    }
}
