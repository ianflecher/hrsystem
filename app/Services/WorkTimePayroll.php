<?php

namespace App\Services;

use App\Support\ShiftSchedule;
use App\Support\WorkDay;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Converts recorded attendance into payable ordinary time for daily/hourly
 * profiles. It intentionally does not calculate statutory premiums; those are
 * added by the existing holiday/OT/NSD services.
 */
class WorkTimePayroll
{
    public function forPeriod(object $employee, string $start, string $end): array
    {
        $attendance = DB::table('hr_attendance')->where('employee_id',$employee->employee_id)->whereBetween('date',[$start,$end])->get()->keyBy(fn($r)=>substr((string)$r->date,0,10));
        $leaves = DB::table('leaves')->where('employee_id',$employee->employee_id)->where('status','approved')->where('start_date','<=',$end)->where('end_date','>=',$start)->get();
        $workedDays=0; $paidLeaveDays=0; $hours=0.0; $penaltyHours=0.0; $last=Carbon::parse($end)->min(Carbon::today());
        for($d=Carbon::parse($start);$d->lte($last);$d->addDay()) {
            $date=$d->toDateString(); $shift=ShiftSchedule::forEmployeeDate($employee,$date);
            if (($shift['rest'] ?? false)) continue;
            $row=$attendance->get($date);
            if($row && $row->time_in){
                $workedDays++;
                if($effectiveOut = WorkDay::effectiveOut($row)){
                    $in=Carbon::parse($row->time_in); $out=$effectiveOut;
                    $capHours = ShiftSchedule::dailyCapMinutes($employee) / 60;
                    $break = ShiftSchedule::isGuard($employee) ? 0 : 60;

                    // A night duty is stored with its real morning time-out,
                    // so the span is already right. An older row that kept
                    // only the time of day still reads as the next morning.
                    if($out->lte($in)){
                        $hours += min($capHours, max(0, ($in->diffInMinutes($out->addDay()) - $break) / 60));
                    } elseif (! ShiftSchedule::isGuard($employee) && $shift['start'] && $shift['end']
                        && ! ShiftSchedule::isOvernight($shift['start'], $shift['end'])) {
                        // The company's late rule, in place of the minutes lost:
                        // up to 5 minutes is grace, 6-15 costs an hour and 16 or
                        // more half a day. So the day runs from the shift start,
                        // not the time in, and staying late does not make it up.
                        // Leaving early still costs what was not worked.
                        $from = Carbon::parse($date.' '.$shift['start']);
                        $to = Carbon::parse($date.' '.$shift['end']);
                        $late = max(0, (int) floor($from->diffInSeconds($in, false) / 60));
                        $penalty = $late <= 5 ? 0.0 : ($late <= 15 ? 1.0 : 4.0);
                        // No lunch hour off a morning that ended before lunch (8 to 12 is four hours).
                        $reach = max(0, $from->diffInMinutes($out->min($to), false));
                        $span = max(0, $reach - (WorkDay::leftBeforeLunch($row, (int) $reach) ? 0 : 60)) / 60;
                        $day = $span < (WorkDay::MINIMUM_PAID_MINUTES / 60)
                            ? 0.0
                            : min($capHours, $span);
                        $penaltyHours += min($day, $penalty);
                        // Whole hours, as the payslips count them: 6 h 01 m less lunch is 5.
                        $hours += max(0, floor($day + 0.0001) - $penalty);
                    } else {
                        // Deduct the fixed lunch hour, not the scanned lunch
                        // duration - and none for a guard, who stays on post.
                        $hours += WorkDay::workedHours($row, 60, $employee);
                    }
                }
                continue;
            }
            foreach($leaves as $leave){
                $paidLeave = $leave->leave_type !== 'unpaid'
                    && (! property_exists($leave, 'pay_status') || $leave->pay_status === null || $leave->pay_status === 'paid');
                if($paidLeave && substr((string)$leave->start_date,0,10)<=$date && substr((string)$leave->end_date,0,10)>=$date){$paidLeaveDays++;break;}
            }
        }
        return ['worked_days'=>$workedDays,'paid_leave_days'=>$paidLeaveDays,'hours'=>round($hours,2),'late_penalty_hours'=>round($penaltyHours,2)];
    }
}
