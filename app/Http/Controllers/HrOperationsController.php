<?php

namespace App\Http\Controllers;

use App\Services\PayrollOperations;
use App\Support\PayPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Support\SecurityAudit;

class HrOperationsController extends Controller
{
    public function payrollControl(Request $request)
    {
        \App\Support\PeopleAccess::pay();
        $period = PayPeriod::fromStart($request->get('period', PayPeriod::recent(1)[0]->start));
        app(PayrollOperations::class)->refreshExceptions($period);
        $exceptions = DB::table('payroll_exceptions as x')->leftJoin('employees as e', 'x.employee_id', '=', 'e.employee_id')->leftJoin('users as u', 'e.user_id', '=', 'u.user_id')->where('x.period_start', $period->start)->where('x.resolved', false)->select('x.*', 'u.full_name')->orderByRaw("FIELD(x.severity, 'high','medium','low')")->orderBy('u.full_name')->get();
        $totals = DB::table('hr_payroll')->where('period_start', $period->start)->selectRaw('COUNT(*) employees, COALESCE(SUM(gross_pay),0) gross, COALESCE(SUM(deductions),0) deductions, COALESCE(SUM(net_pay),0) net, COALESCE(SUM(employer_total_cost),0) employer_cost')->first();
        $anomalies = Schema::hasTable('payroll_anomalies') ? DB::table('payroll_anomalies as a')->leftJoin('employees as e','a.employee_id','=','e.employee_id')->leftJoin('users as u','e.user_id','=','u.user_id')->where('a.period_start',$period->start)->where('a.resolved',false)->select('a.*','u.full_name')->orderByRaw("FIELD(a.severity, 'high','medium','low')")->get() : collect();
        return view('hr.operations.payroll-control', compact('period', 'exceptions', 'anomalies', 'totals'));
    }

    public function resolveAnomaly(int $id)
    {
        \App\Support\PeopleAccess::hr();
        abort_unless(Schema::hasTable('payroll_anomalies'), 404);
        DB::table('payroll_anomalies')->where('id', $id)->where('resolved', false)->update(['resolved' => true, 'resolved_by' => auth()->id(), 'resolved_at' => now(), 'updated_at' => now()]);
        return back()->with('success', 'Payroll anomaly resolved.');
    }

    public function resolveException(int $id)
    {
        \App\Support\PeopleAccess::hr();
        DB::table('payroll_exceptions')->where('id', $id)->update(['resolved' => true, 'resolved_by' => auth()->id(), 'resolved_at' => now(), 'updated_at' => now()]);
        return back()->with('success', 'Payroll exception resolved.');
    }

    public function employee(int $id)
    {
        $employee = DB::table('employees as e')->join('users as u', 'e.user_id', '=', 'u.user_id')->leftJoin('departments as d', 'e.department_id', '=', 'd.department_id')->where('e.employee_id', $id)->select('e.*', 'u.full_name', 'u.email', 'd.department_name')->first();
        abort_unless($employee, 404);
        $teamDepartmentIds = \App\Support\PeopleAccess::teamDashboardDepartmentIds();
        // HR opens anybody; a supervisor or leader only their own team.
        if (! \App\Support\PeopleAccess::isHr() && request()->routeIs('hr.operations.employee', 'employee.team.employee') && $teamDepartmentIds !== []) {
            abort_unless(in_array((int) $employee->department_id, $teamDepartmentIds, true), 403);
        }
        \App\Support\PeopleAccess::managerForEmployee($id);
        $payroll = \App\Support\PeopleAccess::canSeePay()
            ? DB::table('hr_payroll')->where('employee_id', $id)->orderByDesc('period_end')->limit(12)->get()
            : collect();
        $attendance = DB::table('hr_attendance')->where('employee_id', $id)->orderByDesc('date')->limit(30)->get();
        $leave = DB::table('leaves')->where('employee_id', $id)->orderByDesc('start_date')->limit(20)->get();
        $loans = \App\Support\PeopleAccess::canSeePay() ? DB::table('employee_loans')->where('employee_id', $id)->orderByDesc('id')->get() : collect();
        $documents = \App\Support\PeopleAccess::isHr() ? DB::table('employee_documents')->where('employee_id', $id)->orderByDesc('id')->get() : collect();
        $salaryHistory = \App\Support\PeopleAccess::canSeePay() ? DB::table('employee_salary_history')->where('employee_id', $id)->orderByDesc('effective_from')->get() : collect();
        return view('hr.operations.employee-360', compact('employee', 'payroll', 'attendance', 'leave', 'loans', 'documents', 'salaryHistory'));
    }

    public function manager()
    {
        \App\Support\PeopleAccess::manager();
        $departmentIds = \App\Support\PeopleAccess::teamDashboardDepartmentIds();
        $employees = DB::table('employees as e')
            ->join('users as u', 'e.user_id', '=', 'u.user_id')
            ->leftJoin('departments as d', 'e.department_id', '=', 'd.department_id')
            ->where('e.status', 'active')
            ->when(\App\Support\PeopleAccess::isHr(), fn ($q) => $q->whereIn('e.department_id', $departmentIds))
            ->when(! \App\Support\PeopleAccess::isHr(), fn($q) => \App\Support\PeopleAccess::scopeTeam($q)->where('e.user_id', '!=', auth()->id())
                // Nobody opens their own profile here, and a leader does not open their supervisor's.
                ->when(auth()->user()->role === 'leader', fn($q) => $q->where('u.role', '!=', 'supervisor')))
            ->select('e.employee_id','e.employee_no','e.job_title','e.department_id','e.pay_basis','u.full_name','d.department_name','e.shift_start','e.shift_end','e.rest_days')
            ->orderBy('u.full_name')
            ->get();
        // This cutoff's rest days per person: a calendar mark wins over the weekly default.
        $cutoff = \App\Support\PayPeriod::recent(1)[0];
        $marks = DB::table('shift_assignments')->whereIn('employee_id', $employees->pluck('employee_id'))
            ->whereBetween('work_date', [$cutoff->start, $cutoff->end])->get()
            ->groupBy('employee_id');
        foreach ($employees as $e) {
            $own = ($marks[$e->employee_id] ?? collect())->keyBy(fn ($m) => substr((string) $m->work_date, 0, 10));
            $e->cutoffRest = [];
            for ($day = \Carbon\Carbon::parse($cutoff->start); $day->lte(\Carbon\Carbon::parse($cutoff->end)); $day->addDay()) {
                $mark = $own->get($day->toDateString());
                $e->cutoffRest[$day->toDateString()] = $mark ? (bool) $mark->rest_day : \App\Support\WorkWeek::restsOn($e->rest_days, $day);
            }
        }
        $teamWorked = DB::table('hr_attendance')
            ->whereIn('employee_id', $employees->pluck('employee_id'))
            ->whereBetween('date', [$cutoff->start, $cutoff->end])
            ->get()
            ->keyBy(fn ($a) => $a->employee_id.'|'.substr((string) $a->date, 0, 10));
        $teamLeaves = collect();
        DB::table('leaves')
            ->whereIn('employee_id', $employees->pluck('employee_id'))
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $cutoff->end)
            ->whereDate('end_date', '>=', $cutoff->start)
            ->get()
            ->each(function ($leave) use ($cutoff, $teamLeaves) {
                $start = \Carbon\Carbon::parse(max((string) $leave->start_date, $cutoff->start));
                $end = \Carbon\Carbon::parse(min((string) $leave->end_date, $cutoff->end));
                for ($day = $start; $day->lte($end); $day->addDay()) {
                    $teamLeaves->put($leave->employee_id.'|'.$day->toDateString(), $leave);
                }
            });
        $pendingLeave = DB::table('leaves as l')->join('employees as e','l.employee_id','=','e.employee_id')->join('users as u','e.user_id','=','u.user_id')
            // HR sees everything waiting, to know - not to decide.
            ->when(\App\Support\PeopleAccess::isHr(), fn($q) => $q->whereIn('l.status', ['pending', 'pending_hr'])->whereIn('e.department_id', $departmentIds))
            // A team's staff for its supervisor or leader; supervisors' and
            // leaders' leave, and everything already sent on, for Ma'am An.
            ->when(! \App\Support\PeopleAccess::isHr(), fn($q)=>$q->where('e.user_id','!=',auth()->id())
                ->where(fn($q) => $q->where(fn($q) => \App\Support\PeopleAccess::scopeTeam($q->where('l.status', 'pending'))
                        ->when(! \App\Support\PeopleAccess::isOperationsSupervisor(), fn($q) => $q->whereNotIn('u.role', ['supervisor', 'leader'])))
                    ->when(\App\Support\PeopleAccess::isOperationsSupervisor(), fn($q) => $q->orWhere('l.status', 'pending_hr'))))
            ->select('l.*','u.full_name')->orderBy('l.start_date')->get();
        $leaveBalances = app(\App\Services\LeaveBalances::class);
        foreach ($pendingLeave as $leave) {
            $year = (int) substr((string) $leave->start_date, 0, 4);
            $balance = $leaveBalances->forEmployee((int) $leave->employee_id, $year)[$leave->leave_type] ?? null;
            $leave->paid_balance = $balance;
            $leave->paid_used = $balance ? (float) ($balance['used'] ?? 0) : null;
            $leave->paid_pending = $balance ? (float) ($balance['pending'] ?? 0) : null;
            $leave->paid_remaining = $balance ? $balance['remaining'] : null;
            $leave->paid_entitled = $balance ? $balance['entitled'] : null;
        }
        $pendingOt = DB::table('overtime_requests as o')->join('employees as e','o.employee_id','=','e.employee_id')->join('users as u','e.user_id','=','u.user_id')
            ->when(\App\Support\PeopleAccess::isHr(), fn($q) => $q->whereIn('o.status', ['pending', 'pending_hr'])->whereIn('e.department_id', $departmentIds))
            ->when(! \App\Support\PeopleAccess::isHr(), fn($q)=>$q->where('e.user_id','!=',auth()->id())
                ->where(fn($q) => $q->where(fn($q) => \App\Support\PeopleAccess::scopeTeam($q->where('o.status', 'pending'))
                        ->when(auth()->user()->role === 'leader', fn($q) => $q->where('u.role', '!=', 'supervisor')))
                    ->when(\App\Services\OvertimeApproval::isFinalApprover(), fn($q) => $q->orWhere('o.status', 'pending_hr'))))
            ->select('o.*','u.full_name')->orderBy('o.starts_at')->get();
        // Team members the scanner cannot see yet: their days are entered here.
        $noScanner = DB::table('employees as e')->join('users as u', 'e.user_id', '=', 'u.user_id')
            ->where('e.status', 'active')->where(fn ($q) => $q->whereNull('e.biometric_id')->orWhere('e.biometric_id', ''))
            ->when(\App\Support\PeopleAccess::isHr(), fn ($q) => $q->whereIn('e.department_id', $departmentIds))
            ->when(! \App\Support\PeopleAccess::isHr(), fn ($q) => \App\Support\PeopleAccess::scopeTeam($q)->where('e.user_id', '!=', auth()->id()))
            ->orderBy('u.full_name')->get(['e.employee_id', 'e.employee_no', 'u.full_name']);
        $recentManual = DB::table('hr_attendance as a')->join('employees as e', 'e.employee_id', '=', 'a.employee_id')->join('users as u', 'u.user_id', '=', 'e.user_id')
            ->whereIn('a.employee_id', $noScanner->pluck('employee_id'))->where('a.date', '>=', now()->subDays(20)->toDateString())
            ->orderByDesc('a.date')->orderBy('u.full_name')->limit(40)->get(['a.*', 'u.full_name']);

        $pendingTimeLogs = DB::table('time_log_requests as t')->join('employees as e', 'e.employee_id', '=', 't.employee_id')->join('users as u', 'u.user_id', '=', 'e.user_id')
            ->where('t.status', 'pending')
            ->when(\App\Support\PeopleAccess::isHr(), fn ($q) => $q->whereIn('e.department_id', $departmentIds))
            ->when(! \App\Support\PeopleAccess::isHr(), fn ($q) => \App\Support\PeopleAccess::scopeTeam($q)->where('e.user_id', '!=', auth()->id()))
            ->orderBy('t.date')->get(['t.*', 'u.full_name']);

        return view('hr.operations.manager', compact('cutoff','employees','teamWorked','teamLeaves','pendingLeave','pendingOt','noScanner','recentManual','pendingTimeLogs'));
    }

    /**
     * An employee's own time log, approved or rejected. Approved, it becomes
     * the day's attendance - the same as if the supervisor had entered it.
     */
    public function timeLogDecision(Request $request, int $id)
    {
        \App\Support\PeopleAccess::manager();
        $log = DB::table('time_log_requests')->where('id', $id)->first();
        abort_unless($log, 404);
        abort_unless($log->status === 'pending', 422, 'This time log has already been decided.');
        \App\Support\PeopleAccess::managerForEmployee((int) $log->employee_id);
        $data = $request->validate(['action' => 'required|in:approve,reject', 'review_note' => 'nullable|string|max:255']);

        DB::transaction(function () use ($log, $data) {
            DB::table('time_log_requests')->where('id', $log->id)->update([
                'status' => $data['action'] === 'approve' ? 'approved' : 'rejected',
                'reviewed_by' => auth()->id(), 'reviewed_at' => now(),
                'review_note' => $data['review_note'] ?? null, 'updated_at' => now(),
            ]);

            if ($data['action'] === 'approve') {
                $employee = DB::table('employees')->where('employee_id', $log->employee_id)->first();
                $shift = \App\Support\ShiftSchedule::forEmployeeDate($employee, (string) $log->date);
                $in = \Carbon\Carbon::parse($log->time_in);
                $late = $shift['start'] && \App\Support\Tardiness::isLate($in, $shift['start']);
                DB::table('hr_attendance')->updateOrInsert(
                    ['employee_id' => $log->employee_id, 'date' => substr((string) $log->date, 0, 10)],
                    ['time_in' => $log->time_in, 'time_out' => $log->time_out, 'status' => $late ? 'late' : 'present',
                        'notes' => 'Time log approved by '.auth()->user()->full_name.' (no scanner ID)',
                        'created_at' => now(), 'updated_at' => now()]
                );
            }
        });

        \App\Services\Auditor::record('update', 'time_log_requests', $log->id, ['status' => 'pending'], ['status' => $data['action']]);

        return back()->with('success', 'Time log '.($data['action'] === 'approve' ? 'approved.' : 'rejected.'));
    }

    /**
     * A day for somebody the scanner cannot record - seasonal staff not yet
     * enrolled. The supervisor enters the shift and the times worked; a person
     * who has a scanner ID is not offered here, so nothing the device recorded
     * can be written over. The note says who entered it.
     */
    public function manualAttendance(Request $request)
    {
        \App\Support\PeopleAccess::manager();
        $data = $request->validate([
            'employee_id' => 'required|integer|exists:employees,employee_id',
            'date' => 'required|date_format:Y-m-d|before_or_equal:today',
            'shift_start' => 'required|date_format:H:i',
            'shift_end' => 'required|date_format:H:i',
            'time_in' => 'nullable|date_format:H:i',
            'time_out' => 'nullable|date_format:H:i',
        ], ['date.before_or_equal' => 'Times can only be entered for today or earlier.']);

        \App\Support\PeopleAccess::managerForEmployee((int) $data['employee_id']);
        $employee = DB::table('employees')->where('employee_id', $data['employee_id'])->first();
        abort_if(trim((string) $employee->biometric_id) !== '', 422, 'This person has a scanner ID - their times come from the scanner.');

        // Times on the clock; a time-out earlier than the time-in is the next morning.
        $at = fn ($time) => $time ? \Carbon\Carbon::parse($data['date'].' '.$time) : null;
        $in = $at($data['time_in'] ?? null);
        $out = $at($data['time_out'] ?? null);
        if ($in && $out && $out->lte($in)) {
            $out->addDay();
        }

        DB::transaction(function () use ($data, $in, $out) {
            DB::table('shift_assignments')->updateOrInsert(
                ['employee_id' => $data['employee_id'], 'work_date' => $data['date']],
                ['starts_at' => $data['shift_start'], 'ends_at' => $data['shift_end'], 'rest_day' => false,
                    'label' => 'Assigned shift', 'status' => 'approved', 'created_by' => auth()->id(),
                    'approved_by' => auth()->id(), 'approved_at' => now(), 'created_at' => now(), 'updated_at' => now()]
            );

            if ($in || $out) {
                $late = $in && \App\Support\Tardiness::isLate($in, $data['shift_start']);
                DB::table('hr_attendance')->updateOrInsert(
                    ['employee_id' => $data['employee_id'], 'date' => $data['date']],
                    ['time_in' => $in?->toDateTimeString(), 'time_out' => $out?->toDateTimeString(),
                        'status' => $late ? 'late' : 'present',
                        'notes' => 'Entered by '.auth()->user()->full_name.' (no scanner ID)',
                        'created_at' => now(), 'updated_at' => now()]
                );
            }
        });

        \App\Services\Auditor::record('update', 'hr_attendance', 0, null,
            ['manual_attendance' => $data, 'by' => auth()->id()]);

        return back()->with('success', 'Saved for '.\Carbon\Carbon::parse($data['date'])->format('M j').'.');
    }

    public function managerLeaveDecision(Request $request, int $id)
    {
        \App\Support\PeopleAccess::manager();
        $leave = DB::table('leaves')->where('leave_id', $id)->first();
        abort_unless($leave, 404);
        // Leave: the team's supervisor or leader first, then Ma'am An, whose
        // approval is final. Supervisors' and leaders' leave is hers alone. HR only sees it.
        $ops = \App\Support\PeopleAccess::isOperationsSupervisor();
        $final = $ops && ($leave->status === 'pending_hr' || \App\Support\PeopleAccess::decidesLeaveOf((int) $leave->employee_id));
        abort_unless($final || ($leave->status === 'pending' && \App\Support\PeopleAccess::decidesLeaveOf((int) $leave->employee_id)), 403);
        abort_if($ops && (int) DB::table('employees')->where('employee_id', $leave->employee_id)->value('user_id') === (int) auth()->id(), 403);

        $action = $request->input('action');
        abort_unless(in_array($action, ['approve', 'reject'], true) && in_array($leave->status, $final ? ['pending', 'pending_hr'] : ['pending'], true), 422);
        $data = $request->validate(['note' => ($action === 'reject' ? 'required' : 'nullable').'|string|max:2000']);

        if ($final && $action === 'approve') {
            $verdict = (new \App\Services\LeaveBalances)->canApprove($leave);
            if (! $verdict['ok']) {
                return back()->with('error', 'Not approved. '.$verdict['reason']);
            }
            DB::table('leaves')->where('leave_id', $id)->update([
                'status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => now(),
                'manager_reviewed_by' => $leave->manager_reviewed_by ?? auth()->id(),
                'manager_reviewed_at' => $leave->manager_reviewed_at ?? now(),
                'manager_decision_note' => $data['note'] ?? $leave->manager_decision_note,
                'updated_at' => now(),
            ]);
            app(\App\Services\PayrollRun::class)->recalculateOpen((int) $leave->employee_id);

            return back()->with('success', 'Leave approved.');
        }

        DB::table('leaves')->where('leave_id', $id)->update([
            'status' => $action === 'approve' ? 'pending_hr' : 'rejected',
            'manager_reviewed_by' => auth()->id(),
            'manager_reviewed_at' => now(),
            'manager_decision_note' => $data['note'] ?? null,
            'rejection_reason' => $action === 'reject' ? ($data['note'] ?? null) : $leave->rejection_reason,
            'updated_at' => now(),
        ]);

        return back()->with('success', $action === 'approve' ? "Leave sent to Ma'am An." : 'Leave rejected.');
    }

    public function managerOvertimeDecision(Request $request, int $id)
    {
        \App\Support\PeopleAccess::manager();
        $row = DB::table('overtime_requests')->where('id', $id)->first();
        abort_unless($row, 404);
        $action = (string) $request->input('action');
        $data = $request->validate(['note' => ($action === 'reject' ? 'required' : 'nullable').'|string|max:2000']);

        return back()->with('success', \App\Services\OvertimeApproval::decide($row, $action, $data['note'] ?? null));
    }

    public function inbox()
    {
        \App\Support\PeopleAccess::hr();
        $requests = DB::table('employee_requests as r')->join('employees as e', 'r.employee_id', '=', 'e.employee_id')->join('users as u', 'e.user_id', '=', 'u.user_id')->where('r.status', 'pending')->select('r.*', 'u.full_name')->orderBy('r.created_at')->get();
        $overtime = DB::table('overtime_requests as o')->join('employees as e', 'o.employee_id', '=', 'e.employee_id')->join('users as u', 'e.user_id', '=', 'u.user_id')->where('o.status', 'pending_hr')->select('o.*', 'u.full_name')->orderBy('o.starts_at')->get();
        return view('hr.operations.inbox', compact('requests', 'overtime'));
    }

    public function approvalCenter()
    {
        \App\Support\PeopleAccess::hr();
        $leave = DB::table('leaves as l')->join('employees as e','l.employee_id','=','e.employee_id')->join('users as u','e.user_id','=','u.user_id')->where('l.status','pending_hr')->select('l.leave_id as id','l.employee_id','l.start_date','l.end_date','l.reason','u.full_name')->orderBy('l.start_date')->get();
        $overtime = DB::table('overtime_requests as o')->join('employees as e','o.employee_id','=','e.employee_id')->join('users as u','e.user_id','=','u.user_id')->where('o.status','pending_hr')->select('o.id','o.employee_id','o.starts_at','o.ends_at','o.minutes','o.reason','u.full_name')->orderBy('o.starts_at')->get();
        $attendance = Schema::hasTable('attendance_corrections') ? DB::table('attendance_corrections as a')->join('employees as e','a.employee_id','=','e.employee_id')->join('users as u','e.user_id','=','u.user_id')->where('a.status','pending')->select('a.*','u.full_name')->orderBy('a.attendance_date')->get() : collect();
        $requests = DB::table('employee_requests as r')->join('employees as e','r.employee_id','=','e.employee_id')->join('users as u','e.user_id','=','u.user_id')->where('r.status','pending')->select('r.*','u.full_name')->orderBy('r.created_at')->get();
        return view('hr.operations.approval-center', compact('leave','overtime','attendance','requests'));
    }

    public function requestDecision(Request $request, int $id)
    {
        \App\Support\PeopleAccess::hr();
        $status = in_array($request->input('status'), ['approved', 'rejected'], true) ? $request->input('status') : 'rejected';
        DB::table('employee_requests')->where('id', $id)->where('status', 'pending')->update(['status' => $status, 'reviewed_by' => auth()->id(), 'reviewed_at' => now(), 'review_note' => $request->input('review_note'), 'updated_at' => now()]);
        SecurityAudit::record('approval.employee_request', $status.' request #'.$id);
        if (Schema::hasTable('approval_actions')) { DB::table('approval_actions')->insert(['category'=>'employee_request','reference_type'=>'employee_request','reference_id'=>$id,'action'=>$status,'acted_by'=>auth()->id(),'notes'=>$request->input('review_note'),'ip_address'=>$request->ip(),'created_at'=>now(),'updated_at'=>now()]); }
        return back()->with('success', 'Request updated.');
    }
    public function payrollApproval(Request $request)
    {
        \App\Support\PeopleAccess::pay();
        $period = PayPeriod::fromStart($request->get('period', PayPeriod::recent(1)[0]->start));
        $control = app(\App\Services\PayrollControlCenter::class)->control($period);
        $summary = app(\App\Services\PayrollControlCenter::class)->summary($period);
        return view('hr.operations.payroll-approval', compact('period','control','summary'));
    }

    public function approvePayroll(Request $request)
    {
        \App\Support\PeopleAccess::pay();
        try { $period=PayPeriod::fromStart($request->validate(['period'=>'required|date'])['period']); app(\App\Services\PayrollControlCenter::class)->approve($period); SecurityAudit::record('payroll.approved', $period->start.' to '.$period->end); return back()->with('success','Payroll approved.'); }
        catch (\Throwable $e) { return back()->with('error',$e->getMessage()); }
    }

    public function paidPayroll(Request $request)
    {
        \App\Support\PeopleAccess::pay();
        try { $period=PayPeriod::fromStart($request->validate(['period'=>'required|date'])['period']); app(\App\Services\PayrollControlCenter::class)->markPaid($period); SecurityAudit::record('payroll.paid', $period->start.' to '.$period->end); return back()->with('success','Payroll marked paid and locked.'); }
        catch (\Throwable $e) { return back()->with('error',$e->getMessage()); }
    }

    public function adminCenter()
    {
        \App\Support\PeopleAccess::hr();
        $settings = Schema::hasTable('hr_settings') ? DB::table('hr_settings')->orderBy('setting_group')->orderBy('setting_key')->get() : collect();
        $counts = $settings->groupBy('setting_group')->map->count();
        $groups = $counts->keys()->merge(['payroll','attendance','leave','notifications','organization'])->unique()->values();
        return view('hr.operations.admin-center', compact('settings','counts','groups'));
    }

    public function analytics()
    {
        \App\Support\PeopleAccess::hr();
        $today = now()->toDateString();
        $stats = [
            'active' => DB::table('employees')->where('status','active')->count(),
            'new_hires_30' => DB::table('employees')->where('hire_date','>=',now()->subDays(30)->toDateString())->count(),
            'separations_30' => Schema::hasTable('employee_separations') ? DB::table('employee_separations')->where('separation_date','>=',now()->subDays(30)->toDateString())->count() : 0,
            'attendance_today' => DB::table('hr_attendance')->whereDate('date',$today)->count(),
            'late_today' => DB::table('hr_attendance')->whereDate('date',$today)->where('status','late')->count(),
            'pending_leave' => DB::table('leaves')->where('status','pending_hr')->count(),
            'pending_ot' => DB::table('overtime_requests')->where('status','pending_hr')->count(),
            'payroll_cost' => (float) DB::table('hr_payroll')->whereMonth('period_end',now()->month)->whereYear('period_end',now()->year)->sum('employer_total_cost'),
        ];
        $pay = \App\Support\PeopleAccess::canSeePay();
        if (! $pay) $stats['payroll_cost'] = null;
        $departmentCosts = ! $pay ? collect() : DB::table('hr_payroll as p')->join('employees as e','p.employee_id','=','e.employee_id')->leftJoin('departments as d','e.department_id','=','d.department_id')->whereMonth('p.period_end',now()->month)->whereYear('p.period_end',now()->year)->groupBy('d.department_name')->selectRaw("COALESCE(d.department_name,'Unassigned') department, SUM(p.employer_total_cost) cost, COUNT(DISTINCT p.employee_id) employees")->orderByDesc('cost')->get();
        return view('hr.operations.analytics', compact('stats','departmentCosts','pay'));
    }

    public function attendanceExceptions()
    {
        \App\Support\PeopleAccess::hr();
        $rows = DB::table('hr_attendance as a')->join('employees as e','a.employee_id','=','e.employee_id')->join('users as u','e.user_id','=','u.user_id')->where(function($q){$q->whereNull('a.time_in')->orWhereNull('a.time_out')->orWhereIn('a.status',['late','absent']);})->whereBetween('a.date',[now()->subDays(14)->toDateString(),now()->toDateString()])->select('a.*','u.full_name')->orderByDesc('a.date')->limit(200)->get();
        return view('hr.operations.attendance-exceptions', compact('rows'));
    }

    public function adminSet(Request $request)
    {
        \App\Support\PeopleAccess::hr();
        $data=$request->validate(['setting_group'=>'required|string|max:50','setting_key'=>'required|string|max:100','setting_value'=>'nullable|string|max:5000']);
        DB::table('hr_settings')->updateOrInsert(['setting_group'=>$data['setting_group'],'setting_key'=>$data['setting_key']],['setting_value'=>$data['setting_value'],'updated_at'=>now(),'created_at'=>now()]);
        return back()->with('success','Setting saved.');
    }

    public function lifecycleEvent(Request $request, int $id)
    {
        \App\Support\PeopleAccess::hr();
        $data=$request->validate(['event_type'=>'required|in:onboarding,probation,promotion,transfer,salary_change,separation,rehire','effective_date'=>'required|date','details'=>'nullable|string|max:5000']);
        DB::table('employee_lifecycle_events')->insert(['employee_id'=>$id,'event_type'=>$data['event_type'],'effective_date'=>$data['effective_date'],'details'=>json_encode(['note'=>$data['details'] ?? null]),'created_by'=>auth()->id(),'created_at'=>now(),'updated_at'=>now()]);
        return back()->with('success','Lifecycle event recorded.');
    }

    /**
     * A supervisor sets their team's shift and rest days: the weekly default,
     * and this cutoff's days off as calendar marks that leave the default alone.
     */
    public function teamSchedule(\Illuminate\Http\Request $request, int $id)
    {
        \App\Support\PeopleAccess::managerForEmployee($id);
        $data = $request->validate([
            'shift_start' => ['nullable', 'date_format:H:i'],
            'shift_end' => ['nullable', 'date_format:H:i'],
            'rest_days' => ['array'], 'rest_days.*' => ['integer', 'between:1,7'],
            'cutoff_rest' => ['array'], 'cutoff_rest.*' => ['date'],
        ]);
        $weekly = \App\Support\WorkWeek::store($data['rest_days'] ?? []);
        $cutoff = \App\Support\PayPeriod::recent(1)[0];
        $restOn = array_flip($data['cutoff_rest'] ?? []);

        DB::transaction(function () use ($id, $data, $weekly, $cutoff, $restOn) {
            DB::table('employees')->where('employee_id', $id)->update([
                'shift_start' => ($data['shift_start'] ?? null) ? $data['shift_start'].':00' : null,
                'shift_end' => ($data['shift_end'] ?? null) ? $data['shift_end'].':00' : null,
                'rest_days' => $weekly, 'updated_at' => now(),
            ]);
            $marks = DB::table('shift_assignments')->where('employee_id', $id)
                ->whereBetween('work_date', [$cutoff->start, $cutoff->end])->get()
                ->keyBy(fn ($m) => substr((string) $m->work_date, 0, 10));
            for ($day = \Carbon\Carbon::parse($cutoff->start); $day->lte(\Carbon\Carbon::parse($cutoff->end)); $day->addDay()) {
                $date = $day->toDateString();
                $rest = isset($restOn[$date]);
                $mark = $marks->get($date);
                $default = \App\Support\WorkWeek::restsOn($weekly, $day);
                if ($mark ? (bool) $mark->rest_day === $rest : $rest === $default) {
                    continue;
                }
                // Back to the default: a plain rest/working mark is no longer needed.
                if ($rest === $default && $mark && $mark->starts_at === null) {
                    DB::table('shift_assignments')->where('id', $mark->id)->delete();
                    continue;
                }
                DB::table('shift_assignments')->updateOrInsert(
                    ['employee_id' => $id, 'work_date' => $date],
                    ['rest_day' => $rest, 'label' => $rest ? 'Rest day' : 'Working day', 'status' => 'approved',
                        'created_by' => auth()->id(), 'approved_by' => auth()->id(), 'approved_at' => now(),
                        'created_at' => $mark->created_at ?? now(), 'updated_at' => now()]
                );
            }
            \App\Services\Auditor::record('update', 'employees', $id, null, ['team_schedule' => $data, 'by' => auth()->id()]);
        });

        return back()->with('success', 'Shift and rest days saved.');
    }

    /**
     * A supervisor marks their own people as out on official business - an
     * event or errand away from the office. Those days count as full working
     * days, and the scanner sync leaves them alone.
     */
    public function teamOfficialBusiness(\Illuminate\Http\Request $request)
    {
        \App\Support\PeopleAccess::manager();
        $data = $request->validate([
            'ob_employee' => ['required', 'integer'],
            'ob_from' => ['required', 'date_format:Y-m-d'],
            'ob_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:ob_from'],
            'ob_note' => ['required', 'string', 'max:120'],
        ], ['ob_employee.required' => 'Choose who was on official business.', 'ob_note.required' => 'Say where - an event name or place.']);
        \App\Support\PeopleAccess::managerForEmployee((int) $data['ob_employee']);

        $from = \Carbon\Carbon::parse($data['ob_from']);
        $to = \Carbon\Carbon::parse($data['ob_to'] ?? null ?: $data['ob_from']);
        if ($from->diffInDays($to) > 31) {
            return back()->withErrors(['ob_to' => 'At most a month at a time.'])->withInput();
        }

        $note = 'Official business: '.trim($data['ob_note']);
        DB::transaction(function () use ($data, $from, $to, $note) {
            for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
                $existing = DB::table('hr_attendance')->where('employee_id', $data['ob_employee'])->whereDate('date', $day)->first();
                if ($existing) {
                    DB::table('hr_attendance')->where('attendance_id', $existing->attendance_id)
                        ->update(['status' => 'official_business', 'notes' => $note, 'updated_at' => now()]);
                } else {
                    DB::table('hr_attendance')->insert(['employee_id' => $data['ob_employee'], 'date' => $day->toDateString(),
                        'status' => 'official_business', 'notes' => $note, 'created_at' => now(), 'updated_at' => now()]);
                }
                \App\Services\Auditor::record('update', 'hr_attendance', $existing->attendance_id ?? 0,
                    $existing ? ['status' => $existing->status] : null,
                    ['status' => 'official_business', 'date' => $day->toDateString(), 'notes' => $note, 'by' => auth()->id()]);
            }
        });

        return back()->with('success', 'Marked '.($from->diffInDays($to) + 1).' day(s) as official business.');
    }
}
