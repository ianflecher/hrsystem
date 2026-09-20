<?php

namespace App\Services;

use App\Support\ShiftSchedule;
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
        $workedDays=0; $paidLeaveDays=0; $hours=0.0; $last=Carbon::parse($end)->min(Carbon::today());
        for($d=Carbon::parse($start);$d->lte($last);$d->addDay()) {
            $date=$d->toDateString(); $shift=ShiftSchedule::forEmployeeDate($employee,$date);
            if (($shift['rest'] ?? false)) continue;
            $row=$attendance->get($date);
            if($row && $row->time_in){
                $workedDays++;
                if($row->time_out){
                    $in=Carbon::parse($row->time_in); $out=Carbon::parse($row->time_out);
                    if($out->lte($in)) $out->addDay();
                    $minutes=max(0,$in->diffInMinutes($out));
                    $break=(int)($shift['break_minutes'] ?? 0); $hours += max(0,($minutes-$break)/60);
                }
                continue;
            }
            foreach($leaves as $leave){
                if($leave->leave_type!=='unpaid' && substr((string)$leave->start_date,0,10)<=$date && substr((string)$leave->end_date,0,10)>=$date){$paidLeaveDays++;break;}
            }
        }
        return ['worked_days'=>$workedDays,'paid_leave_days'=>$paidLeaveDays,'hours'=>round($hours,2)];
    }
}
