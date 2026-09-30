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

    /**
     * A scanner id nobody real is using.
     *
     * The fixtures hardcoded 101, 102 and so on. Those became real enrolment
     * numbers the day the company's own were imported, and the test began
     * colliding with live staff - failing for a reason that had nothing to do
     * with what it was testing.
     */
    private function freeBiometricId(): string
    {
        do {
            $candidate = (string) random_int(900000, 999999);
        } while (DB::table('employees')->where('biometric_id', $candidate)->exists());

        return $candidate;
    }

    private function employee(string $biometricId, ?string $shift = '08:00:00', string $title = 'Subject', ?string $shiftEnd = null): int
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
            'job_title'    => $title,
            'hire_date'    => '2020-01-01',
            'shift_start'  => $shift,
            'shift_end'    => $shiftEnd,
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
        $bio101 = $this->freeBiometricId();
        $id = $this->employee($bio101);

        (new PunchImporter)->import([
            ['biometric_id' => $bio101, 'timestamp' => '2020-06-01 07:58:00'],
            ['biometric_id' => $bio101, 'timestamp' => '2020-06-01 12:01:00'],
            ['biometric_id' => $bio101, 'timestamp' => '2020-06-01 13:00:00'],
            ['biometric_id' => $bio101, 'timestamp' => '2020-06-01 17:32:00'],
        ]);

        $rows = DB::table('hr_attendance')->where('employee_id', $id)->get();

        $this->assertCount(1, $rows, 'four scans in one day are one attendance day');
        $this->assertStringContainsString('07:58:00', $rows[0]->time_in, 'earliest scan is the arrival');
        $this->assertStringContainsString('17:32:00', $rows[0]->time_out, 'latest scan is the departure');
    }

    /**
     * No lunch punched, only the coffee break. By position the break would be
     * read as the lunch; the keys pressed say otherwise.
     */
    public function test_the_key_pressed_places_a_punch_when_one_is_missing(): void
    {
        $bio = $this->freeBiometricId();
        $id = $this->employee($bio);

        (new PunchImporter)->import([
            ['biometric_id' => $bio, 'timestamp' => '2020-06-02 07:58:00', 'state' => 0],
            ['biometric_id' => $bio, 'timestamp' => '2020-06-02 15:30:00', 'state' => 4],
            ['biometric_id' => $bio, 'timestamp' => '2020-06-02 15:44:00', 'state' => 5],
            ['biometric_id' => $bio, 'timestamp' => '2020-06-02 17:05:00', 'state' => 1],
        ]);

        $row = DB::table('hr_attendance')->where('employee_id', $id)->first();

        $this->assertNull($row->lunch_in, 'no lunch was punched');
        $this->assertNull($row->lunch_out, 'no lunch was punched');
        $this->assertStringContainsString('15:30:00', $row->cb_in, 'CB out key is the coffee break');
        $this->assertStringContainsString('15:44:00', $row->cb_out, 'CB in key is the end of it');
        $this->assertStringContainsString('17:05:00', $row->time_out);
    }

    /** A guard's night duty is one day: the night it started. */
    public function test_a_guard_night_duty_is_one_day(): void
    {
        $bio = $this->freeBiometricId();
        $id = $this->employee($bio, null, 'SECURITY GUARD');

        (new PunchImporter)->import([
            ['biometric_id' => $bio, 'timestamp' => '2020-06-24 18:44:58', 'state' => 0],
            ['biometric_id' => $bio, 'timestamp' => '2020-06-25 07:00:07', 'state' => 1],
        ]);

        $rows = DB::table('hr_attendance')->where('employee_id', $id)->get();

        $this->assertCount(1, $rows, 'the morning time-out is not a day of its own');
        $this->assertSame('2020-06-24', substr((string) $rows[0]->date, 0, 10));
        $this->assertSame('2020-06-24 18:44:58', (string) $rows[0]->time_in);
        $this->assertSame('2020-06-25 07:00:07', (string) $rows[0]->time_out, 'the real timestamp is kept');
        $this->assertSame(720, \App\Support\WorkDay::workedMinutes($rows[0], 60,
            DB::table('employees')->where('employee_id', $id)->first()), 'twelve hours, no break taken off');
    }

    /** A guard on the morning duty checks in on the morning's own date. */
    public function test_a_guard_morning_duty_is_its_own_day(): void
    {
        $bio = $this->freeBiometricId();
        $id = $this->employee($bio, null, 'SECURITY GUARD');

        (new PunchImporter)->import([
            ['biometric_id' => $bio, 'timestamp' => '2020-06-26 07:01:00', 'state' => 1],
            ['biometric_id' => $bio, 'timestamp' => '2020-06-26 18:58:00', 'state' => 1],
            ['biometric_id' => $bio, 'timestamp' => '2020-06-27 06:55:00', 'state' => 0],
            ['biometric_id' => $bio, 'timestamp' => '2020-06-27 19:02:00', 'state' => 1],
        ]);

        $dates = DB::table('hr_attendance')->where('employee_id', $id)->orderBy('date')->pluck('date')
            ->map(fn ($d) => substr((string) $d, 0, 10))->all();

        $this->assertSame(['2020-06-26', '2020-06-27'], $dates, 'a check-in the next morning starts a new day');
    }

    /** Scheduled 7AM-7PM, worked noon to midnight: the midnight scan still ends that duty. */
    public function test_a_guard_noon_to_midnight_duty_is_one_day_whatever_the_schedule_said(): void
    {
        $bio = $this->freeBiometricId();
        $id = $this->employee($bio, '07:00:00', 'SECURITY GUARD', '19:00:00');

        (new PunchImporter)->import([
            ['biometric_id' => $bio, 'timestamp' => '2020-06-29 12:00:04', 'state' => 0],
            ['biometric_id' => $bio, 'timestamp' => '2020-06-30 00:00:07', 'state' => 1],
        ]);

        $rows = DB::table('hr_attendance')->where('employee_id', $id)->get();
        $this->assertCount(1, $rows, 'the midnight time-out is not a day of its own');
        $this->assertSame('2020-06-29', substr((string) $rows[0]->date, 0, 10));
        $this->assertSame('2020-06-30 00:00:07', (string) $rows[0]->time_out);
    }

    /** With the shift set, the shift decides: 22:00 to 06:00 ends the next morning. */
    public function test_an_overnight_shift_set_on_the_employee(): void
    {
        $bio = $this->freeBiometricId();
        $id = $this->employee($bio, '22:00:00', 'Machine operator', '06:00:00');

        (new PunchImporter)->import([
            ['biometric_id' => $bio, 'timestamp' => '2020-06-28 21:55:00'],
            ['biometric_id' => $bio, 'timestamp' => '2020-06-29 06:03:00'],
        ]);

        $row = DB::table('hr_attendance')->where('employee_id', $id)->sole();

        $this->assertSame('2020-06-28', substr((string) $row->date, 0, 10));
        $this->assertSame('2020-06-29 06:03:00', (string) $row->time_out);
        $this->assertSame(428, \App\Support\WorkDay::workedMinutes($row), '8h 8m across midnight, less the hour break');
    }

    /** Mid-day: in and out to lunch so far. The lunch is not the day's end. */
    public function test_a_lunch_scan_is_the_lunch_not_the_final_out(): void
    {
        $bio = $this->freeBiometricId();
        $id = $this->employee($bio);

        (new PunchImporter)->import([
            ['biometric_id' => $bio, 'timestamp' => '2020-06-04 07:58:00', 'state' => 0],
            ['biometric_id' => $bio, 'timestamp' => '2020-06-04 12:02:00', 'state' => 2],
        ]);

        $row = DB::table('hr_attendance')->where('employee_id', $id)->first();

        $this->assertStringContainsString('07:58:00', $row->time_in);
        $this->assertStringContainsString('12:02:00', $row->lunch_in, 'the lunch key is the lunch');
        $this->assertNull($row->time_out, 'nobody has left for the day yet');
    }

    /** The same key twice is a slip; the extra punch fills what is missing. */
    public function test_a_repeated_key_falls_back_to_the_order(): void
    {
        $bio = $this->freeBiometricId();
        $id = $this->employee($bio);

        (new PunchImporter)->import([
            ['biometric_id' => $bio, 'timestamp' => '2020-06-03 07:58:00', 'state' => 0],
            ['biometric_id' => $bio, 'timestamp' => '2020-06-03 12:00:00', 'state' => 0],
            ['biometric_id' => $bio, 'timestamp' => '2020-06-03 13:00:00', 'state' => 0],
            ['biometric_id' => $bio, 'timestamp' => '2020-06-03 17:00:00', 'state' => 1],
        ]);

        $row = DB::table('hr_attendance')->where('employee_id', $id)->first();

        $this->assertStringContainsString('07:58:00', $row->time_in);
        $this->assertStringContainsString('12:00:00', $row->lunch_in, 'read by position');
        $this->assertStringContainsString('13:00:00', $row->lunch_out, 'read by position');
        $this->assertStringContainsString('17:00:00', $row->time_out);
    }

    public function test_a_single_scan_leaves_the_departure_empty(): void
    {
        $bio102 = $this->freeBiometricId();
        $id = $this->employee($bio102);

        (new PunchImporter)->import([
            ['biometric_id' => $bio102, 'timestamp' => '2020-06-02 08:00:00'],
        ]);

        $row = DB::table('hr_attendance')->where('employee_id', $id)->first();

        $this->assertNotNull($row->time_in);
        $this->assertNull($row->time_out, 'arriving is not the same as having left');
    }

    public function test_scans_are_split_by_day(): void
    {
        $bio103 = $this->freeBiometricId();
        $id = $this->employee($bio103);

        (new PunchImporter)->import([
            ['biometric_id' => $bio103, 'timestamp' => '2020-06-03 08:00:00'],
            ['biometric_id' => $bio103, 'timestamp' => '2020-06-04 08:00:00'],
        ]);

        $this->assertSame(2, DB::table('hr_attendance')->where('employee_id', $id)->count());
    }

    // ------------------------------------------------------------- identity

    public function test_an_unknown_enrolment_number_is_reported_not_swallowed(): void
    {
        $bio104 = $this->freeBiometricId();
        $this->employee($bio104);

        $summary = (new PunchImporter)->import([
            ['biometric_id' => $bio104, 'timestamp' => '2020-06-05 08:00:00'],
            ['biometric_id' => '999', 'timestamp' => '2020-06-05 08:00:00'],
        ]);

        $this->assertSame(['999'], $summary['unknown'],
            'a number nobody owns has to be named, or that person loses their month');
        $this->assertSame(1, $summary['days']);
    }

    // ------------------------------------------------------------- lateness

    public function test_the_arrival_decides_whether_the_day_is_late(): void
    {
        $bio105 = $this->freeBiometricId();
        $onTime = $this->employee($bio105);
        $bio106 = $this->freeBiometricId();
        $late = $this->employee($bio106);

        (new PunchImporter)->import([
            // Three minutes late is inside the grace period.
            ['biometric_id' => $bio105, 'timestamp' => '2020-06-06 08:03:00'],
            ['biometric_id' => $bio106, 'timestamp' => '2020-06-06 08:20:00'],
        ]);

        $this->assertSame('present', DB::table('hr_attendance')->where('employee_id', $onTime)->value('status'));
        $this->assertSame('late', DB::table('hr_attendance')->where('employee_id', $late)->value('status'));
    }

    public function test_somebody_with_no_shift_is_never_marked_late(): void
    {
        $bio107 = $this->freeBiometricId();
        $id = $this->employee($bio107, null);

        (new PunchImporter)->import([
            ['biometric_id' => $bio107, 'timestamp' => '2020-06-07 11:00:00'],
        ]);

        $this->assertSame('present', DB::table('hr_attendance')->where('employee_id', $id)->value('status'));
    }

    public function test_a_dated_shift_decides_scanner_lateness_for_that_day(): void
    {
        $bio121 = $this->freeBiometricId();
        $id = $this->employee($bio121, '08:00:00');

        DB::table('shift_assignments')->insert([
            'employee_id' => $id,
            'work_date' => '2020-06-21',
            'starts_at' => '10:00:00',
            'ends_at' => '19:00:00',
            'label' => 'Late shift',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        (new PunchImporter)->import([
            ['biometric_id' => $bio121, 'timestamp' => '2020-06-21 09:30:00'],
            ['biometric_id' => $bio121, 'timestamp' => '2020-06-22 09:30:00'],
        ]);

        $byDate = DB::table('hr_attendance')->where('employee_id', $id)->pluck('status', 'date');

        $this->assertSame('present', $byDate['2020-06-21']);
        $this->assertSame('late', $byDate['2020-06-22']);
    }

    // ------------------------------------------------------- manual entries

    public function test_a_day_entered_by_hand_is_not_overwritten(): void
    {
        $bio108 = $this->freeBiometricId();
        $id = $this->employee($bio108);

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
            ['biometric_id' => $bio108, 'timestamp' => '2020-06-08 09:45:00'],
        ]);

        $row = DB::table('hr_attendance')->where('employee_id', $id)->first();

        $this->assertStringContainsString('08:00:00', $row->time_in,
            'a correction made by a person survives the next sync');
        $this->assertSame('present', $row->status);
    }

    public function test_but_it_can_be_overwritten_on_purpose(): void
    {
        $bio109 = $this->freeBiometricId();
        $id = $this->employee($bio109);

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
            ['biometric_id' => $bio109, 'timestamp' => '2020-06-09 09:45:00'],
        ], overwriteManual: true);

        $row = DB::table('hr_attendance')->where('employee_id', $id)->first();

        $this->assertStringContainsString('09:45:00', $row->time_in);
        $this->assertSame('late', $row->status);
    }

    public function test_re_running_a_sync_does_not_duplicate_days(): void
    {
        $bio110 = $this->freeBiometricId();
        $id = $this->employee($bio110);

        $punches = [['biometric_id' => $bio110, 'timestamp' => '2020-06-10 08:00:00']];

        (new PunchImporter)->import($punches);
        (new PunchImporter)->import($punches);

        $this->assertSame(1, DB::table('hr_attendance')->where('employee_id', $id)->count());
    }

    public function test_the_attendance_screen_shows_undertime_against_the_shift(): void
    {
        $bio120 = $this->freeBiometricId();
        $id = $this->employee($bio120);
        DB::table('employees')->where('employee_id', $id)->update(['shift_end' => '17:00:00']);
        DB::table('hr_attendance')->insert(['employee_id' => $id, 'date' => '2020-06-20',
            'time_in' => '2020-06-20 08:00:00', 'time_out' => '2020-06-20 16:20:00', 'status' => 'present',
            'created_at' => now(), 'updated_at' => now()]);

        $hr = User::where('username', 'hr')->firstOrFail();

        \Livewire\Volt\Volt::actingAs($hr)->test('hr.attendance')
            ->set('selectedDate', '2020-06-20')
            ->assertSee('40 min undertime');
    }

    // ----------------------------------------------------------- file reader

    public function test_incremental_imports_preserve_the_full_day(): void
    {
        $bio115 = $this->freeBiometricId();
        $id = $this->employee($bio115);
        $importer = new PunchImporter;
        foreach (['08:00:00', '17:00:00', '12:00:00'] as $time) {
            $importer->import([['biometric_id' => $bio115, 'timestamp' => '2020-06-15 '.$time]]);
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
        $bio117 = $this->freeBiometricId();
        $id = $this->employee($bio117);
        $hr = User::where('username', 'hr')->firstOrFail();
        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent(
            'punches.csv', "User ID,Date/Time\n{$bio117},2020-06-17 08:00:00\n"
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
        $bio114 = $this->freeBiometricId();
        $id = $this->employee($bio114);
        $path = $this->file("User ID,Date/Time\n{$bio114},2020-06-14 07:55:00\n{$bio114},2020-06-14 17:10:00\n");

        $read = (new PunchFileReader)->read($path);
        (new PunchImporter)->import($read['punches']);

        $row = DB::table('hr_attendance')->where('employee_id', $id)->first();

        $this->assertStringContainsString('07:55:00', $row->time_in);
        $this->assertStringContainsString('17:10:00', $row->time_out);
    }
}
