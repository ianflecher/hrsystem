<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Print201Test extends TestCase
{
    public function test_it_prints_three_pages_for_hr(): void
    {
        $hr = User::where('username', 'hr')->first();
        $app = DB::table('job_applications')->first();
        if (! $hr || ! $app) { $this->markTestSkipped('need hr + an application'); }

        $html = $this->actingAs($hr)->get("/hr/applications/{$app->application_id}/201")
            ->assertOk()->getContent();

        fwrite(STDERR, "\n  sheets (pages)      : ".substr_count($html, 'class="sheet"'));
        fwrite(STDERR, "\n  page 1 of 3 footer  : ".(str_contains($html, 'Page 1 of 3') ? 'yes' : 'NO'));
        fwrite(STDERR, "\n  page 3 of 3 footer  : ".(str_contains($html, 'Page 3 of 3') ? 'yes' : 'NO'));
        fwrite(STDERR, "\n  print button        : ".(str_contains($html, 'window.print()') ? 'yes' : 'NO'));
        fwrite(STDERR, "\n  toolbar hidden print: ".(str_contains($html, '.toolbar { display: none; }') ? 'yes' : 'NO'));
        fwrite(STDERR, "\n  disclosures on it   : ".(str_contains($html, 'Applicant disclosures') ? 'yes' : 'NO'));
        fwrite(STDERR, "\n\n");

        $this->assertSame(3, substr_count($html, 'class="sheet"'), 'should be exactly three pages');
    }

    /** It carries health and criminal history: not for an ordinary employee. */
    public function test_an_employee_cannot_open_somebody_elses_201(): void
    {
        $employee = User::where('username', 'test.employee')->first();
        $app = DB::table('job_applications')->first();
        if (! $employee || ! $app) { $this->markTestSkipped('need an employee + an application'); }

        $status = $this->actingAs($employee)->get("/hr/applications/{$app->application_id}/201")->status();
        fwrite(STDERR, "\n  employee gets HTTP  : {$status}\n\n");

        $this->assertContains($status, [403, 302], 'an employee should not be able to read it');
    }

    public function test_a_guest_is_turned_away(): void
    {
        $app = DB::table('job_applications')->first();
        if (! $app) { $this->markTestSkipped('need an application'); }
        $this->get("/hr/applications/{$app->application_id}/201")->assertRedirect();
    }
}
