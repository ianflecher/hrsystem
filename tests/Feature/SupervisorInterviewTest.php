<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * The loop: HR names an interviewer, the interviewer sees it, records a
 * recommendation, and HR reads it back.
 */
class SupervisorInterviewTest extends TestCase
{
    private array $madeUsers = [];
    private array $madeApps = [];

    protected function tearDown(): void
    {
        DB::table('job_applications')->whereIn('application_id', $this->madeApps)->delete();
        foreach ($this->madeUsers as $id) {
            DB::table('employees')->where('user_id', $id)->delete();
            DB::table('users')->where('user_id', $id)->delete();
        }
        parent::tearDown();
    }

    private function supervisor(string $tag): User
    {
        $n = random_int(100000, 999999);
        $u = User::create([
            'full_name' => "Sup {$tag}", 'username' => "sup{$tag}{$n}",
            'email' => "sup{$tag}{$n}@example.test", 'password' => 'Password!2345', 'role' => 'supervisor',
        ]);
        $this->madeUsers[] = $u->user_id;

        return $u;
    }

    private function applicationFor(User $interviewer, string $when = '+2 days'): int
    {
        $n = random_int(100000, 999999);
        $applicant = User::create([
            'full_name' => 'Candidate Person', 'username' => "cand{$n}",
            'email' => "cand{$n}@example.test", 'password' => 'Password!2345', 'role' => 'employee',
        ]);
        $this->madeUsers[] = $applicant->user_id;

        $id = DB::table('job_applications')->insertGetId([
            'user_id' => $applicant->user_id, 'position_applied' => 'Crew Member',
            'years_experience' => '', 'status' => 'reviewed',
            'interview_date' => now()->modify($when), 'interview_type' => 'in_person',
            'interview_status' => 'scheduled', 'interviewer_id' => $interviewer->user_id,
            'interview_notes' => 'Ask about the night shift.',
            'application_date' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->madeApps[] = $id;

        return $id;
    }

    public function test_a_supervisor_sees_the_interview_assigned_to_them(): void
    {
        $sup = $this->supervisor('a');
        $this->applicationFor($sup);

        Volt::actingAs($sup)->test('employee.interviews')
            ->assertSee('Candidate Person')
            ->assertSee('Crew Member')
            ->assertSee('Ask about the night shift.');
    }

    /** Scoped to the person it was given to, not to supervisors in general. */
    public function test_a_supervisor_does_not_see_somebody_elses(): void
    {
        $mine = $this->supervisor('b');
        $theirs = $this->supervisor('c');
        $this->applicationFor($theirs);

        Volt::actingAs($mine)->test('employee.interviews')
            ->assertDontSee('Candidate Person')
            ->assertSee('No interviews assigned to you');
    }

    public function test_recording_a_recommendation_reaches_hr(): void
    {
        $sup = $this->supervisor('d');
        $id = $this->applicationFor($sup, '-1 day');

        Volt::actingAs($sup)->test('employee.interviews')
            ->call('open', $id)
            ->set('recommendation', 'recommend')
            ->set('notes', 'Strong on the press, keen to start.')
            ->call('record')
            ->assertHasNoErrors();

        $row = DB::table('job_applications')->where('application_id', $id)->first();
        $this->assertSame('recommend', $row->interviewer_recommendation);
        $this->assertSame('Strong on the press, keen to start.', $row->recommendation_notes);
        $this->assertNotNull($row->recommended_at);
        $this->assertSame('completed', $row->interview_status, 'a verdict means the interview happened');

        // And HR reads it back on the application.
        $hr = User::where('username', 'hr')->first();
        if ($hr) {
            Volt::actingAs($hr)->test('hr.applications')
                ->call('viewApplication', $id)
                ->assertSee('Recommends')
                ->assertSee('Strong on the press, keen to start.');
        }
    }

    public function test_a_verdict_is_required(): void
    {
        $sup = $this->supervisor('e');
        $id = $this->applicationFor($sup);

        Volt::actingAs($sup)->test('employee.interviews')
            ->call('open', $id)
            ->set('recommendation', '')
            ->call('record')
            ->assertHasErrors('recommendation');
    }

    /** Changing the id in the browser must not open somebody else's candidate. */
    public function test_opening_an_interview_that_is_not_yours_is_refused(): void
    {
        $mine = $this->supervisor('f');
        $theirs = $this->supervisor('g');
        $id = $this->applicationFor($theirs);

        Volt::actingAs($mine)->test('employee.interviews')
            ->call('open', $id)
            ->assertForbidden();
    }

    /** Health and criminal history stay with HR. */
    public function test_the_disclosures_are_not_shown_to_the_interviewer(): void
    {
        $sup = $this->supervisor('h');
        $id = $this->applicationFor($sup);

        $applicant = DB::table('job_applications')->where('application_id', $id)->value('user_id');
        DB::table('applicant_profiles')->insert([
            'user_id' => $applicant, 'surname' => 'Candidate', 'first_name' => 'Person',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('applicant_disclosures')->insert([
            'user_id' => $applicant, 'has_medical_condition' => 1,
            'medical_condition_details' => 'CONFIDENTIAL-HEALTH-MARKER',
            'ever_convicted' => 1, 'ever_convicted_details' => 'CONFIDENTIAL-LEGAL-MARKER',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $html = Volt::actingAs($sup)->test('employee.interviews')->call('open', $id)->html();

        $this->assertStringNotContainsString('CONFIDENTIAL-HEALTH-MARKER', $html);
        $this->assertStringNotContainsString('CONFIDENTIAL-LEGAL-MARKER', $html);
        $this->assertStringNotContainsString('Applicant disclosures', $html);
        // But the part the interview is actually about is there.
        $this->assertStringContainsString('Employment record', $html);

        DB::table('applicant_disclosures')->where('user_id', $applicant)->delete();
        DB::table('applicant_profiles')->where('user_id', $applicant)->delete();
    }
}
