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

    /** @return array{application: int, interview: int} */
    private function applicationFor(User $interviewer, string $when = '+2 days'): array
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
            'application_date' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->madeApps[] = $id;

        $interviewId = DB::table('application_interviews')->insertGetId([
            'application_id' => $id, 'interviewer_id' => $interviewer->user_id,
            'round' => 1, 'scheduled_at' => now()->modify($when), 'type' => 'in_person',
            'status' => 'scheduled', 'hr_notes' => 'Ask about the night shift.',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return ['application' => $id, 'interview' => $interviewId];
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
        ['application' => $id, 'interview' => $interviewId] = $this->applicationFor($sup, '-1 day');

        Volt::actingAs($sup)->test('employee.interviews')
            ->call('open', $interviewId)
            ->set('recommendation', 'recommend')
            ->set('notes', 'Strong on the press, keen to start.')
            ->call('record')
            ->assertHasNoErrors();

        $row = DB::table('application_interviews')->where('application_id', $id)->first();
        $this->assertSame('recommend', $row->recommendation);
        $this->assertSame('Strong on the press, keen to start.', $row->recommendation_notes);
        $this->assertNotNull($row->recommended_at);
        $this->assertSame('completed', $row->status, 'a verdict means the interview happened');

        // And HR reads it back on the application.
        $hr = User::where('username', 'hr')->first();
        if ($hr) {
            Volt::actingAs($hr)->test('hr.applications')
                ->call('viewApplication', $id)
                ->assertSee('Recommends')
                ->assertSee('Strong on the press, keen to start.');
        }
    }

    /**
     * The whole point of the table. Two rounds used to be one row: the second
     * overwrote the first's date and notes, the first interviewer's verdict
     * stayed put while interviewer_id moved on - so their words appeared under
     * the second interviewer's name - and the first lost the interview from
     * their own list.
     */
    public function test_a_second_round_leaves_the_first_alone(): void
    {
        $hr = User::where('username', 'hr')->first();
        if (! $hr) { $this->markTestSkipped('no hr account'); }

        $first = $this->supervisor('r1');
        $second = $this->supervisor('r2');
        ['application' => $appId, 'interview' => $round1] = $this->applicationFor($first, '-1 day');

        // Round one happens and the first supervisor gives their verdict.
        Volt::actingAs($first)->test('employee.interviews')
            ->call('open', $round1)
            ->set('recommendation', 'recommend')
            ->set('notes', 'FIRST SUPERVISOR WORDS')
            ->call('record')
            ->assertHasNoErrors();

        // HR books a second round with somebody else.
        Volt::actingAs($hr)->test('hr.applications')
            ->call('viewApplication', $appId)
            ->call('openInterviewModal', $appId)
            ->set('interviewDate', now()->addDays(3)->format('Y-m-d'))
            ->set('interviewTime', '14:00')
            ->set('interviewerId', $second->user_id)
            ->set('interviewType', 'in_person')
            ->set('interviewNotes', 'SECOND ROUND BRIEF')
            ->call('saveInterviewSchedule')
            ->assertHasNoErrors();

        $rounds = DB::table('application_interviews')->where('application_id', $appId)
            ->orderBy('round')->get();

        $this->assertCount(2, $rounds, 'the second round replaced the first instead of joining it');

        // Round one is untouched, and still belongs to the first supervisor.
        $this->assertSame(1, (int) $rounds[0]->round);
        $this->assertSame((int) $first->user_id, (int) $rounds[0]->interviewer_id);
        $this->assertSame('recommend', $rounds[0]->recommendation);
        $this->assertSame('FIRST SUPERVISOR WORDS', $rounds[0]->recommendation_notes);
        $this->assertSame('Ask about the night shift.', $rounds[0]->hr_notes, 'round one brief was overwritten');

        // Round two is the second supervisor's, and carries no verdict yet.
        $this->assertSame(2, (int) $rounds[1]->round);
        $this->assertSame((int) $second->user_id, (int) $rounds[1]->interviewer_id);
        $this->assertNull($rounds[1]->recommendation, "round two is wearing round one's verdict");
        $this->assertSame('SECOND ROUND BRIEF', $rounds[1]->hr_notes);

        // Each supervisor still sees their own, and only their own.
        $firstSees = Volt::actingAs($first)->test('employee.interviews')->html();
        $this->assertStringContainsString('Candidate Person', $firstSees, 'the first lost it from their list');
        $this->assertStringContainsString('FIRST SUPERVISOR WORDS', $firstSees);

        $secondSees = Volt::actingAs($second)->test('employee.interviews')->html();
        $this->assertStringContainsString('Candidate Person', $secondSees);
        $this->assertStringNotContainsString('FIRST SUPERVISOR WORDS', $secondSees,
            "the second can read the first's notes");

        // And HR sees both, each under the right name.
        $hrSees = Volt::actingAs($hr)->test('hr.applications')->call('viewApplication', $appId)->html();
        $this->assertStringContainsString('Round 2', $hrSees);
        $this->assertStringContainsString('FIRST SUPERVISOR WORDS', $hrSees);
        $this->assertStringContainsString($first->full_name, $hrSees);
        $this->assertStringContainsString($second->full_name, $hrSees);
    }

    public function test_a_verdict_is_required(): void
    {
        $sup = $this->supervisor('e');
        ['interview' => $interviewId] = $this->applicationFor($sup);

        Volt::actingAs($sup)->test('employee.interviews')
            ->call('open', $interviewId)
            ->set('recommendation', '')
            ->call('record')
            ->assertHasErrors('recommendation');
    }

    /** Changing the id in the browser must not open somebody else's candidate. */
    public function test_opening_an_interview_that_is_not_yours_is_refused(): void
    {
        $mine = $this->supervisor('f');
        $theirs = $this->supervisor('g');
        ['interview' => $interviewId] = $this->applicationFor($theirs);

        Volt::actingAs($mine)->test('employee.interviews')
            ->call('open', $interviewId)
            ->assertForbidden();
    }

    /** Health and criminal history stay with HR. */
    public function test_the_disclosures_are_not_shown_to_the_interviewer(): void
    {
        $sup = $this->supervisor('h');
        ['application' => $id, 'interview' => $interviewId] = $this->applicationFor($sup);

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

        $html = Volt::actingAs($sup)->test('employee.interviews')->call('open', $interviewId)->html();

        $this->assertStringNotContainsString('CONFIDENTIAL-HEALTH-MARKER', $html);
        $this->assertStringNotContainsString('CONFIDENTIAL-LEGAL-MARKER', $html);
        $this->assertStringNotContainsString('Applicant disclosures', $html);
        // But the part the interview is actually about is there.
        $this->assertStringContainsString('Employment record', $html);

        DB::table('applicant_disclosures')->where('user_id', $applicant)->delete();
        DB::table('applicant_profiles')->where('user_id', $applicant)->delete();
    }
}
