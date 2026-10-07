<?php

namespace App\Services;

use App\Support\ShiftSchedule;
use App\Support\WorkDay;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * A day worked as a whole different shift than the one scheduled is matched to
 * the scanner start, then measured against the correct duty length. Ordinary
 * employees get a nine-hour schedule span (eight paid hours plus lunch);
 * security guards get a twelve-hour post. Time beyond that is overtime, time
 * short of it is undertime.
 *
 * Only clear cases: the first punch rounds to an hour within thirty minutes.
 * Rest days and days HR corrected by hand are left alone.
 */
class ScheduleMatcher
{
    /** @return list<array{employee_id: int, name: string, date: string, was: string, now: string, worked: string}> */
    public function run(string $from, string $to, bool $apply = true, ?array $employeeIds = null): array
    {
        $changed = [];
        $staff = DB::table('employees as e')->join('users as u', 'u.user_id', '=', 'e.user_id')
            ->where('e.status', 'active')
            ->when($employeeIds, fn ($q) => $q->whereIn('e.employee_id', $employeeIds))
            ->get(['e.*', 'u.full_name']);

        foreach ($staff as $e) {
            $days = DB::table('hr_attendance')->where('employee_id', $e->employee_id)->whereBetween('date', [$from, $to])
                ->whereNotNull('time_in')->where('notes', 'From the biometric scanner')->get();
            foreach ($days as $a) {
                $date = substr((string) $a->date, 0, 10);
                $s = ShiftSchedule::forEmployeeDate($e, $date);
                if ($s['rest'] || ! $s['start'] || ! $s['end']) continue;

                $in = Carbon::parse($a->time_in);
                $out = WorkDay::effectiveOut($a);
                if (! $out || ! $out->greaterThan($in)) continue;

                $scheduledStart = Carbon::parse($date.' '.$s['start']);
                $scheduledEnd = Carbon::parse($date.' '.$s['end']);
                if ($scheduledEnd->lessThanOrEqualTo($scheduledStart)) $scheduledEnd->addDay();
                $actualOut = $out->lessThan($in) ? $out->copy()->addDay() : $out;

                // Coming in before the scheduled duty must not move the
                // schedule earlier. At one full hour or more it is overtime;
                // below one hour it is simply early, with no OT.
                if ($in->lt($scheduledStart)) continue;
                // One full hour after the scheduled duty is overtime territory,
                // not a reason to move the schedule and hide it.
                if ($actualOut->gt($scheduledEnd) && $scheduledEnd->diffInMinutes($actualOut) >= 60) continue;

                $hour = $in->copy()->second(0);
                if ($hour->minute >= 30) $hour->addHour();
                $hour->minute(0);
                if (abs($in->diffInMinutes($hour, false)) > 30) continue;
                $start = $hour->format('H:i');
                $length = ShiftSchedule::isGuard($e) ? 720 : 540;
                $end = $hour->copy()->addMinutes($length);
                if ($start === substr((string) $s['start'], 0, 5) && $end->format('H:i') === substr((string) $s['end'], 0, 5)) continue;

                $changed[] = ['employee_id' => (int) $e->employee_id, 'name' => $e->full_name, 'date' => $date,
                    'was' => substr((string) $s['start'], 0, 5).'-'.substr((string) $s['end'], 0, 5),
                    'now' => $start.'-'.$end->format('H:i'), 'worked' => $in->format('H:i').'-'.$out->format('H:i')];
                if ($apply) {
                    DB::table('shift_assignments')->updateOrInsert(['employee_id' => $e->employee_id, 'work_date' => $date], [
                        'starts_at' => $start.':00', 'ends_at' => $end->format('H:i').':00', 'rest_day' => false,
                        'label' => 'Matched to punches', 'status' => 'approved', 'approved_at' => now(),
                        'created_at' => now(), 'updated_at' => now()]);
                }
            }
        }

        if ($apply && $changed) {
            foreach (collect($changed)->pluck('employee_id')->unique() as $id) {
                app(PayrollRun::class)->recalculateOpen($id);
            }
            Auditor::record('update', 'shift_assignments', 0, null, ['matched_to_punches' => array_map(
                fn ($c) => $c['name'].' '.$c['date'].' '.$c['was'].' -> '.$c['now'], $changed)]);
        }

        return $changed;
    }
}
