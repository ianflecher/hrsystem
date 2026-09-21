<?php

namespace Tests\Feature;

use App\Http\Controllers\HrReportController;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\TestCase;

/**
 * The CSVs HR pulls out of /hr/reports/download.
 *
 * Two things had gone wrong with them. An empty report came out zero bytes,
 * with not even a header row, which looks exactly like a download that failed.
 * And the leave report's window ended today, so upcoming leave - the only kind
 * anybody can still act on - was never in it.
 */
class ReportDownloadTest extends TestCase
{
    private array $temporaryUserIds = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryUserIds as $id) {
            $employeeId = DB::table('employees')->where('user_id', $id)->value('employee_id');
            if ($employeeId) {
                DB::table('leaves')->where('employee_id', $employeeId)->delete();
                DB::table('hr_attendance')->where('employee_id', $employeeId)->delete();
                DB::table('hr_payroll')->where('employee_id', $employeeId)->delete();
            }
            DB::table('employees')->where('user_id', $id)->delete();
            DB::table('users')->where('user_id', $id)->delete();
        }

        parent::tearDown();
    }

    public static function reports(): array
    {
        return array_map(fn ($r) => [$r],
            array_combine(
                ['headcount', 'attendance', 'leave', 'payroll', 'sss', 'philhealth', 'pagibig', 'bir'],
                ['headcount', 'attendance', 'leave', 'payroll', 'sss', 'philhealth', 'pagibig', 'bir']
            ));
    }

    #[DataProvider('reports')]
    public function test_a_report_with_nothing_in_it_still_has_headers(string $report): void
    {
        $csv = $this->download($report);

        $this->assertNotSame('', trim($csv), "the {$report} report downloaded as an empty file");

        $header = str_getcsv(strtok($csv, "\n"));
        $this->assertNotEmpty($header);
        $this->assertSame(
            $this->columns($report), $header,
            "the {$report} header does not match the columns the controller declares"
        );
    }

    /**
     * The header list is written by hand, so it can drift from the select that
     * produces the rows. Wherever there is a row to look at, the two are
     * compared for real.
     */
    #[DataProvider('reports')]
    public function test_declared_headers_match_the_columns_the_query_returns(string $report): void
    {
        $this->employeeWithHistory();

        $rows = new ReflectionMethod(HrReportController::class, 'rows');
        $rows->setAccessible(true);

        $first = null;
        foreach ($rows->invoke(new HrReportController, $report, '1900-01-01', '2999-01-01', null) as $row) {
            $first = (array) $row;
            break;
        }

        if ($first === null) {
            $this->markTestSkipped("no {$report} rows to compare against");
        }

        $this->assertSame(array_keys($first), $this->columns($report));
    }

    public function test_the_leave_report_includes_leave_that_has_not_happened_yet(): void
    {
        $employeeId = $this->employeeWithHistory();
        $starts = now()->addDays(20)->toDateString();

        DB::table('leaves')->insert([
            'employee_id' => $employeeId, 'leave_type' => 'vacation',
            'start_date' => $starts, 'end_date' => $starts, 'total_days' => 1,
            'reason' => 'Report test', 'status' => 'pending',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertStringContainsString(
            $starts, $this->download('leave'),
            'leave twenty days out is missing from the leave report'
        );
    }

    private function download(string $report): string
    {
        $response = $this->actingAs($this->hr())->get('/hr/reports/download?report='.$report);
        $response->assertOk();

        return $response->streamedContent();
    }

    private function columns(string $report): array
    {
        $m = new ReflectionMethod(HrReportController::class, 'columns');
        $m->setAccessible(true);

        return $m->invoke(new HrReportController, $report);
    }

    private function hr(): User
    {
        return User::where('username', 'hr')->firstOrFail();
    }

    /** An employee with one day of attendance, so more reports have a row. */
    private function employeeWithHistory(): int
    {
        $n = random_int(100000, 999999);

        $user = User::create([
            'full_name' => 'Report Test Employee',
            'username'  => "reporttest{$n}",
            'email'     => "reporttest{$n}@example.test",
            'password'  => 'Password!2345',
            'role'      => 'employee',
        ]);
        $this->temporaryUserIds[] = $user->user_id;

        $employeeId = DB::table('employees')->insertGetId([
            'user_id' => $user->user_id, 'job_title' => 'Report Test',
            'hire_date' => now()->subYear()->toDateString(), 'salary' => 22000,
            'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('hr_attendance')->insert([
            'employee_id' => $employeeId, 'date' => now()->subDay()->toDateString(),
            'time_in' => now()->subDay()->setTime(8, 0), 'time_out' => now()->subDay()->setTime(17, 0),
            'status' => 'present', 'created_at' => now(), 'updated_at' => now(),
        ]);

        // One payslip, so the payroll report and the four government reports
        // have a row to compare their headers against too - those are the long
        // column lists, and the ones most likely to drift unnoticed. It is
        // marked draft and removed again in tearDown.
        DB::table('hr_payroll')->insert([
            'employee_id' => $employeeId,
            'period_start' => now()->subDays(15)->toDateString(),
            'period_end' => now()->subDay()->toDateString(),
            'gross_pay' => 11000, 'net_pay' => 10000, 'deductions' => 1000,
            'status' => 'draft', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $employeeId;
    }
}
