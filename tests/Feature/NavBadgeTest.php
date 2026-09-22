<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\NavBadges;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * A badge is a claim that something is waiting. It must appear when that is
 * true and go away when it stops being true, or it teaches people to ignore it.
 */
class NavBadgeTest extends TestCase
{
    private array $made = [];
    private array $apps = [];

    protected function tearDown(): void
    {
        DB::table('job_applications')->whereIn('application_id', $this->apps)->delete();
        foreach ($this->made as $id) {
            DB::table('application_interviews')->where('interviewer_id', $id)->delete();
            DB::table('employees')->where('user_id', $id)->delete();
            DB::table('users')->where('user_id', $id)->delete();
        }
        NavBadges::forget();
        parent::tearDown();
    }

    public function test_an_interviewer_is_badged_only_while_something_is_outstanding(): void
    {
        $n = random_int(100000, 999999);
        $sup = User::create(['full_name' => 'Badge Sup', 'username' => "bsup{$n}",
            'email' => "bsup{$n}@example.test", 'password' => 'x', 'role' => 'supervisor']);
        $cand = User::create(['full_name' => 'Badge Cand', 'username' => "bcand{$n}",
            'email' => "bcand{$n}@example.test", 'password' => 'x', 'role' => 'employee']);
        $this->made = [$sup->user_id, $cand->user_id];

        $this->actingAs($sup);
        NavBadges::forget();
        $this->assertSame(0, NavBadges::staff()['employee.interviews'], 'badged with nothing waiting');

        $appId = DB::table('job_applications')->insertGetId([
            'user_id' => $cand->user_id, 'position_applied' => 'Crew', 'years_experience' => '',
            'status' => 'reviewed', 'application_date' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->apps[] = $appId;

        $ivId = DB::table('application_interviews')->insertGetId([
            'application_id' => $appId, 'interviewer_id' => $sup->user_id, 'round' => 1,
            'scheduled_at' => now()->addDay(), 'type' => 'in_person', 'status' => 'scheduled',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        NavBadges::forget();
        $this->assertSame(1, NavBadges::staff()['employee.interviews']);

        // And it is on the page, not just in the figure.
        $html = $this->actingAs($sup)->get('/employee/interviews')->getContent();
        $this->assertStringContainsString('nav-badge', $html, 'the badge is not rendered');

        // Once they have answered, it goes.
        Volt::actingAs($sup)->test('employee.interviews')
            ->call('open', $ivId)->set('recommendation', 'recommend')->call('record');

        NavBadges::forget();
        $this->assertSame(0, NavBadges::staff()['employee.interviews'], 'still badged after answering');
    }

    /**
     * Counting only "pending" made the badge look broken: it sat at zero while
     * work plainly waited. These are the three states that need HR next.
     */
    public function test_the_applications_badge_counts_everything_waiting_on_hr(): void
    {
        NavBadges::forget();
        $base = NavBadges::hr()['hr.applications'];

        $unopened   = $this->application('pending');
        $noInterview = $this->application('reviewed');

        NavBadges::forget();
        $this->assertSame($base + 2, NavBadges::hr()['hr.applications'],
            'an unopened one and a reviewed one with no interview should both count');

        // Reviewed, interview booked and not yet answered: that is the
        // interviewer's move, not HR's, so it drops off.
        $interviewId = DB::table('application_interviews')->insertGetId([
            'application_id' => $noInterview, 'interviewer_id' => null, 'round' => 1,
            'scheduled_at' => now()->addDay(), 'type' => 'in_person', 'status' => 'scheduled',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        NavBadges::forget();
        $this->assertSame($base + 1, NavBadges::hr()['hr.applications'],
            'waiting on the interviewer should not be badged to HR');

        // Answered, and still no decision: back to HR.
        DB::table('application_interviews')->where('interview_id', $interviewId)
            ->update(['recommendation' => 'recommend', 'status' => 'completed']);

        NavBadges::forget();
        $this->assertSame($base + 2, NavBadges::hr()['hr.applications'],
            'an answered interview with no decision is HR\'s move');

        // Decided.
        DB::table('job_applications')->where('application_id', $noInterview)->update(['status' => 'hired']);
        DB::table('job_applications')->where('application_id', $unopened)->update(['status' => 'rejected']);

        NavBadges::forget();
        $this->assertSame($base, NavBadges::hr()['hr.applications'], 'settled applications should not be badged');
    }

    private function application(string $status): int
    {
        $n = random_int(100000, 999999);
        $u = User::create(['full_name' => 'Badge Person', 'username' => "bp{$n}",
            'email' => "bp{$n}@example.test", 'password' => 'x', 'role' => 'employee']);
        $this->made[] = $u->user_id;

        $id = DB::table('job_applications')->insertGetId([
            'user_id' => $u->user_id, 'position_applied' => 'Crew', 'years_experience' => '',
            'status' => $status, 'application_date' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->apps[] = $id;

        return $id;
    }

    public function test_hr_is_badged_for_work_that_is_actually_pending(): void
    {
        NavBadges::forget();
        $before = NavBadges::hr();

        $n = random_int(100000, 999999);
        $cand = User::create(['full_name' => 'Badge Applicant', 'username' => "bap{$n}",
            'email' => "bap{$n}@example.test", 'password' => 'x', 'role' => 'employee']);
        $this->made[] = $cand->user_id;

        $this->apps[] = DB::table('job_applications')->insertGetId([
            'user_id' => $cand->user_id, 'position_applied' => 'Crew', 'years_experience' => '',
            'status' => 'pending', 'application_date' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        NavBadges::forget();
        $after = NavBadges::hr();

        $this->assertSame($before['hr.applications'] + 1, $after['hr.applications'],
            'a new pending application did not raise the count');

        fwrite(STDERR, "\n  HR badges now: ".json_encode($after)."\n\n");
    }
}
