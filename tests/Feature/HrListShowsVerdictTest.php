<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * The interviewer's verdict was only inside the application dialog, so finding
 * out how an interview went meant opening every applicant in turn.
 */
class HrListShowsVerdictTest extends TestCase
{
    private array $made = [];
    private array $apps = [];

    protected function tearDown(): void
    {
        DB::table('job_applications')->whereIn('application_id', $this->apps)->delete();
        foreach ($this->made as $id) {
            DB::table('application_interviews')->where('interviewer_id', $id)->delete();
            DB::table('users')->where('user_id', $id)->delete();
        }
        parent::tearDown();
    }

    private function applicant(string $name): int
    {
        $n = random_int(100000, 999999);
        $u = User::create(['full_name' => $name, 'username' => "lv{$n}",
            'email' => "lv{$n}@example.test", 'password' => 'x', 'role' => 'employee']);
        $this->made[] = $u->user_id;

        $id = DB::table('job_applications')->insertGetId([
            'user_id' => $u->user_id, 'position_applied' => 'Crew', 'years_experience' => '',
            'status' => 'reviewed', 'application_date' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->apps[] = $id;

        return $id;
    }

    /**
     * The list joined only the latest round, so somebody recommended twice and
     * then turned down looked identical to somebody turned down once.
     */
    public function test_every_round_is_marked_not_just_the_last(): void
    {
        $hr = User::where('username', 'hr')->first();
        $sup = User::where('username', 'carla')->first();

        $appId = $this->applicant('Three Rounds');

        foreach ([[1, 'recommend'], [2, 'recommend'], [3, 'not_recommend']] as [$round, $verdict]) {
            DB::table('application_interviews')->insert([
                'application_id' => $appId, 'interviewer_id' => $sup->user_id, 'round' => $round,
                'scheduled_at' => now()->subDays(4 - $round), 'type' => 'in_person',
                'status' => 'completed', 'recommendation' => $verdict, 'recommended_at' => now(),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $c = Volt::actingAs($hr)->test('hr.applications');
        $rounds = $c->get('roundsByApplication')[$appId] ?? [];

        $this->assertCount(3, $rounds, 'the list still carries only one round');
        $this->assertSame('recommend', $rounds[0]->recommendation);
        $this->assertSame('not_recommend', $rounds[2]->recommendation);

        // Both outcomes visible at once, which is the point.
        $html = $c->html();
        $this->assertStringContainsString('Recommends', $html);
        $this->assertStringContainsString('Not recommended', $html);
    }

    /**
     * The note is the reason behind the verdict. It is a sentence or three, so
     * it cannot be a column - it is on the hover, and in full in the dialog.
     */
    public function test_the_note_is_reachable_from_the_list(): void
    {
        $hr = User::where('username', 'hr')->first();
        $sup = User::where('username', 'carla')->first();

        $appId = $this->applicant('Noted Person');
        $note = 'Steady on the press and asked good questions about the night shift.';

        DB::table('application_interviews')->insert([
            'application_id' => $appId, 'interviewer_id' => $sup->user_id, 'round' => 1,
            'scheduled_at' => now()->subDay(), 'type' => 'in_person', 'status' => 'completed',
            'recommendation' => 'recommend', 'recommendation_notes' => $note,
            'recommended_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $html = Volt::actingAs($hr)->test('hr.applications')->html();

        $this->assertStringContainsString($note, $html, 'the note is not reachable from the list');
        $this->assertStringContainsString('fa-comment-dots', $html, 'nothing says a note exists');

        // And in full where there is room for it.
        $dialog = Volt::actingAs($hr)->test('hr.applications')->call('viewApplication', $appId)->html();
        $this->assertStringContainsString($note, $dialog);
    }

    /**
     * A Result button on the row, because the verdicts and the reasons behind
     * them are what somebody deciding whether to hire actually wants - and
     * they were only reachable by opening the whole 201 file and scrolling.
     */
    public function test_the_result_button_opens_every_round_with_its_reasons(): void
    {
        $hr = User::where('username', 'hr')->first();
        $carla = User::where('username', 'carla')->first();
        $boying = User::where('username', 'boying')->first();

        $appId = $this->applicant('Result Person');

        DB::table('application_interviews')->insert([
            ['application_id' => $appId, 'interviewer_id' => $carla->user_id, 'round' => 1,
             'scheduled_at' => now()->subDays(3), 'type' => 'in_person', 'status' => 'completed',
             'hr_notes' => 'Ask about the night shift.',
             'recommendation' => 'recommend', 'recommendation_notes' => 'FIRST ROUND REASONING',
             'recommended_at' => now(), 'created_at' => now(), 'updated_at' => now()],
            // Same keys as the row above: a multi-row insert needs them to match.
            ['application_id' => $appId, 'interviewer_id' => $boying->user_id, 'round' => 2,
             'scheduled_at' => now()->subDay(), 'type' => 'in_person', 'status' => 'completed',
             'hr_notes' => null,
             'recommendation' => 'not_recommend', 'recommendation_notes' => 'SECOND ROUND REASONING',
             'recommended_at' => now(), 'created_at' => now(), 'updated_at' => now()],
        ]);

        $c = Volt::actingAs($hr)->test('hr.applications');

        // The button is on the row.
        $this->assertStringContainsString('openResults', $c->html());

        $c->call('openResults', $appId)->assertSet('showResultsModal', true);
        $html = $c->html();

        // Both rounds, both verdicts, and the reasoning behind each.
        $this->assertStringContainsString('Round 1', $html);
        $this->assertStringContainsString('Round 2', $html);
        $this->assertStringContainsString('Recommends', $html);
        $this->assertStringContainsString('Does not recommend', $html);
        $this->assertStringContainsString('FIRST ROUND REASONING', $html);
        $this->assertStringContainsString('SECOND ROUND REASONING', $html);

        // Attributed to whoever said it, which is the whole point of rounds.
        $this->assertStringContainsString($carla->full_name, $html);
        $this->assertStringContainsString($boying->full_name, $html);
    }

    public function test_the_result_button_is_absent_when_there_is_nothing_to_show(): void
    {
        $hr = User::where('username', 'hr')->first();
        $appId = $this->applicant('Never Interviewed');

        $rounds = Volt::actingAs($hr)->test('hr.applications')->get('roundsByApplication');
        $this->assertArrayNotHasKey($appId, $rounds, 'an application with no interviews carries rounds');
    }

    public function test_the_list_shows_the_verdict_without_opening_anything(): void
    {
        $hr = User::where('username', 'hr')->first();
        $sup = User::where('username', 'carla')->first();

        $yes = $this->applicant('Verdict Yes');
        $no = $this->applicant('Verdict No');
        $waiting = $this->applicant('Verdict Waiting');

        foreach ([[$yes, 'recommend'], [$no, 'not_recommend'], [$waiting, null]] as [$appId, $verdict]) {
            DB::table('application_interviews')->insert([
                'application_id' => $appId, 'interviewer_id' => $sup->user_id, 'round' => 1,
                'scheduled_at' => now()->subDay(), 'type' => 'in_person',
                'status' => $verdict ? 'completed' : 'scheduled',
                'recommendation' => $verdict, 'recommended_at' => $verdict ? now() : null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // The list, with nothing clicked.
        $html = Volt::actingAs($hr)->test('hr.applications')->html();

        $this->assertStringContainsString('Recommends', $html);
        $this->assertStringContainsString('Not recommended', $html);
        // A booked but unanswered round is marked as outstanding rather than
        // left looking like a verdict that was never given.
        $this->assertStringContainsString('Not yet given', $html);
    }
}
