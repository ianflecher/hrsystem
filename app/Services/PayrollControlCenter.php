<?php

namespace App\Services;

use App\Support\PayPeriod;
use App\Support\Statutory;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PayrollControlCenter
{
    public function control(PayPeriod $period): object
    {
        $row = DB::table('payroll_period_controls')->where('period_start', $period->start)->where('period_end', $period->end)->first();
        if ($row) return $row;
        $id = DB::table('payroll_period_controls')->insertGetId([
            'period_start' => $period->start, 'period_end' => $period->end, 'status' => 'open',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return DB::table('payroll_period_controls')->where('id', $id)->first();
    }

    public function summary(PayPeriod $period): array
    {
        $control = $this->control($period);
        $eligible = DB::table('employees')->where('status', 'active')->where(function($q){ $q->where('salary','>',0)->orWhere('daily_rate','>',0); })->count();
        $rows = DB::table('hr_payroll')->where('period_start', $period->start)
            ->selectRaw('status, COUNT(*) n')->groupBy('status')->pluck('n', 'status')->all();

        $missingSalary = DB::table('employees')->where('status', 'active')->where(function($q){ $q->where(function($x){$x->where('pay_basis','monthly')->where('salary','<=',0);})->orWhere(function($x){$x->whereIn('pay_basis',['daily','hourly'])->where('daily_rate','<=',0);}); })->count();
        $unapprovedOt = DB::table('overtime_requests')->where('status', 'pending')
            ->where('starts_at', '<', Carbon::parse($period->end)->addDay())->where('ends_at', '>=', $period->start)->count();
        $gaps = $this->attendanceGapCount($period);
        $negative = DB::table('hr_payroll')->where('period_start', $period->start)->where('net_pay', '<', 0)->count();
        $ruleReady = app(PhilippinePayrollCompliance::class)->status($period->start)['ready'] ?? false;

        $issues = [];
        if ($missingSalary) $issues[] = ['level' => 'high', 'count' => $missingSalary, 'label' => 'Active employees without salary'];
        if ($unapprovedOt) $issues[] = ['level' => 'medium', 'count' => $unapprovedOt, 'label' => 'Pending overtime requests'];
        if ($gaps) $issues[] = ['level' => 'high', 'count' => $gaps, 'label' => 'Attendance gaps'];
        if ($negative) $issues[] = ['level' => 'high', 'count' => $negative, 'label' => 'Negative net pay records'];
        if (! $ruleReady) $issues[] = ['level' => 'high', 'count' => 1, 'label' => 'Philippine payroll rules are not verified'];

        return [
            'control' => $control,
            'eligible' => $eligible,
            'pending' => max(0, $eligible - array_sum($rows)),
            'calculated' => (int) ($rows['calculated'] ?? 0),
            'approved' => (int) ($rows['approved'] ?? 0),
            'paid' => (int) ($rows['paid'] ?? 0),
            'issues' => $issues,
            'rule_version' => Statutory::version(),
            'rule_ready' => $ruleReady,
        ];
    }

    public function assertWritable(PayPeriod $period): void
    {
        $control = $this->control($period);
        if ($control->status === 'locked' || $control->status === 'paid') {
            throw new RuntimeException('This payroll period is locked and cannot be changed.');
        }
    }

    public function approve(PayPeriod $period): int
    {
        $this->assertWritable($period);
        $summary = $this->summary($period);
        if ($summary['issues']) {
            throw new RuntimeException('Resolve payroll control-center exceptions before approval.');
        }

        // Which payslips, not just how many: each one is audited by id below.
        // The screen used to do this before approval moved in here, and the
        // period-level event that replaced it cannot answer "who approved this
        // payslip, and when" - which is the question an audit gets asked.
        $ids = DB::table('hr_payroll')->where('period_start', $period->start)
            ->where('status', 'calculated')->pluck('payroll_id');

        $n = DB::table('hr_payroll')->whereIn('payroll_id', $ids)
            ->update(['status' => 'approved', 'updated_at' => now()]);

        foreach ($ids as $payrollId) {
            Auditor::record('update', 'hr_payroll', $payrollId,
                ['status' => 'calculated'], ['status' => 'approved']);
        }
        DB::table('payroll_period_controls')->where('id', $summary['control']->id)->update([
            'status' => $n ? 'approved' : $summary['control']->status,
            'approved_by' => $n ? auth()->id() : $summary['control']->approved_by,
            'approved_at' => $n ? now() : $summary['control']->approved_at,
            'updated_at' => now(),
        ]);
        if ($n && \Illuminate\Support\Facades\Schema::hasTable('payroll_approval_events')) { DB::table('payroll_approval_events')->insert(['period_start'=>$period->start,'period_end'=>$period->end,'action'=>'approved','user_id'=>auth()->id(),'ip_address'=>request()->ip(),'created_at'=>now(),'updated_at'=>now()]); }
        return $n;
    }

    public function markPaid(PayPeriod $period): int
    {
        $control = $this->control($period);
        if ($control->status !== 'approved') {
            throw new RuntimeException('Payroll must be approved before it can be marked paid.');
        }
        $n = app(PayrollRun::class)->markPaid($period->start);
        if ($n) {
            DB::table('payroll_period_controls')->where('id', $control->id)->update([
                'status' => 'paid', 'paid_by' => auth()->id(), 'paid_at' => now(),
                'locked_by' => auth()->id(), 'locked_at' => now(), 'updated_at' => now(),
            ]);
            if (\Illuminate\Support\Facades\Schema::hasTable('payroll_approval_events')) { DB::table('payroll_approval_events')->insert(['period_start'=>$period->start,'period_end'=>$period->end,'action'=>'paid','user_id'=>auth()->id(),'ip_address'=>request()->ip(),'created_at'=>now(),'updated_at'=>now()]); }
        }
        return $n;
    }

    public function lock(PayPeriod $period): void
    {
        $summary = $this->summary($period);
        if ($summary['pending'] > 0 || $summary['calculated'] > 0 || $summary['approved'] > 0) {
            throw new RuntimeException('A payroll period can only be locked after all payslips are paid or otherwise resolved.');
        }
        DB::table('payroll_period_controls')->where('id', $summary['control']->id)->update([
            'status' => 'locked', 'locked_by' => auth()->id(), 'locked_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function attendanceGapCount(PayPeriod $period): int
    {
        $end = min($period->end, today()->toDateString());
        if ($end < $period->start) return 0;
        $employees = DB::table('employees')->where('status', 'active')->where(function($q){$q->where('salary','>',0)->orWhere('daily_rate','>',0);})->get(['employee_id', 'rest_days', 'hire_date']);
        $present = DB::table('hr_attendance')->whereBetween('date', [$period->start, $end])->whereNotNull('time_in')
            ->get(['employee_id', 'date'])->mapWithKeys(fn ($r) => [$r->employee_id.'|'.substr((string) $r->date, 0, 10) => true]);
        $holidays = DB::table('holidays')->whereBetween('date', [$period->start, $end])->pluck('date')->map(fn ($d) => substr((string) $d, 0, 10))->all();
        $leaves = DB::table('leaves')->where('status', 'approved')->where('start_date', '<=', $end)->where('end_date', '>=', $period->start)->get();
        $gaps = 0;
        foreach ($employees as $e) {
            for ($d = Carbon::parse($period->start); $d->lte(Carbon::parse($end)); $d->addDay()) {
                $date = $d->toDateString();
                if (in_array($date, $holidays, true) || \App\Support\WorkWeek::restsOn($e->rest_days, $d) || ($e->hire_date && $date < substr((string) $e->hire_date, 0, 10)) || $present->has($e->employee_id.'|'.$date)) continue;
                if ($leaves->contains(fn ($l) => (int) $l->employee_id === (int) $e->employee_id && substr((string) $l->start_date, 0, 10) <= $date && substr((string) $l->end_date, 0, 10) >= $date)) continue;
                $gaps++;
            }
        }
        return $gaps;
    }
}
