<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * What a review is made of, and how a notice moves.
 *
 * The attendance half is not typed in by anybody: the scanner and payroll
 * already know who was late, who left early and who did not come in, so a
 * review reads that record rather than asking somebody to remember it.
 *
 * The other half is the notices. A Notice to Explain is served, the employee
 * answers it in writing, and HR records a decision - the twin-notice rule, in
 * the order the law expects it: nobody is disciplined before they have been
 * asked, and asked in writing.
 *
 * The grade is a person's judgement, not a formula. Nothing here computes one:
 * somebody whose numbers look poor may have had a reason the numbers cannot
 * hold, and a grade nobody chose is a grade nobody can explain.
 */
class ReviewSummary
{
    /** The two notices, in the order they are allowed to happen. */
    public const KINDS = [
        'nte' => 'Notice to Explain',
        'not' => 'Notice of Termination',
    ];

    /** Days an employee has to answer a notice, unless HR sets another date. */
    public const DAYS_TO_RESPOND = 5;

    /** What a notice can be about. */
    public const TYPES = [
        'lateness'      => 'Habitual lateness',
        'absence'       => 'Absence without leave',
        'undertime'     => 'Undertime',
        'conduct'       => 'Conduct',
        'safety'        => 'Safety',
        'quality'       => 'Work quality',
        'insubordination' => 'Insubordination',
        'other'         => 'Other',
    ];

    /** What HR can decide once the explanation is in. */
    public const DECISIONS = [
        'dismissed'    => 'No further action',
        'verbal'       => 'Verbal warning',
        'written'      => 'Written warning',
        'final'        => 'Final warning',
        'suspension'   => 'Suspension',
        'termination'  => 'Termination',
    ];

    /** Where a notice has got to. */
    public const STATUSES = [
        'issued'    => 'Awaiting your explanation',
        'explained' => 'Explanation received',
        'closed'    => 'Closed',
    ];

    /** The grades, best to worst. */
    public const GRADES = [
        5 => 'Excellent',
        4 => 'Very good',
        3 => 'Satisfactory',
        2 => 'Needs improvement',
        1 => 'Unsatisfactory',
    ];

    /**
     * The attendance record for a review period.
     *
     * @return array{days: int, late: int, undertime: int, absent: int, leave: int, onTime: int}
     */
    public function attendance(object $employee, string $from, string $to): array
    {
        $time = (new TimeDeductions)->forPeriod($employee, $from, $to);

        $worked = DB::table('hr_attendance')->where('employee_id', $employee->employee_id)
            ->whereBetween('date', [$from, $to])->whereNotNull('time_in')->count();

        return [
            'days'      => $worked,
            'late'      => $time['lateDays'],
            'undertime' => $time['undertimeDays'],
            'absent'    => $time['absentDays'],
            'leave'     => $time['leaveDays'] + $time['unpaidLeaveDays'],
            'onTime'    => max(0, $worked - $time['lateDays']),
        ];
    }

    /** Any Notice of Termination already issued from these notices, by parent. */
    public function terminationsFor(iterable $notices)
    {
        $ids = collect($notices)->pluck('id');

        return DB::table('employee_notices')->where('kind', 'not')->whereIn('parent_id', $ids)
            ->get()->keyBy('parent_id');
    }

    /** Notices served on somebody during a period, newest first. */
    public function notices(int $employeeId, ?string $from = null, ?string $to = null)
    {
        return DB::table('employee_notices as n')
            ->leftJoin('users as i', 'i.user_id', '=', 'n.issued_by')
            ->leftJoin('users as d', 'd.user_id', '=', 'n.decided_by')
            ->where('n.employee_id', $employeeId)
            ->when($from && $to, fn ($q) => $q->whereBetween('n.occurred_on', [$from, $to]))
            ->orderByDesc('n.occurred_on')
            ->select('n.*', 'i.full_name as issued_by_name', 'd.full_name as decided_by_name')
            ->get();
    }

    /** Notices still waiting on the person to answer them. */
    public function awaiting(int $employeeId)
    {
        return $this->notices($employeeId)->where('status', 'issued')->values();
    }

    public static function grade(?int $rating): string
    {
        return self::GRADES[$rating] ?? 'Not graded';
    }

    /**
     * Whether a Notice of Termination may be issued from this notice.
     *
     * Only from a closed Notice to Explain that was decided as termination:
     * the explanation has to have been asked for and heard first, which is the
     * whole point of the two notices. One per case.
     */
    public static function canTerminate(object $notice, bool $alreadyIssued = false): bool
    {
        return ! $alreadyIssued
            && $notice->kind === 'nte'
            && $notice->status === 'closed'
            && $notice->decision === 'termination';
    }

    public static function decision(?string $decision): string
    {
        return self::DECISIONS[$decision] ?? 'No decision recorded';
    }

    /** Past the date they were given to answer, and still silent. */
    public static function isOverdue(object $notice): bool
    {
        return $notice->status === 'issued'
            && Carbon::parse($notice->respond_by)->lt(today());
    }
}
