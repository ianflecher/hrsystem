<?php

namespace Tests\Feature;

use App\Services\Attendance\PunchImporter;
use App\Support\WorkDay;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A working day is six punches: first in, lunch in, lunch out, cb in, cb out,
 * final out.
 *
 * The record held two. Both breaks were invisible, and the hours were worked
 * out by subtracting a fixed break_minutes from the shift whether or not
 * anybody took a break - so somebody who worked through their lunch lost an
 * hour of a day they had spent at the machine.
 */
class SixPunchDayTest extends TestCase
{
    private int $employeeId;
    private int $userId;
    private string $bio;

    protected function setUp(): void
    {
        parent::setUp();

        $n = random_int(100000, 999999);

        $this->userId = DB::table('users')->insertGetId([
            'full_name' => 'Punch Subject', 'username' => "px{$n}",
            'email' => "px{$n}@example.test", 'password' => 'x', 'role' => 'employee',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        do {
            $this->bio = (string) random_int(800000, 899999);
        } while (DB::table('employees')->where('biometric_id', $this->bio)->exists());

        $this->employeeId = DB::table('employees')->insertGetId([
            'user_id' => $this->userId, 'job_title' => 'Operator', 'hire_date' => '2020-01-01',
            'biometric_id' => $this->bio, 'shift_start' => '08:00:00', 'shift_end' => '17:00:00',
            'salary' => 15000, 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        DB::table('hr_attendance')->where('employee_id', $this->employeeId)->delete();
        DB::table('employees')->where('employee_id', $this->employeeId)->delete();
        DB::table('users')->where('user_id', $this->userId)->delete();

        parent::tearDown();
    }

    /** A row with whichever punches are given. */
    private function day(array $punches): object
    {
        return (object) array_merge(
            array_fill_keys(array_keys(WorkDay::PUNCHES), null),
            $punches,
        );
    }

    // ------------------------------------------------------------ the shape

    public function test_a_day_has_six_punches_in_order(): void
    {
        $this->assertSame(
            ['time_in', 'lunch_in', 'lunch_out', 'cb_in', 'cb_out', 'time_out'],
            array_keys(WorkDay::PUNCHES),
        );

        $this->assertSame(
            ['First in', 'Lunch in', 'Lunch out', 'CB in', 'CB out', 'Final out'],
            array_values(WorkDay::PUNCHES),
        );
    }

    public function test_the_columns_exist_on_the_record(): void
    {
        $columns = array_map(fn ($c) => $c->Field, DB::select('SHOW COLUMNS FROM hr_attendance'));

        foreach (array_keys(WorkDay::PUNCHES) as $punch) {
            $this->assertContains($punch, $columns, "hr_attendance has no {$punch}");
        }
    }

    // ------------------------------------------------------------- the hours

    public function test_the_breaks_that_were_punched_are_the_ones_deducted(): void
    {
        // In at 8, an hour for lunch, fifteen minutes for a break, out at 6.
        $row = $this->day([
            'time_in'   => '2026-06-01 08:00:00',
            'lunch_in'  => '2026-06-01 12:00:00',
            'lunch_out' => '2026-06-01 13:00:00',
            'cb_in'     => '2026-06-01 15:00:00',
            'cb_out'    => '2026-06-01 15:15:00',
            'time_out'  => '2026-06-01 18:00:00',
        ]);

        $this->assertSame(75, WorkDay::breakMinutes($row), 'an hour and a quarter of break');

        // Ten hours on the clock, less the seventy-five minutes.
        $this->assertSame(8.75, WorkDay::workedHours($row, 60));
    }

    /**
     * The point of recording them. Somebody who worked through lunch used to
     * lose an hour to an assumption.
     */
    public function test_working_through_lunch_is_not_deducted_for(): void
    {
        $worked = $this->day([
            'time_in'  => '2026-06-01 08:00:00',
            'cb_in'    => '2026-06-01 15:00:00',
            'cb_out'   => '2026-06-01 15:15:00',
            'time_out' => '2026-06-01 17:00:00',
        ]);

        // Nine on the clock, a quarter hour of break punched, so 8.75 - not
        // 8.0, which is what a fixed sixty-minute deduction would have given.
        $this->assertSame(8.75, WorkDay::workedHours($worked, 60));
    }

    /**
     * Nobody punched a break, so the shift's assumption is all there is. That
     * is the old behaviour, kept for days and people the scanner never saw.
     */
    public function test_with_no_break_punched_the_shift_assumption_is_used(): void
    {
        $row = $this->day([
            'time_in'  => '2026-06-01 08:00:00',
            'time_out' => '2026-06-01 17:00:00',
        ]);

        $this->assertFalse(WorkDay::hasPunchedBreak($row));
        $this->assertSame(8.0, WorkDay::workedHours($row, 60));
        $this->assertSame(9.0, WorkDay::workedHours($row, 0));
    }

    /**
     * Half a break is not a break. Somebody who went to lunch and never
     * punched back is a day to look at, not a length to guess at.
     */
    public function test_a_break_with_only_one_punch_deducts_nothing(): void
    {
        $row = $this->day([
            'time_in'  => '2026-06-01 08:00:00',
            'lunch_in' => '2026-06-01 12:00:00',
            'time_out' => '2026-06-01 17:00:00',
        ]);

        $this->assertSame(0, WorkDay::breakMinutes($row));
        $this->assertContains('Lunch in with no lunch out', WorkDay::problems($row));
    }

    public function test_a_day_with_no_final_out_is_not_paid_hours(): void
    {
        $row = $this->day(['time_in' => '2026-06-01 08:00:00']);

        $this->assertSame(0.0, WorkDay::workedHours($row, 60));
        $this->assertContains('No final out', WorkDay::problems($row));
    }

    public function test_punches_out_of_order_are_reported(): void
    {
        $row = $this->day([
            'time_in'   => '2026-06-01 08:00:00',
            'lunch_in'  => '2026-06-01 13:00:00',
            'lunch_out' => '2026-06-01 12:00:00',
            'time_out'  => '2026-06-01 17:00:00',
        ]);

        $this->assertNotEmpty(WorkDay::problems($row));
        $this->assertSame(0, WorkDay::breakMinutes($row), 'a backwards break was counted');
    }

    // --------------------------------------------------- from the scanner

    public function test_the_scanner_fills_all_six_slots_in_order(): void
    {
        (new PunchImporter)->import([
            ['biometric_id' => $this->bio, 'timestamp' => '2026-06-02 07:58:00'],
            ['biometric_id' => $this->bio, 'timestamp' => '2026-06-02 12:01:00'],
            ['biometric_id' => $this->bio, 'timestamp' => '2026-06-02 12:58:00'],
            ['biometric_id' => $this->bio, 'timestamp' => '2026-06-02 15:00:00'],
            ['biometric_id' => $this->bio, 'timestamp' => '2026-06-02 15:14:00'],
            ['biometric_id' => $this->bio, 'timestamp' => '2026-06-02 17:32:00'],
        ]);

        $row = DB::table('hr_attendance')->where('employee_id', $this->employeeId)
            ->whereDate('date', '2026-06-02')->first();

        $this->assertNotNull($row);
        $this->assertSame('2026-06-02 07:58:00', $row->time_in);
        $this->assertSame('2026-06-02 12:01:00', $row->lunch_in);
        $this->assertSame('2026-06-02 12:58:00', $row->lunch_out);
        $this->assertSame('2026-06-02 15:00:00', $row->cb_in);
        $this->assertSame('2026-06-02 15:14:00', $row->cb_out);
        $this->assertSame('2026-06-02 17:32:00', $row->time_out);
    }

    /** Four punches is a lunch and nothing else; the cb slots stay empty. */
    public function test_four_punches_fill_as_far_as_they_reach(): void
    {
        (new PunchImporter)->import([
            ['biometric_id' => $this->bio, 'timestamp' => '2026-06-03 08:00:00'],
            ['biometric_id' => $this->bio, 'timestamp' => '2026-06-03 12:00:00'],
            ['biometric_id' => $this->bio, 'timestamp' => '2026-06-03 13:00:00'],
            ['biometric_id' => $this->bio, 'timestamp' => '2026-06-03 17:00:00'],
        ]);

        $row = DB::table('hr_attendance')->where('employee_id', $this->employeeId)
            ->whereDate('date', '2026-06-03')->first();

        $this->assertSame('2026-06-03 08:00:00', $row->time_in);
        $this->assertSame('2026-06-03 12:00:00', $row->lunch_in);
        $this->assertSame('2026-06-03 13:00:00', $row->lunch_out);
        $this->assertNull($row->cb_in);
        $this->assertNull($row->cb_out);
        $this->assertSame('2026-06-03 17:00:00', $row->time_out);

        $this->assertSame(8.0, WorkDay::workedHours($row, 0));
    }

    /** Two punches is the day it always was: in and out. */
    public function test_two_punches_are_still_just_in_and_out(): void
    {
        (new PunchImporter)->import([
            ['biometric_id' => $this->bio, 'timestamp' => '2026-06-04 08:00:00'],
            ['biometric_id' => $this->bio, 'timestamp' => '2026-06-04 17:00:00'],
        ]);

        $row = DB::table('hr_attendance')->where('employee_id', $this->employeeId)
            ->whereDate('date', '2026-06-04')->first();

        $this->assertSame('2026-06-04 08:00:00', $row->time_in);
        $this->assertSame('2026-06-04 17:00:00', $row->time_out);
        $this->assertNull($row->lunch_in);
    }

    /**
     * A scanner that reads the same finger twice within the minute is one
     * punch. Two rows for it would shift every later slot along by one and
     * turn a lunch into a coffee break.
     */
    public function test_a_double_read_does_not_shift_the_slots(): void
    {
        (new PunchImporter)->import([
            ['biometric_id' => $this->bio, 'timestamp' => '2026-06-05 08:00:00'],
            ['biometric_id' => $this->bio, 'timestamp' => '2026-06-05 08:00:20'],
            ['biometric_id' => $this->bio, 'timestamp' => '2026-06-05 12:00:00'],
            ['biometric_id' => $this->bio, 'timestamp' => '2026-06-05 13:00:00'],
            ['biometric_id' => $this->bio, 'timestamp' => '2026-06-05 17:00:00'],
        ]);

        $row = DB::table('hr_attendance')->where('employee_id', $this->employeeId)
            ->whereDate('date', '2026-06-05')->first();

        $this->assertSame('2026-06-05 12:00:00', $row->lunch_in, 'the double read shifted the slots');
        $this->assertSame('2026-06-05 13:00:00', $row->lunch_out);
        $this->assertSame('2026-06-05 17:00:00', $row->time_out);
    }

    // ------------------------------------------------------- what the day is

    /**
     * First punch opens the day, last closes it, and the lunch and then the
     * coffee break fall between. The coffee break is optional.
     */
    public function test_the_punches_fall_into_the_day_in_order(): void
    {
        (new PunchImporter)->import([
            ['biometric_id' => $this->bio, 'timestamp' => '2026-07-01 08:00:00', 'type' => 'i'],
            ['biometric_id' => $this->bio, 'timestamp' => '2026-07-01 12:00:00', 'type' => 'o'],
            ['biometric_id' => $this->bio, 'timestamp' => '2026-07-01 13:00:00', 'type' => 'i'],
            ['biometric_id' => $this->bio, 'timestamp' => '2026-07-01 15:00:00', 'type' => 'o'],
            ['biometric_id' => $this->bio, 'timestamp' => '2026-07-01 15:15:00', 'type' => 'i'],
            ['biometric_id' => $this->bio, 'timestamp' => '2026-07-01 17:00:00', 'type' => 'o'],
        ]);

        $row = DB::table('hr_attendance')->where('employee_id', $this->employeeId)
            ->whereDate('date', '2026-07-01')->first();

        $this->assertSame('2026-07-01 08:00:00', $row->time_in);
        $this->assertSame('2026-07-01 12:00:00', $row->lunch_in);
        $this->assertSame('2026-07-01 13:00:00', $row->lunch_out);
        $this->assertSame('2026-07-01 15:00:00', $row->cb_in);
        $this->assertSame('2026-07-01 15:15:00', $row->cb_out);
        $this->assertSame('2026-07-01 17:00:00', $row->time_out);

        $this->assertSame(75, WorkDay::breakMinutes($row));
    }

    /**
     * Somebody goes to lunch, forgets to punch back, and punches out at the
     * end of the day. The last punch is the final out whatever else is
     * missing, so the day still ends when they left rather than at lunchtime.
     */
    public function test_a_missed_punch_does_not_shift_the_day(): void
    {
        (new PunchImporter)->import([
            ['biometric_id' => $this->bio, 'timestamp' => '2026-07-02 08:00:00', 'type' => 'i'],
            ['biometric_id' => $this->bio, 'timestamp' => '2026-07-02 12:00:00', 'type' => 'o'],
            ['biometric_id' => $this->bio, 'timestamp' => '2026-07-02 17:00:00', 'type' => 'o'],
        ]);

        $row = DB::table('hr_attendance')->where('employee_id', $this->employeeId)
            ->whereDate('date', '2026-07-02')->first();

        $this->assertSame('2026-07-02 08:00:00', $row->time_in);
        $this->assertSame('2026-07-02 17:00:00', $row->time_out,
            'the day ended at the last punch, not the first one after lunch');
        $this->assertSame('2026-07-02 12:00:00', $row->lunch_in);
        $this->assertNull($row->lunch_out, 'they never punched back from lunch');

        // Half a break deducts nothing rather than guessing at its length.
        $this->assertSame(0, WorkDay::breakMinutes($row));
        $this->assertContains('Lunch in with no lunch out', WorkDay::problems($row));
    }

    /** A punch that opened a break nobody returned from is the final out. */
    public function test_leaving_for_the_day_is_not_recorded_as_a_break(): void
    {
        (new PunchImporter)->import([
            ['biometric_id' => $this->bio, 'timestamp' => '2026-07-03 08:00:00', 'type' => 'i'],
            ['biometric_id' => $this->bio, 'timestamp' => '2026-07-03 17:00:00', 'type' => 'o'],
        ]);

        $row = DB::table('hr_attendance')->where('employee_id', $this->employeeId)
            ->whereDate('date', '2026-07-03')->first();

        $this->assertSame('2026-07-03 17:00:00', $row->time_out);
        $this->assertNull($row->lunch_in, 'going home was filed as going to lunch');
        $this->assertSame(9.0, WorkDay::workedHours($row, 0));
    }

    /**
     * A break of a few seconds over the minute is not rounded away.
     *
     * Carbon hands back a float here and adding it to an int truncates
     * silently, so a break was being shortened by up to a minute with nobody
     * told - in the employer's favour, every time.
     */
    public function test_break_minutes_are_not_truncated(): void
    {
        $row = $this->day([
            'time_in'   => '2026-06-01 08:00:00',
            'lunch_in'  => '2026-06-01 12:00:00',
            'lunch_out' => '2026-06-01 12:44:40',
            'time_out'  => '2026-06-01 17:00:00',
        ]);

        $this->assertSame(45, WorkDay::breakMinutes($row),
            'a 44 minute 40 second break was rounded down rather than to nearest');
    }

    /** Files without a flag still fall back to plain order. */
    public function test_unflagged_punches_still_work(): void
    {
        (new PunchImporter)->import([
            ['biometric_id' => $this->bio, 'timestamp' => '2026-07-04 08:00:00'],
            ['biometric_id' => $this->bio, 'timestamp' => '2026-07-04 12:00:00'],
            ['biometric_id' => $this->bio, 'timestamp' => '2026-07-04 13:00:00'],
            ['biometric_id' => $this->bio, 'timestamp' => '2026-07-04 17:00:00'],
        ]);

        $row = DB::table('hr_attendance')->where('employee_id', $this->employeeId)
            ->whereDate('date', '2026-07-04')->first();

        $this->assertSame('2026-07-04 12:00:00', $row->lunch_in);
        $this->assertSame('2026-07-04 17:00:00', $row->time_out);
    }

    // --------------------------------------------- the employee's own screen

    /**
     * Punches come from the scanner, not from a button. The dashboard shows
     * the day's six read-only; there is nothing to press that records a time
     * somebody was not physically at the scanner for.
     */
    public function test_employees_see_their_punches_but_cannot_record_them(): void
    {
        DB::table('hr_attendance')->insert([
            'employee_id' => $this->employeeId, 'date' => now()->toDateString(),
            'time_in' => now()->setTime(7, 58)->toDateTimeString(),
            'status' => 'present', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $user = \App\Models\User::find($this->userId);
        $html = \Livewire\Volt\Volt::actingAs($user)->test('employee.index')->html();

        $this->assertStringContainsString('07:58', $html, 'their first in is not shown');
        $this->assertStringNotContainsString('wire:click="punch"', $html);
        $this->assertStringNotContainsString('wire:click="clockIn"', $html);

        $attendance = \Livewire\Volt\Volt::actingAs($user)->test('employee.attendance')->html();
        $this->assertStringNotContainsString('Manual Time Out', $attendance);
        $this->assertStringNotContainsString('syncClockWithAttendance', $attendance);
    }

    /** A later export adds the afternoon without losing the morning. */
    public function test_a_second_export_adds_to_the_same_day(): void
    {
        (new PunchImporter)->import([
            ['biometric_id' => $this->bio, 'timestamp' => '2026-06-06 08:00:00'],
            ['biometric_id' => $this->bio, 'timestamp' => '2026-06-06 12:00:00'],
        ]);

        (new PunchImporter)->import([
            ['biometric_id' => $this->bio, 'timestamp' => '2026-06-06 13:00:00'],
            ['biometric_id' => $this->bio, 'timestamp' => '2026-06-06 17:00:00'],
        ]);

        $row = DB::table('hr_attendance')->where('employee_id', $this->employeeId)
            ->whereDate('date', '2026-06-06')->first();

        $this->assertSame('2026-06-06 08:00:00', $row->time_in, 'the morning was lost');
        $this->assertSame('2026-06-06 12:00:00', $row->lunch_in);
        $this->assertSame('2026-06-06 13:00:00', $row->lunch_out);
        $this->assertSame('2026-06-06 17:00:00', $row->time_out);
    }
}
