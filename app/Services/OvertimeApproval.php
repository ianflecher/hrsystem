<?php

namespace App\Services;

use App\Support\PeopleAccess;
use Illuminate\Support\Facades\DB;

/**
 * Overtime is approved twice before it is paid: first by the employee's
 * supervisor or leader, then by Ma'am An Cansino (config/leave.php,
 * supervisor_approver_user_id). HR only sees the result; once she approves,
 * the amount goes to the next payroll.
 *
 * Statuses: pending (supervisor) -> pending_hr (Ma'am An) -> approved.
 * Her own team's overtime needs only her approval, and her own overtime is
 * approved by the HR supervisor (admin).
 */
class OvertimeApproval
{
    public static function finalApproverId(): int
    {
        return (int) config('leave.supervisor_approver_user_id');
    }

    public static function isFinalApprover(): bool
    {
        return auth()->check() && (int) auth()->id() === self::finalApproverId();
    }

    /** 'supervisor', 'final', or null when the signed-in user cannot decide this request. */
    public static function stage(object $row): ?string
    {
        $employee = DB::table('employees')->where('employee_id', $row->employee_id)->first();
        if (! $employee || ! auth()->check() || (int) $employee->user_id === (int) auth()->id()) {
            return null;
        }
        $hers = (int) $employee->user_id === self::finalApproverId();
        if (! in_array($row->status, ['pending', 'pending_hr'], true)) {
            return null;
        }
        if ($hers) {
            return PeopleAccess::canSeePay() ? 'final' : null;
        }
        if ($row->status === 'pending_hr') {
            return self::isFinalApprover() ? 'final' : null;
        }
        if (! PeopleAccess::managesEmployee((int) $row->employee_id)) {
            return null;
        }
        if (self::isFinalApprover()) {
            return 'final';
        }

        return in_array(auth()->user()->role, ['supervisor', 'leader'], true) ? 'supervisor' : null;
    }

    public static function decide(object $row, string $action, ?string $note, $amount = null): string
    {
        abort_unless(in_array($action, ['approve', 'reject'], true), 422);
        $stage = self::stage($row);
        abort_unless($stage, 403, 'This overtime is not yours to decide.');

        $update = ['updated_at' => now()];
        if ($stage === 'supervisor') {
            $update += ['manager_reviewed_by' => auth()->id(), 'manager_reviewed_at' => now(), 'manager_decision_note' => $note,
                'status' => $action === 'approve' ? 'pending_hr' : 'rejected'];
            $message = $action === 'approve' ? "Overtime sent to Ma'am An." : 'Overtime rejected.';
        } elseif ($action === 'reject') {
            $update += ['status' => 'rejected', 'reviewed_by' => auth()->id(), 'reviewed_at' => now(), 'decision_note' => $note];
            $message = 'Overtime rejected.';
        } else {
            $employee = DB::table('employees')->where('employee_id', $row->employee_id)->first();
            $suggestion = app(PhilippineOvertime::class)->suggest($employee, $row->starts_at, $row->ends_at);
            $approved = $amount !== null && $amount !== '' ? round((float) $amount, 2) : $suggestion['suggested_amount'];
            $update += ['status' => 'approved', 'approved_amount' => $approved, 'reviewed_by' => auth()->id(), 'reviewed_at' => now(),
                'decision_note' => trim(($note ?? '').' Approved at '.$suggestion['multiplier'].'x.')];
            $message = 'Overtime approved - it goes to the next payroll.';
        }
        DB::table('overtime_requests')->where('id', $row->id)->update($update);

        if (($update['status'] ?? null) === 'approved') {
            app(PayrollRun::class)->recalculateOpen((int) $row->employee_id);
        }

        return $message;
    }
}
