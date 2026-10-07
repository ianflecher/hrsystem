<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Overtime is time outside the day's shift. Approved overtime can end up
 * inside it when the schedule changes afterwards (a 6-10 PM request filed
 * against a 9-6 default, then the shift set to 1-10). Those hours are already
 * paid as basic, so payroll pays only the part outside the shift - checked
 * against the schedule when the payslip is worked out, not when it was filed.
 */
class OvertimePayable
{
    /**
     * @return array{minutes: int, amount: float, trimmed: int}
     */
    public static function for(object $employee, object $request): array
    {
        $from = Carbon::parse($request->starts_at);
        $to = Carbon::parse($request->ends_at);
        $minutes = max(0, (int) $request->minutes);
        $amount = (float) $request->approved_amount;
        // The amount as worked out, unrounded, when HR kept the suggestion - so the
        // cutoff total is rounded once (2 h x 79.375 = 158.75, not 79.38 + 79.38).
        // An amount HR typed in themselves is used as it is.
        try {
            $suggestion = app(\App\Services\PhilippineOvertime::class)->suggest($employee, (string) $request->starts_at, (string) $request->ends_at);
            if (abs($amount - (float) $suggestion['suggested_amount']) <= 0.011 && isset($suggestion['exact_amount'])) {
                $amount = (float) $suggestion['exact_amount'];
            }
        } catch (\Throwable) {
        }

        $shift = ShiftSchedule::forEmployeeDate($employee, $from->toDateString());
        if ($shift['rest'] || ! $shift['start'] || ! $shift['end'] || $minutes === 0) {
            return ['minutes' => $minutes, 'amount' => $amount, 'trimmed' => 0];
        }

        $start = Carbon::parse($from->toDateString().' '.substr((string) $shift['start'], 0, 8));
        $end = Carbon::parse($from->toDateString().' '.substr((string) $shift['end'], 0, 8));
        if ($end->lte($start)) $end->addDay();

        $overlap = (int) round(max(0, min($to->timestamp, $end->timestamp) - max($from->timestamp, $start->timestamp)) / 60);
        $overlap = min($overlap, $minutes);
        if ($overlap === 0) {
            return ['minutes' => $minutes, 'amount' => $amount, 'trimmed' => 0];
        }

        $left = $minutes - $overlap;

        return ['minutes' => $left, 'amount' => $amount * $left / $minutes, 'trimmed' => $overlap];
    }
}
