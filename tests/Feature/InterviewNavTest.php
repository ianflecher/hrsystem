<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\NavBadges;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * "Interviews to conduct" is the interviewer's screen - the candidates
 * somebody has been asked to meet.
 *
 * It sat in the sidebar for everybody, labelled "My interviews", so a sewer
 * with no interviews to give got a menu item that reads as though it is about
 * their own, opens on an empty page, and never changes.
 */
class InterviewNavTest extends TestCase
{
    private array $made = [];
    private array $apps = [];

    protected function setUp(): void
    {
        parent::setUp();
        NavBadges::forget();
    }

    protected function tearDown(): void
    {
        DB::table('application_interviews')->whereIn('application_id', $this->apps)->delete();
        DB::table('job_applications')->whereIn('application_id', $this->apps)->delete();

        foreach ($this->made as $id) {
            DB::table('employees')->where('user_id', $id)->delete();
            DB::table('users')->where('user_id', $id)->delete();
        }

        NavBadges::forget();
        parent::tearDown();
    }

    private function staff(string $name, string $role = 'employee'): User
    {
        $n = random_int(100000, 999999);

        $u = User::create([
            'full_name' => $name, 'username' => "nv{$n}",
            'email' => "nv{$n}@example.test", 'password' => 'x', 'role' => $role,
        ]);
        $this->made[] = $u->user_id;

        DB::table('employees')->insert([
            'user_id' => $u->user_id, 'job_title' => 'Sewer', 'hire_date' => '2026-01-01',
            'salary' => 15000, 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $u;
    }

    private function giveAnInterviewTo(User $interviewer, string $status = 'scheduled'): void
    {
        $candidate = $this->staff('Some Candidate');

        $appId = DB::table('job_applications')->insertGetId([
            'user_id' => $candidate->user_id, 'position_applied' => 'Sewer',
            'years_experience' => '', 'status' => 'reviewed',
            'application_date' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->apps[] = $appId;

        DB::table('application_interviews')->insert([
            'application_id' => $appId, 'interviewer_id' => $interviewer->user_id,
            'round' => 1, 'scheduled_at' => now()->addDay(), 'type' => 'in_person',
            'status' => $status, 'created_at' => now(), 'updated_at' => now(),
        ]);

        NavBadges::forget();
    }

    public function test_somebody_with_no_interviews_does_not_see_the_link(): void
    {
        $sewer = $this->staff('Ordinary Sewer');

        $this->assertFalse(NavBadges::conductsInterviews());

        $html = $this->actingAs($sewer)->get('/employee/dashboard')->assertOk()->getContent();

        $this->assertStringNotContainsString('Interviews to conduct', $html,
            'an employee with no interviews was offered the interviewer screen');
        $this->assertStringNotContainsString('My interviews', $html);
    }

    public function test_somebody_given_an_interview_does_see_it(): void
    {
        $supervisor = $this->staff('Interviewing Supervisor', 'supervisor');
        $this->giveAnInterviewTo($supervisor);

        $this->actingAs($supervisor);
        $this->assertTrue(NavBadges::conductsInterviews());

        $html = $this->actingAs($supervisor)->get('/employee/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('Interviews to conduct', $html,
            'an assigned interviewer cannot reach their interviews');
    }

    /**
     * It stays once the interview is done. An interviewer still needs to look
     * back at what they recommended and why.
     */
    public function test_it_stays_after_the_interview_has_been_answered(): void
    {
        $supervisor = $this->staff('Past Interviewer', 'supervisor');
        $this->giveAnInterviewTo($supervisor, 'completed');

        $this->actingAs($supervisor);
        $this->assertTrue(NavBadges::conductsInterviews(),
            'a finished interview should still be reachable');
    }

    /** A cancelled one is not an interview anybody has to conduct. */
    public function test_a_cancelled_interview_does_not_bring_the_link_back(): void
    {
        $supervisor = $this->staff('Cancelled Interviewer', 'supervisor');
        $this->giveAnInterviewTo($supervisor, 'cancelled');

        $this->actingAs($supervisor);
        $this->assertFalse(NavBadges::conductsInterviews());
    }

    /** Hiding the link is tidiness; the screen guards itself regardless. */
    public function test_the_screen_shows_nothing_of_anybody_elses(): void
    {
        $supervisor = $this->staff('Real Interviewer', 'supervisor');
        $this->giveAnInterviewTo($supervisor);

        $nosy = $this->staff('Nosy Sewer');

        $html = $this->actingAs($nosy)->get('/employee/interviews')->getContent();

        $this->assertStringNotContainsString('Some Candidate', $html,
            'somebody else\'s candidate was visible');
    }
}
