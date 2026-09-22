<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Volt;
use Tests\TestCase;

class PayslipLinkTest extends TestCase
{
    /**
     * The printable payslip is a laid-out document, so it reads better than
     * the dialog does - but the way to it was at the very bottom, which meant
     * scrolling the whole cramped version to reach the uncramped one.
     */
    public function test_the_print_link_is_reachable_without_scrolling(): void
    {
        $hr = User::where('username', 'hr')->first();
        $row = DB::table('hr_payroll')->first();
        if (! $hr || ! $row) { $this->markTestSkipped('need hr + a payslip'); }

        $html = Volt::actingAs($hr)->test('hr.payroll')
            ->call('viewPayrollDetails', $row->employee_id)
            ->html();

        $link = route('payslip.show', $row->payroll_id);
        $this->assertStringContainsString($link, $html, 'no link to the printable payslip');
        $this->assertSame(1, substr_count($html, $link), 'the link is duplicated');

        // Before the breakdown, not after it.
        $linkAt = strpos($html, $link);
        $breakdownAt = strpos($html, 'Payroll Breakdown');
        $this->assertNotFalse($breakdownAt);
        $this->assertLessThan($breakdownAt, $linkAt,
            'the print link is still below the content it lets you skip');
    }

    public function test_the_printable_payslip_opens(): void
    {
        $hr = User::where('username', 'hr')->first();
        $row = DB::table('hr_payroll')->first();
        if (! $hr || ! $row) { $this->markTestSkipped('need hr + a payslip'); }

        $this->actingAs($hr)->get(route('payslip.show', $row->payroll_id))->assertOk();
    }
}
