<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ScheduleUpload;
use App\Support\PayPeriod;
use App\Support\SpreadsheetReader;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * HR's schedule sheet as it is kept - names down the side, days across, the
 * shift or RD / S / LEAVE / an event in each cell - read, and then written.
 */
class ScheduleUploadTest extends TestCase
{
    private array $userIds = [];
    private array $employeeIds = [];
    private ?string $file = null;

    protected function tearDown(): void
    {
        DB::table('shift_assignments')->whereIn('employee_id', $this->employeeIds)->delete();
        DB::table('leaves')->whereIn('employee_id', $this->employeeIds)->delete();
        DB::table('hr_attendance')->whereIn('employee_id', $this->employeeIds)->delete();
        DB::table('employees')->whereIn('employee_id', $this->employeeIds)->delete();
        DB::table('users')->whereIn('user_id', $this->userIds)->delete();
        if ($this->file) {
            @unlink($this->file);
        }
        parent::tearDown();
    }

    private function person(string $first, string $last, string $number): int
    {
        $n = random_int(100000, 999999);
        $user = User::create([
            'full_name' => "{$first} {$last}", 'first_name' => $first, 'last_name' => $last,
            'username' => "sched{$n}", 'email' => "sched{$n}@example.test",
            'password' => 'Password!2345', 'role' => 'employee',
        ]);
        $this->userIds[] = $user->user_id;
        $id = DB::table('employees')->insertGetId([
            'user_id' => $user->user_id, 'employee_no' => $number, 'job_title' => 'Crew',
            'hire_date' => '2020-01-01', 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->employeeIds[] = $id;

        return $id;
    }

    public function test_the_sheet_is_read_as_hr_writes_it_and_saved(): void
    {
        $suffix = random_int(100000, 999999);
        $byNumber = $this->person('Zyxwv', 'Qopmarn', "CAFE-{$suffix}");
        $byName = $this->person('Vuxtrel', 'Brankowitz', "IC-{$suffix}");

        // Oct 1-15, 2020 - day numbers across the top, as the sheets have them.
        $days = range(1, 15);
        $rows = [
            array_merge(['', 'NAME'], $days),
            array_merge(["CAFE-{$suffix}", 'ZYXWV'], ['8-5', '12NN-9PM', 'RD', 'S', 'LEAVE', 'LEAVE WITH PAY', 'LEAVE WITH PAY', 'MMDA EVENT', 'SCHOOL', '1PM-10PM', '', 'ABSENT', '7PM-7AM', '10-7', '9AM-6PM']),
            array_merge(['', 'Brankowitz, Vuxtrel'], array_fill(0, 15, '10-7')),
            array_merge(['', 'Nobody Atall'], array_fill(0, 15, '8-5')),
        ];
        $this->file = sys_get_temp_dir().DIRECTORY_SEPARATOR.'schedule'.random_int(1000, 9999).'.csv';
        $h = fopen($this->file, 'w');
        foreach ($rows as $row) {
            fputcsv($h, $row);
        }
        fclose($h);

        $plan = (new ScheduleUpload)->read(SpreadsheetReader::rows($this->file), PayPeriod::fromStart('2020-10-01'));

        $this->assertCount(2, $plan['people'], 'the two real people, by number and by "Surname, First"');
        $this->assertSame(['Nobody Atall'], $plan['unmatched']);
        // ABSENT is what happened, not a plan: it changes nothing and is not an error.
        $this->assertCount(0, $plan['unknown']);

        (new ScheduleUpload)->apply($plan, null);

        $shift = fn ($date) => DB::table('shift_assignments')->where('employee_id', $byNumber)->whereDate('work_date', $date)->first();
        $this->assertSame(['08:00:00', '17:00:00'], [$shift('2020-10-01')->starts_at, $shift('2020-10-01')->ends_at]);
        $this->assertSame(['12:00:00', '21:00:00'], [$shift('2020-10-02')->starts_at, $shift('2020-10-02')->ends_at]);
        $this->assertTrue((bool) $shift('2020-10-03')->rest_day);
        $this->assertSame(['19:00:00', '07:00:00'], [$shift('2020-10-13')->starts_at, $shift('2020-10-13')->ends_at], 'a night shift');
        $this->assertNull($shift('2020-10-11'), 'a blank cell changes nothing');

        $att = fn ($date) => DB::table('hr_attendance')->where('employee_id', $byNumber)->whereDate('date', $date)->first();
        $this->assertSame(['absent', 'Suspension'], [$att('2020-10-04')->status, $att('2020-10-04')->notes]);
        $this->assertSame('official_business', $att('2020-10-08')->status);

        $leaves = DB::table('leaves')->where('employee_id', $byNumber)->orderBy('start_date')->get();
        $this->assertCount(2, $leaves, 'one unpaid day, and two paid days as one request');
        $this->assertSame('unpaid', $leaves[0]->leave_type);
        $this->assertSame(['vacation', '2020-10-06', '2020-10-07'], [$leaves[1]->leave_type, substr((string) $leaves[1]->start_date, 0, 10), substr((string) $leaves[1]->end_date, 0, 10)]);

        $this->assertSame(15, DB::table('shift_assignments')->where('employee_id', $byName)->count());
    }
}
