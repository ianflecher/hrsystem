<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Openings are managed in the HR back office and read by the careers page.
 *
 * The pair used to be two hardcoded arrays that had to be edited in step, so
 * the assertions that matter here are the ones tying the two ends together:
 * what HR posts is what applicants see, and what they can apply for.
 */
class JobOpeningsTest extends TestCase
{
    private array $createdPositionIds = [];

    protected function tearDown(): void
    {
        if ($this->createdPositionIds) {
            DB::table('job_positions')->whereIn('position_id', $this->createdPositionIds)->delete();
        }

        parent::tearDown();
    }

    private function hr(): User
    {
        $hr = User::where('username', 'hr')->first();

        if (! $hr) {
            $this->markTestSkipped('Seeded HR user not found; run `php artisan db:seed`.');
        }

        return $hr;
    }

    private function uniqueTitle(): string
    {
        return 'Test Role '.random_int(100000, 999999);
    }

    public function test_hr_can_post_an_opening(): void
    {
        $title = $this->uniqueTitle();

        Volt::actingAs($this->hr())
            ->test('hr.positions')
            ->call('openCreate')
            ->set('title', $title)
            ->set('employment_type', 'contract')
            ->set('description', 'Posted by a test.')
            ->call('save')
            ->assertHasNoErrors();

        $row = DB::table('job_positions')->where('title', $title)->first();
        $this->assertNotNull($row, 'the opening should have been created');
        $this->createdPositionIds[] = $row->position_id;

        $this->assertSame('contract', $row->employment_type);
        $this->assertEquals(1, $row->is_open);
    }

    public function test_posting_requires_a_title(): void
    {
        Volt::actingAs($this->hr())
            ->test('hr.positions')
            ->call('openCreate')
            ->set('title', '')
            ->call('save')
            ->assertHasErrors('title');
    }

    public function test_an_open_role_reaches_the_careers_page_and_its_application_form(): void
    {
        $title = $this->uniqueTitle();
        $this->createdPositionIds[] = DB::table('job_positions')->insertGetId([
            'title'           => $title,
            'employment_type' => 'full_time',
            'is_open'         => true,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $response = $this->get('/applicant/login');
        $response->assertOk();
        $response->assertSee($title);

        // The list and the dropdown read one source, so the role is offered
        // for application as well as advertised.
        $titles = Volt::test('auth.applicantlogin')
            ->instance()
            ->openPositions()
            ->pluck('title');

        $this->assertContains($title, $titles->all());
    }

    public function test_closing_a_role_withdraws_it_from_the_careers_page(): void
    {
        $title = $this->uniqueTitle();
        $id = DB::table('job_positions')->insertGetId([
            'title'           => $title,
            'employment_type' => 'full_time',
            'is_open'         => true,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
        $this->createdPositionIds[] = $id;

        $this->get('/applicant/login')->assertSee($title);

        Volt::actingAs($this->hr())
            ->test('hr.positions')
            ->call('toggleOpen', $id);

        $this->assertEquals(0, DB::table('job_positions')->where('position_id', $id)->value('is_open'));
        $this->get('/applicant/login')->assertDontSee($title);
    }

    public function test_the_openings_screen_is_closed_to_guests(): void
    {
        $this->get('/hr/positions')->assertRedirect('/admin/login');
    }
}
