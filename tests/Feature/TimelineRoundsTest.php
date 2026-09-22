<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The timeline showed one interview however many there had been, because the
 * page only ever loaded one.
 */
class TimelineRoundsTest extends TestCase
{
    public function test_every_round_appears_on_the_timeline(): void
    {
        $u = User::where('username', 'test.admin')->first();
        if (! $u) { $this->markTestSkipped('no applicant'); }

        $rounds = DB::table('application_interviews as ai')
            ->join('job_applications as ja', 'ai.application_id', '=', 'ja.application_id')
            ->leftJoin('users as i', 'ai.interviewer_id', '=', 'i.user_id')
            ->where('ja.user_id', $u->user_id)
            ->where('ai.status', '!=', 'cancelled')
            ->orderBy('ai.round')
            ->get(['ai.round', 'i.full_name']);

        if ($rounds->count() < 2) { $this->markTestSkipped('needs more than one round'); }

        $html = $this->actingAs($u)->get('/applicant')->assertOk()->getContent();

        fwrite(STDERR, "\n  rounds on the application : {$rounds->count()}");

        foreach ($rounds as $r) {
            $this->assertStringContainsString('Interview ' . $r->round, $html,
                "round {$r->round} is missing from the timeline");
            fwrite(STDERR, "\n    Interview {$r->round} with " . ($r->full_name ?? '-') . ' : shown');
        }

        // And still nothing of the verdict, which the candidate may not see.
        $this->assertStringNotContainsString('recommendation', $html);
        $this->assertStringNotContainsString('hr_notes', $html);
        fwrite(STDERR, "\n  verdict still hidden      : confirmed\n\n");
    }
}
