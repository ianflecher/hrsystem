<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * The offer, and the candidate's answer to it.
 *
 * Hiring used to go straight from HR pressing a button to an active employee
 * on payroll: the system recorded a decision it had never been given.
 */
class JobOfferTest extends TestCase
{
    private array $made = [];
    private array $apps = [];

    protected function tearDown(): void
    {
        DB::table('job_offers')->whereIn('application_id', $this->apps)->delete();
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
            'full_name' => 'Offer Person', 'username' => "off{$n}",
            'email' => "off{$n}@example.test", 'password' => 'x', 'role' => 'employee',
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

    private function sendOffer(int $applicationId, float $basic = 18000, float $allowance = 2000): void
    {
        $hr = User::where('username', 'hr')->first();

        Volt::actingAs($hr)->test('hr.applications')
            ->call('openHireModal', $applicationId)
            ->set('hireSalary', (string) $basic)
            ->set('hireAllowance', (string) $allowance)
            ->set('hireResponsibilities', 'Run the press, keep the screens clean, and check every batch before it leaves.')
            ->set('hireStartsOn', now()->addWeek()->toDateString())
            ->call('confirmHire')
            ->assertHasNoErrors();
    }

    public function test_sending_an_offer_does_not_hire_anybody(): void
    {
        [$u, $appId] = $this->candidate();
        $this->sendOffer($appId);

        $this->assertSame('offered',
            DB::table('job_applications')->where('application_id', $appId)->value('status'));

        $this->assertNull(DB::table('employees')->where('user_id', $u->user_id)->first(),
            'sending an offer created an employee');

        $offer = DB::table('job_offers')->where('application_id', $appId)->first();
        $this->assertSame('sent', $offer->status);
        $this->assertEquals(18000, $offer->basic_salary);
        $this->assertEquals(2000, $offer->allowance);
    }

    public function test_the_candidate_sees_the_terms_and_what_the_job_involves(): void
    {
        [$u, $appId] = $this->candidate();
        $this->sendOffer($appId);

        $html = $this->actingAs($u)->get('/applicant')->assertOk()->getContent();

        $this->assertStringContainsString('You have a job offer', $html);
        $this->assertStringContainsString('18,000.00', $html, 'the basic is not shown');
        $this->assertStringContainsString('2,000.00', $html, 'the allowance is not shown');
        $this->assertStringContainsString('20,000.00', $html, 'the total is not shown');
        $this->assertStringContainsString('Run the press', $html, 'the responsibilities are not shown');
    }

    public function test_accepting_hires_them_on_the_terms_they_accepted(): void
    {
        [$u, $appId] = $this->candidate();
        $this->sendOffer($appId);

        Volt::actingAs($u)->test('applicant.index')->call('acceptOffer')->assertHasNoErrors();

        $this->assertSame('hired',
            DB::table('job_applications')->where('application_id', $appId)->value('status'));
        $this->assertSame('accepted',
            DB::table('job_offers')->where('application_id', $appId)->value('status'));

        $e = DB::table('employees')->where('user_id', $u->user_id)->first();
        $this->assertNotNull($e, 'accepting did not hire them');
        $this->assertSame('active', $e->status);
        $this->assertEquals(18000, $e->salary, 'hired on a different basic than was accepted');
        $this->assertEquals(2000, $e->allowance, 'hired on a different allowance than was accepted');
        $this->assertSame('Press Operator', $e->job_title);

        // The day they agreed to start, not the day they clicked accept.
        $this->assertSame(now()->addWeek()->toDateString(), $e->hire_date);
    }

    public function test_declining_leaves_them_unhired_and_open_to_another_offer(): void
    {
        [$u, $appId] = $this->candidate();
        $this->sendOffer($appId);

        Volt::actingAs($u)->test('applicant.index')
            ->set('declineNote', 'The start date is too soon for me.')
            ->call('declineOffer')
            ->assertHasNoErrors();

        $offer = DB::table('job_offers')->where('application_id', $appId)->first();
        $this->assertSame('declined', $offer->status);
        $this->assertSame('The start date is too soon for me.', $offer->response_note);

        $this->assertNull(DB::table('employees')->where('user_id', $u->user_id)->first(),
            'declining hired them anyway');

        // Back where they were, so HR can offer again rather than the record
        // reading as a rejection by the company.
        $this->assertSame('shortlisted',
            DB::table('job_applications')->where('application_id', $appId)->value('status'));
    }

    public function test_a_second_offer_supersedes_the_first_rather_than_replacing_it(): void
    {
        [$u, $appId] = $this->candidate();
        $this->sendOffer($appId, 18000, 2000);
        $this->sendOffer($appId, 20000, 2500);

        $offers = DB::table('job_offers')->where('application_id', $appId)->orderBy('offer_id')->get();

        $this->assertCount(2, $offers, 'the first offer was overwritten');
        $this->assertSame('withdrawn', $offers[0]->status, 'the old offer was left open');
        $this->assertSame('sent', $offers[1]->status);
    }

    /** Nobody is hired on terms they were never shown. */
    public function test_hr_cannot_mark_somebody_hired_without_an_accepted_offer(): void
    {
        $hr = User::where('username', 'hr')->first();
        [$u, $appId] = $this->candidate();

        Volt::actingAs($hr)->test('hr.applications')
            ->call('updateApplicationStatus', $appId, 'hired');

        $this->assertNotSame('hired',
            DB::table('job_applications')->where('application_id', $appId)->value('status'));
        $this->assertNull(DB::table('employees')->where('user_id', $u->user_id)->first());
    }

    /** An answer must come from the person it was offered to. */
    public function test_somebody_else_cannot_accept_your_offer(): void
    {
        [$u, $appId] = $this->candidate();
        $this->sendOffer($appId);

        [$other] = $this->candidate();

        Volt::actingAs($other)->test('applicant.index')->call('acceptOffer')->assertForbidden();

        $this->assertSame('sent',
            DB::table('job_offers')->where('application_id', $appId)->value('status'));
    }
}
