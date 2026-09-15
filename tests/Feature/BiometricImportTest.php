<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Attendance\PunchFileReader;
use App\Services\Attendance\PunchImporter;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The scanner itself cannot be tested without the hardware, so everything that
 * could actually be wrong about an import lives in the importer and the file
 * reader, and is tested here: whose punch is whose, how a day is folded out of
 * several scans, and what happens to a row somebody typed by hand.
 */
class BiometricImportTest extends TestCase
{
    private array $createdUserIds = [];
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->createdUserIds as $id) {
            DB::table('hr_attendance')->whereIn('employee_id', function ($q) use ($id) {
                $q->select('employee_id')->from('employees')->where('user_id', $id);
            })->delete();
            DB::table('employees')->where('user_id', $id)->delete();
            DB::table('users')->where('user_id', $id)->delete();
        }

        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    private function employee(string $biometricId, ?string $shift = '08:00:00'): int
    {
        $n = random_int(100000, 999999);

        $user = User::create([
            'full_name' => 'Scanner Subject',
            'username'  => "bio{$n}",
            'email'     => "bio{$n}@example.test",
            'password'  => 'Password!2345',
            'role'      => 'employee',
        ]);
        $this->createdUserIds[] = $user->user_id;

        return DB::table('employees')->insertGetId([
            'user_id'      => $user->user_id,
            'job_title'    => 'Subject',
            'hire_date'    => '2020-01-01',
            'shift_start'  => $shift,
            'biometric_id' => $biometricId,
            'status'       => 'active',
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }

    private function file(string $contents, string $extension = 'csv'): string
    {
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'punches'.random_int(1000, 9999).'.'.$extension;
        file_put_contents($path, $contents);
        $this->tempFiles[] = $path;

        return $path;
    }

    // ------------------------------------------------------------- folding

    public function test_several_scans_in_a_day_become_one_row(): void
    {
        $id = $this->employee('101');

        (new PunchImporter)->import([
            ['biometric_id' => '101', 'timestamp' => '2020-06-01 07:58:00'],
            ['biometric_id' => '101', 'timestamp' => '2020-06-01 12:01:00'],
            ['biometric_id' => '101', 'timestamp' => '2020-06-01 13:00:00'],
            ['biometric_id' => '101', 'timestamp' => '2020-06-01 17:32:00'],
        ]);

        $rows = DB::table('hr_attendance')->where('employee_id', $id)->get();

        $this->assertCount(1, $rows, 'four scans in one day are one attendance day');
        $this->assertStringContainsString('07:58:00', $rows[0]->time_in, 'earliest scan is the arrival');
        $this->assertStringContainsString('17:32:00', $rows[0]->time_out, 'latest scan is the departure');
    }

    public function test_a_single_scan_leaves_the_departure_empty(): void
    {
        $id = $this->employee('102');

        (new PunchImporter)->import([
            ['biometric_id' => '102', 'timestamp' => '2020-06-02 08:00:00'],
        ]);

        $row = DB::table('hr_attendance')->where('employee_id', $id)->first();

        $this->assertNotNull($row->time_in);
        $this->assertNull($row->time_out, 'arriving is not the same as having left');
    }

    public function test_scans_are_split_by_day(): void
    {
        $id = $this->employee('103');

        (new PunchImporter)->import([
            ['biometric_id' => '103', 'timestamp' => '2020-06-03 08:00:00'],
            ['biometric_id' => '103', 'timestamp' => '2020-06-04 08:00:00'],
        ]);

        $this->assertSame(2, DB::table('hr_attendance')->where('employee_id', $id)->count());
    }

    // ------------------------------------------------------------- identity

    public function test_an_unknown_enrolment_number_is_reported_not_swallowed(): void
    {
        $this->employee('104');

        $summary = (new PunchImporter)->import([
            ['biometric_id' => '104', 'timestamp' => '2020-06-05 08:00:00'],
            ['biometric_id' => '999', 'timestamp' => '2020-06-05 08:00:00'],
        ]);

        $this->assertSame(['999'], $summary['unknown'],
            'a number nobody owns has to be named, or that person loses their month');
        $this->assertSame(1, $summary['days']);
    }

    // ------------------------------------------------------------- lateness

    public function test_the_arrival_decides_whether_the_day_is_late(): void
    {
        $onTime = $this->employee('105');
        $late = $this->employee('106');

        (new PunchImporter)->import([
            // Three minutes late is inside the grace period.
            ['biometric_id' => '105', 'timestamp' => '2020-06-06 08:03:00'],
            ['biometric_id' => '106', 'timestamp' => '2020-06-06 08:20:00'],
        ]);

        $this->assertSame('present', DB::table('hr_attendance')->where('employee_id', $onTime)->value('status'));
        $this->assertSame('late', DB::table('hr_attendance')->where('employee_id', $late)->value('status'));
    }

    public function test_somebody_with_no_shift_is_never_marked_late(): void
    {
        $id = $this->employee('107', null);

        (new PunchImporter)->import([
            ['biometric_id' => '107', 'timestamp' => '2020-06-07 11:00:00'],
        ]);

        $this->assertSame('present', DB::table('hr_attendance')->where('employee_id', $id)->value('status'));
    }

    // ------------------------------------------------------- manual entries

    public function test_a_day_entered_by_hand_is_not_overwritten(): void
    {
        $id = $this->employee('108');

        DB::table('hr_attendance')->insert([
            'employee_id' => $id,
            'date'        => '2020-06-08',
            'time_in'     => '2020-06-08 08:00:00',
            'status'      => 'present',
            'notes'       => 'Corrected by HR - scanner was down',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        (new PunchImporter)->import([
            ['biometric_id' => '108', 'timestamp' => '2020-06-08 09:45:00'],
        ]);

        $row = DB::table('hr_attendance')->where('employee_id', $id)->first();

        $this->assertStringContainsString('08:00:00', $row->time_in,
            'a correction made by a person survives the next sync');
        $this->assertSame('present', $row->status);
    }

    public function test_but_it_can_be_overwritten_on_purpose(): void
    {
        $id = $this->employee('109');

        DB::table('hr_attendance')->insert([
            'employee_id' => $id,
            'date'        => '2020-06-09',
            'time_in'     => '2020-06-09 08:00:00',
            'status'      => 'present',
            'notes'       => 'Typed in',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        (new PunchImporter)->import([
            ['biometric_id' => '109', 'timestamp' => '2020-06-09 09:45:00'],
        ], overwriteManual: true);

        $row = DB::table('hr_attendance')->where('employee_id', $id)->first();

        $this->assertStringContainsString('09:45:00', $row->time_in);
        $this->assertSame('late', $row->status);
    }

    public function test_re_running_a_sync_does_not_duplicate_days(): void
    {
        $id = $this->employee('110');

        $punches = [['biometric_id' => '110', 'timestamp' => '2020-06-10 08:00:00']];

        (new PunchImporter)->import($punches);
        (new PunchImporter)->import($punches);

        $this->assertSame(1, DB::table('hr_attendance')->where('employee_id', $id)->count());
    }

    // ----------------------------------------------------------- file reader

    public function test_incremental_imports_preserve_the_full_day(): void
    {
        $id = $this->employee('115');
        $importer = new PunchImporter;
        foreach (['08:00:00', '17:00:00', '12:00:00'] as $time) {
            $importer->import([['biometric_id' => '115', 'timestamp' => '2020-06-15 '.$time]]);
        }
        $row = DB::table('hr_attendance')->where('employee_id', $id)->first();
        $this->assertStringContainsString('08:00:00', $row->time_in);
        $this->assertStringContainsString('17:00:00', $row->time_out);
        $this->assertSame('present', $row->status);
    }

    public function test_split_date_and_time_are_combined(): void
    {
        $result = (new PunchFileReader)->read($this->file("116\t2020-06-16\t08:35:00\n", 'dat'));
        $this->assertSame('2020-06-16 08:35:00', $result['punches'][0]['timestamp']);
    }

    public function test_upload_import_refreshes_the_attendance_screen(): void
    {
        $id = $this->employee('117');
        $hr = User::where('username', 'hr')->firstOrFail();
        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent(
            'punches.csv', "User ID,Date/Time\n117,2020-06-17 08:00:00\n"
        );
        \Livewire\Volt\Volt::actingAs($hr)->test('hr.attendance')
            ->set('selectedDate', '2020-06-17')
            ->set('punchFile', $file)
            ->call('importFile')
            ->assertHasNoErrors()
            ->assertSet('syncSummary.days', 1);
        $this->assertDatabaseHas('hr_attendance', ['employee_id' => $id, 'date' => '2020-06-17']);
    }

    public function test_it_reads_the_tab_separated_export_the_device_writes(): void
    {
        $path = $this->file("111\t2020-06-11 08:01:00\t1\t0\n111\t2020-06-11 17:00:00\t1\t0\n", 'dat');

        $result = (new PunchFileReader)->read($path);

        $this->assertCount(2, $result['punches']);
        $this->assertSame('111', $result['punches'][0]['biometric_id']);
    }

    public function test_it_reads_a_csv_by_its_headers(): void
    {
        $path = $this->file("User ID,Name,Date/Time\n112,Dela Cruz,2020-06-12 08:00:00\n");

        $result = (new PunchFileReader)->read($path);

        $this->assertCount(1, $result['punches']);
        $this->assertSame('112', $result['punches'][0]['biometric_id']);
        $this->assertSame('2020-06-12 08:00:00', $result['punches'][0]['timestamp']);
    }

    public function test_rows_it_cannot_read_are_counted_rather_than_guessed(): void
    {
        $path = $this->file("User ID,Date/Time\n113,2020-06-13 08:00:00\n113,not a date\n,\n");

        $result = (new PunchFileReader)->read($path);

        $this->assertCount(1, $result['punches']);
        $this->assertSame(2, $result['unreadable']);
    }

    public function test_an_empty_file_is_refused(): void
    {
        $this->expectExceptionMessage('no rows');
        (new PunchFileReader)->read($this->file("\n\n"));
    }

    public function test_a_file_and_the_device_produce_the_same_result(): void
    {
        $id = $this->employee('114');
        $path = $this->file("User ID,Date/Time\n114,2020-06-14 07:55:00\n114,2020-06-14 17:10:00\n");

        $read = (new PunchFileReader)->read($path);
        (new PunchImporter)->import($read['punches']);

        $row = DB::table('hr_attendance')->where('employee_id', $id)->first();

        $this->assertStringContainsString('07:55:00', $row->time_in);
        $this->assertStringContainsString('17:10:00', $row->time_out);
    }
}
