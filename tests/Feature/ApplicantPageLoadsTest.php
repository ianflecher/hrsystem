<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The applicant's own page, and what it must not contain.
 *
 * interviewDetails is a public Livewire property, so whatever it holds is
 * serialised into the page and readable in view-source. Selecting ai.* put
 * HR's brief to the interviewer, the recommendation made about this person and
 * the notes behind it straight into the candidate's browser.
 */
class ApplicantPageLoadsTest extends TestCase
{
    public function test_the_portal_loads_for_somebody_with_an_interview(): void
    {
        $u = User::where('username', 'test.admin')->first();
        if (! $u) { $this->markTestSkipped('no applicant'); }

        $rounds = DB::table('application_interviews as ai')
            ->join('job_applications as ja', 'ai.application_id', '=', 'ja.application_id')
            ->where('ja.user_id', $u->user_id)->count();

        // It used to 500 here, on a column that moved to application_interviews.
        $html = $this->actingAs($u)->get('/applicant')->assertOk()->getContent();

        fwrite(STDERR, "\n  interviews on their application : {$rounds}");
        fwrite(STDERR, "\n  page loads                      : yes");
        fwrite(STDERR, "\n  schedule shown                  : ".(str_contains($html, 'Interview') ? 'yes' : 'no'));

        $briefs = DB::table('application_interviews as ai')
            ->join('job_applications as ja', 'ai.application_id', '=', 'ja.application_id')
            ->where('ja.user_id', $u->user_id)->whereNotNull('ai.hr_notes')->pluck('ai.hr_notes');

        foreach ($briefs as $brief) {
            $this->assertStringNotContainsString($brief, $html,
                "HR's brief to the interviewer is on the candidate's page");
        }

        $this->assertStringNotContainsString('recommendation_notes', $html,
            'the interviewer notes are in the page source');
        $this->assertStringNotContainsString('hr_notes', $html,
            "HR's brief is in the page source");

        foreach (['recommend', 'not_recommend', 'undecided'] as $verdict) {
            $this->assertStringNotContainsString('"recommendation":"' . $verdict . '"', $html,
                'the candidate can read the verdict made about them');
        }

        fwrite(STDERR, "\n  verdict absent from the source  : confirmed\n\n");
    }
}
