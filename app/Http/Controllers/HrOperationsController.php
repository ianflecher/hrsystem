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
        \App\Support\PeopleAccess::hr();
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
        \App\Support\PeopleAccess::hr();
        $employee = DB::table('employees as e')->join('users as u', 'e.user_id', '=', 'u.user_id')->leftJoin('departments as d', 'e.department_id', '=', 'd.department_id')->where('e.employee_id', $id)->select('e.*', 'u.full_name', 'u.email', 'd.department_name')->first();
        abort_unless($employee, 404);
        $payroll = DB::table('hr_payroll')->where('employee_id', $id)->orderByDesc('period_end')->limit(12)->get();
        $attendance = DB::table('hr_attendance')->where('employee_id', $id)->orderByDesc('date')->limit(30)->get();
        $leave = DB::table('leaves')->where('employee_id', $id)->orderByDesc('start_date')->limit(20)->get();
        $loans = DB::table('employee_loans')->where('employee_id', $id)->orderByDesc('id')->get();
        $documents = DB::table('employee_documents')->where('employee_id', $id)->orderByDesc('id')->get();
        $salaryHistory = DB::table('employee_salary_history')->where('employee_id', $id)->orderByDesc('effective_from')->get();
        return view('hr.operations.employee-360', compact('employee', 'payroll', 'attendance', 'leave', 'loans', 'documents', 'salaryHistory'));
    }

    public function manager()
    {
        \App\Support\PeopleAccess::manager();
        $user = auth()->user();
        $departmentId = DB::table('employees')->where('user_id', $user->user_id)->value('department_id');
        $employees = DB::table('employees as e')->join('users as u', 'e.user_id', '=', 'u.user_id')->where('e.status', 'active')->when($departmentId, fn($q) => $q->where('e.department_id', $departmentId))->select('e.employee_id','u.full_name','e.department_id')->orderBy('u.full_name')->get();
        $pendingLeave = DB::table('leaves as l')->join('employees as e','l.employee_id','=','e.employee_id')->join('users as u','e.user_id','=','u.user_id')->where('l.status','pending')->when($departmentId, fn($q)=>$q->where('e.department_id',$departmentId))->select('l.*','u.full_name')->orderBy('l.start_date')->get();
        $pendingOt = DB::table('overtime_requests as o')->join('employees as e','o.employee_id','=','e.employee_id')->join('users as u','e.user_id','=','u.user_id')->where('o.status','pending')->when($departmentId, fn($q)=>$q->where('e.department_id',$departmentId))->select('o.*','u.full_name')->orderBy('o.starts_at')->get();
        return view('hr.operations.manager', compact('employees','pendingLeave','pendingOt'));
    }

    public function inbox()
    {
        \App\Support\PeopleAccess::hr();
        $requests = DB::table('employee_requests as r')->join('employees as e', 'r.employee_id', '=', 'e.employee_id')->join('users as u', 'e.user_id', '=', 'u.user_id')->where('r.status', 'pending')->select('r.*', 'u.full_name')->orderBy('r.created_at')->get();
        $overtime = DB::table('overtime_requests as o')->join('employees as e', 'o.employee_id', '=', 'e.employee_id')->join('users as u', 'e.user_id', '=', 'u.user_id')->where('o.status', 'pending')->select('o.*', 'u.full_name')->orderBy('o.starts_at')->get();
        return view('hr.operations.inbox', compact('requests', 'overtime'));
    }

    public function approvalCenter()
    {
        \App\Support\PeopleAccess::hr();
        $leave = DB::table('leaves as l')->join('employees as e','l.employee_id','=','e.employee_id')->join('users as u','e.user_id','=','u.user_id')->where('l.status','pending')->select('l.id','l.employee_id','l.start_date','l.end_date','l.reason','u.full_name')->orderBy('l.start_date')->get();
        $overtime = DB::table('overtime_requests as o')->join('employees as e','o.employee_id','=','e.employee_id')->join('users as u','e.user_id','=','u.user_id')->where('o.status','pending')->select('o.id','o.employee_id','o.starts_at','o.ends_at','o.minutes','o.reason','u.full_name')->orderBy('o.starts_at')->get();
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
        \App\Support\PeopleAccess::hr();
        $period = PayPeriod::fromStart($request->get('period', PayPeriod::recent(1)[0]->start));
        $control = app(\App\Services\PayrollControlCenter::class)->control($period);
        $summary = app(\App\Services\PayrollControlCenter::class)->summary($period);
        return view('hr.operations.payroll-approval', compact('period','control','summary'));
    }

    public function approvePayroll(Request $request)
    {
        \App\Support\PeopleAccess::hr();
        try { $period=PayPeriod::fromStart($request->validate(['period'=>'required|date'])['period']); app(\App\Services\PayrollControlCenter::class)->approve($period); SecurityAudit::record('payroll.approved', $period->start.' to '.$period->end); return back()->with('success','Payroll approved.'); }
        catch (\Throwable $e) { return back()->with('error',$e->getMessage()); }
    }

    public function paidPayroll(Request $request)
    {
        \App\Support\PeopleAccess::hr();
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
            'pending_leave' => DB::table('leaves')->where('status','pending')->count(),
            'pending_ot' => DB::table('overtime_requests')->where('status','pending')->count(),
            'payroll_cost' => (float) DB::table('hr_payroll')->whereMonth('period_end',now()->month)->whereYear('period_end',now()->year)->sum('employer_total_cost'),
        ];
        $departmentCosts = DB::table('hr_payroll as p')->join('employees as e','p.employee_id','=','e.employee_id')->leftJoin('departments as d','e.department_id','=','d.department_id')->whereMonth('p.period_end',now()->month)->whereYear('p.period_end',now()->year)->groupBy('d.department_name')->selectRaw("COALESCE(d.department_name,'Unassigned') department, SUM(p.employer_total_cost) cost, COUNT(DISTINCT p.employee_id) employees")->orderByDesc('cost')->get();
        return view('hr.operations.analytics', compact('stats','departmentCosts'));
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

}
