<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Volt;
use Tests\TestCase;

class InterviewerListTest extends TestCase
{
    public function test_supervisors_appear_in_the_schedule_interview_dropdown(): void
    {
        $hr = User::where('username', 'hr')->first();
        $app = DB::table('job_applications')->first();
        if (! $hr || ! $app) { $this->markTestSkipped('need hr + an application'); }

        // Open the dialog the way HR does, then read what the select offers.
        $html = Volt::actingAs($hr)->test('hr.applications')
            ->call('openInterviewModal', $app->application_id)
            ->html();

        preg_match('/<select[^>]*wire:model="interviewerId".*?<\/select>/s', $html, $m);
        $select = $m[0] ?? '';

        fwrite(STDERR, "\n  options in the dropdown:");
        foreach (DB::table('users')->whereIn('role', ['admin','hr','supervisor','leader'])->get() as $u) {
            $in = str_contains($select, '>'.$u->full_name.' (') || str_contains($select, $u->full_name);
            fwrite(STDERR, sprintf("\n    %-22s %-11s %s", $u->full_name, $u->role, $in ? 'listed' : 'MISSING'));
        }
        fwrite(STDERR, "\n\n");

        foreach (DB::table('users')->where('role', 'supervisor')->pluck('full_name') as $name) {
            $this->assertStringContainsString($name, $select, "{$name} is not in the interviewer dropdown");
        }
    }
}
