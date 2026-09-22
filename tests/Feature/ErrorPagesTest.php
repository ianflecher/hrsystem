<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The error pages, and the way back they offer.
 *
 * An error page is sometimes served when something else is broken, so it must
 * not depend on a build step, a CDN or anything clever.
 */
class ErrorPagesTest extends TestCase
{
    public function test_an_unknown_address_gets_the_404_page(): void
    {
        $r = $this->get('/no-such-page-anywhere');

        $r->assertStatus(404);
        $r->assertSee('That page is not here.', false);
        $r->assertSee('404', false);

        // It used to redirect to the careers page and say nothing at all.
        $r->assertDontSee('Imprint Customs Careers', false);
    }

    public function test_the_403_page_is_shown_when_something_is_not_yours(): void
    {
        $employee = User::where('username', 'test.employee')->first();
        $app = DB::table('job_applications')->first();
        if (! $employee || ! $app) { $this->markTestSkipped('need an employee + an application'); }

        // The printable 201 file is HR only.
        $r = $this->actingAs($employee)->get("/hr/applications/{$app->application_id}/201");

        $r->assertStatus(403);
        $r->assertSee('This one is not yours to open.', false);
    }

    /** A stranger wants the careers page; somebody signed in wants their own. */
    public function test_the_way_back_suits_whoever_is_reading_it(): void
    {
        $guest = $this->get('/no-such-page-anywhere');
        $guest->assertSee(route('landing'), false);

        $hr = User::where('username', 'hr')->first();
        if ($hr) {
            $this->actingAs($hr)->get('/no-such-page-anywhere')
                ->assertSee(route('hr.home'), false);
        }

        // An applicant has the employee role but no employee record, so the
        // staff dashboard would only turn them away a second time.
        $applicant = User::where('username', 'test.admin')->first();
        if ($applicant && ! DB::table('employees')->where('user_id', $applicant->user_id)->exists()) {
            $this->actingAs($applicant)->get('/no-such-page-anywhere')
                ->assertSee(route('applicant.index'), false);
        }
    }

    /** It must stand on its own: no stylesheet to fetch, no script to run. */
    public function test_the_page_needs_nothing_external(): void
    {
        $html = $this->get('/no-such-page-anywhere')->getContent();

        $this->assertStringNotContainsString('<link rel="stylesheet"', $html);
        $this->assertStringNotContainsString('cdn.', $html);
        $this->assertStringNotContainsString('@vite', $html);
        $this->assertStringContainsString('<style>', $html, 'the styling should be inline');
    }
}
