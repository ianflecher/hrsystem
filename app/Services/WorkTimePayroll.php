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

                    // A shift that ends past midnight: the final out belongs to
                    // the next day, and WorkDay reads the columns as stored.
                    if($out->lte($in)){
                        $hours += max(0, ($in->diffInMinutes($out->addDay()) - (int)($shift['break_minutes'] ?? 0)) / 60);
                    } else {
                        // The break that was punched, or the shift's assumed one
                        // when nobody punched it. A fixed deduction applied to
                        // somebody who worked through their lunch takes an hour
                        // off a day they spent at the machine.
                        $hours += \App\Support\WorkDay::workedHours($row, (int)($shift['break_minutes'] ?? 0));
                    }
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
