<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Screens that pick up changes made elsewhere.
 *
 * Polling is only half of it: the component has to actually re-read on the
 * refresh. A poll that re-renders stale properties looks identical to no poll
 * at all, which is why these assert the new thing appears rather than just
 * that the attribute is present.
 */
class LiveRefreshTest extends TestCase
{
    private array $made = [];
    private array $apps = [];

    protected function tearDown(): void
    {
        DB::table('job_offers')->whereIn('application_id', $this->apps)->delete();
        DB::table('application_interviews')->whereIn('application_id', $this->apps)->delete();
        DB::table('job_applications')->whereIn('application_id', $this->apps)->delete();

        foreach ($this->made as $id) {
            DB::table('employees')->where('user_id', $id)->delete();
            DB::table('users')->where('user_id', $id)->delete();
        }

        parent::tearDown();
    }

    /** @return array{0: User, 1: int} */
    private function candidate(): array
    {
        $n = random_int(100000, 999999);

        $u = User::create([
            'full_name' => 'Waiting Person', 'username' => "wait{$n}",
            'email' => "wait{$n}@example.test", 'password' => 'x', 'role' => 'employee',
        ]);
        $this->made[] = $u->user_id;

        $id = DB::table('job_applications')->insertGetId([
            'user_id' => $u->user_id, 'position_applied' => 'Press Operator',
            'years_experience' => '', 'status' => 'shortlisted',
            'application_date' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->apps[] = $id;

        return [$u, $id];
    }

    public function test_the_candidates_page_picks_up_an_offer_sent_while_it_is_open(): void
    {
        $hr = User::where('username', 'hr')->first();
        [$u, $appId] = $this->candidate();

        // Their page, open, with nothing on it yet.
        $page = Volt::actingAs($u)->test('applicant.index');
        $this->assertStringNotContainsString('You have a job offer', $page->html());
        $this->assertStringContainsString('wire:poll', $page->html(), 'the page never refreshes itself');

        // HR sends one, elsewhere.
        Volt::actingAs($hr)->test('hr.applications')
            ->call('openHireModal', $appId)
            ->set('hireSalary', '21000')
            ->set('hireAllowance', '1500')
            ->set('hireResponsibilities', 'Run the press and check every batch before it leaves.')
            ->set('hireStartsOn', now()->addWeek()->toDateString())
            ->call('confirmHire')
            ->assertHasNoErrors();

        // Volt::actingAs above switched the authenticated user to HR, and the
        // page reads Auth::user(). Back to the candidate before the poll runs,
        // or load() looks up HR's application and finds nothing.
        $this->actingAs($u);

        // What the poll calls. Nobody reloaded anything.
        $page->call('load');

        $html = $page->html();
        $this->assertStringContainsString('You have a job offer', $html, 'the offer did not arrive on its own');
        $this->assertStringContainsString('21,000.00', $html);
        $this->assertStringContainsString('22,500.00', $html, 'the total is wrong or missing');
    }

    /**
     * Typing must survive the refresh. wire:model is deferred, so a poll
     * landing mid-sentence would re-render the box from the server and take
     * the sentence with it - which is why the poll is held while they type.
     */
    public function test_the_refresh_is_held_while_they_type_a_reason_to_decline(): void
    {
        $hr = User::where('username', 'hr')->first();
        [$u, $appId] = $this->candidate();

        Volt::actingAs($hr)->test('hr.applications')
            ->call('openHireModal', $appId)
            ->set('hireSalary', '21000')
            ->set('hireResponsibilities', 'Run the press and check every batch before it leaves.')
            ->call('confirmHire');

        $this->actingAs($u);
        $page = Volt::actingAs($u)->test('applicant.index');
        $this->assertStringContainsString('wire:poll', $page->html());

        $page->set('confirmingDecline', true);
        $this->assertStringNotContainsString('wire:poll', $page->html(),
            'it would refresh while they are writing and lose what they wrote');
    }

    public function test_the_supervisor_screen_picks_up_a_newly_assigned_interview(): void
    {
        $hr = User::where('username', 'hr')->first();
        $carla = User::where('username', 'carla')->first();
        [$u, $appId] = $this->candidate();

        DB::table('job_applications')->where('application_id', $appId)->update(['status' => 'reviewed']);

        $page = Volt::actingAs($carla)->test('employee.interviews');
        $this->assertStringNotContainsString('Waiting Person', $page->html());

        Volt::actingAs($hr)->test('hr.applications')
            ->call('openInterviewModal', $appId)
            ->set('interviewDate', now()->addDays(2)->format('Y-m-d'))
            ->set('interviewTime', '10:00')
            ->set('interviewerId', $carla->user_id)
            ->set('interviewType', 'in_person')
            ->call('saveInterviewSchedule')
            ->assertHasNoErrors();

        // Their list is read fresh on every render, so the poll is enough.
        $this->assertStringContainsString('Waiting Person',
            Volt::actingAs($carla)->test('employee.interviews')->html(),
            'the interview did not arrive on its own');
    }
}
