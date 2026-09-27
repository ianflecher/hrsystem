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

    // ------------------------------------------------ the employee's button

    /**
     * One button that names the punch it will make, advancing through the six.
     *
     * A row of six buttons would let somebody file their lunch as a final out
     * and end their day at noon; picking from a list is how a lunch becomes a
     * coffee break. What is already recorded decides what comes next.
     */
    public function test_the_button_walks_through_the_six_punches(): void
    {
        $user = \App\Models\User::find($this->userId);

        foreach (['First in', 'Lunch in', 'Lunch out', 'CB in', 'CB out', 'Final out'] as $label) {
            $page = \Livewire\Volt\Volt::actingAs($user)->test('employee.index');

            $this->assertSame($label, $page->instance()->nextPunchLabel(),
                "the button offered the wrong punch; expected {$label}");

            $page->call('punch');
        }

        // Six punches in, the day is done and the button is gone.
        $this->assertNull(
            \Livewire\Volt\Volt::actingAs($user)->test('employee.index')->instance()->nextPunchLabel(),
            'the day never completes',
        );

        $row = DB::table('hr_attendance')->where('employee_id', $this->employeeId)
            ->whereDate('date', now()->toDateString())->first();

        foreach (array_keys(WorkDay::PUNCHES) as $column) {
            $this->assertNotNull($row->{$column}, "{$column} was never recorded");
        }
    }

    /** A second tap does not skip ahead and close the day early. */
    public function test_punching_twice_does_not_jump_a_slot(): void
    {
        $user = \App\Models\User::find($this->userId);

        \Livewire\Volt\Volt::actingAs($user)->test('employee.index')->call('punch');
        \Livewire\Volt\Volt::actingAs($user)->test('employee.index')->call('punch');

        $row = DB::table('hr_attendance')->where('employee_id', $this->employeeId)
            ->whereDate('date', now()->toDateString())->first();

        $this->assertNotNull($row->time_in);
        $this->assertNotNull($row->lunch_in);
        $this->assertNull($row->time_out, 'two taps ended the day');
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
