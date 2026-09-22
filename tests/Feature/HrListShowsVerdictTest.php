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
        $this->assertStringContainsString('Waiting on the interviewer', $html,
            'a booked but unanswered interview should say so rather than look like no verdict');
    }
}
