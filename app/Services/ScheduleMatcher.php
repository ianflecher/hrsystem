<?php

namespace App\Services;

use App\Support\ShiftSchedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * A day worked as a whole different shift than the one scheduled - in at
 * 5:58 and out at 15:01 on an 8-5 day - is that shift (6-3), not two hours
 * of undertime. Runs after every scanner sync, so future days fix themselves.
 *
 * Only clear cases: in within 10 minutes before (or 5 after) an hour that is
 * not the scheduled start, and out at that shift's end, give or take an hour.
 * A long day (6 AM to 8 PM) keeps its schedule and its overtime.
 * Guards, rest days and days HR corrected by hand are left alone.
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
            if (ShiftSchedule::isGuard($e)) continue;
            $days = DB::table('hr_attendance')->where('employee_id', $e->employee_id)->whereBetween('date', [$from, $to])
                ->whereNotNull('time_in')->whereNotNull('time_out')->where('notes', 'From the biometric scanner')->get();
            foreach ($days as $a) {
                $date = substr((string) $a->date, 0, 10);
                $s = ShiftSchedule::forEmployeeDate($e, $date);
                if ($s['rest'] || ! $s['start'] || ! $s['end']) continue;

                $in = Carbon::parse($a->time_in);
                $out = Carbon::parse($a->time_out);
                // The hour they started at: 5:50-6:05 is 6:00; 7:43 is no hour.
                $hour = $in->copy()->addMinutes(10)->startOfHour();
                if ($in->gt($hour->copy()->addMinutes(5))) continue;
                $start = $hour->format('H:i');
                if ($start === substr((string) $s['start'], 0, 5)) continue;

                // The scheduled shift's length, so a 12-hour day stays 12 hours.
                $length = Carbon::parse('2000-01-01 '.substr((string) $s['start'], 0, 5))
                    ->diffInMinutes(Carbon::parse('2000-01-01 '.substr((string) $s['end'], 0, 5)), false);
                if ($length <= 0) $length += 24 * 60;
                $end = $hour->copy()->addMinutes($length);
                // A full shift from that hour, and out at its end (give or take an hour).
                if ($out->lt($end->copy()->subMinutes(5)) || $out->gt($end->copy()->addHour())) continue;

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
