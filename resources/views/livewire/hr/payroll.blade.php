<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Support\PayPeriod;
use App\Support\PayrollCalculator;
use App\Support\Tardiness;
use Illuminate\Support\Facades\DB;
use Livewire\WithPagination;

new #[Layout('components.layouts.humanresource')] class extends Component
{
    use WithPagination;

    public function boot(): void
    {
        \App\Support\PeopleAccess::hr();
    }
    
    public $search = '';
    public $departmentFilter = '';
    public $payPeriod = '';
    public $statusFilter = 'active';

    /** The year the 13th month panel is showing. */
    public $thirteenthYear = '';
    public bool $showThirteenth = false;
    
    // Payroll details modal
    public $showPayrollDetails = false;
    public $selectedEmployee = null;
    public $payrollBreakdown = [
        'sss' => 0,
        'philhealth' => 0,
        'pagibig' => 0,
        'tax' => 0,
        'time' => 0,
        'loan' => 0,
        'other_deductions' => 0,
        'total_deductions' => 0
    ];
    
    public function mount()
    {
        // The cutoff today falls in: the 1st-15th, or the 16th to month end.
        $this->payPeriod = PayPeriod::recent(1)[0]->start;
    }
    
    public function getPayPeriods()
    {
        return array_map(
            fn (PayPeriod $p) => ['value' => $p->start, 'label' => $p->label()],
            PayPeriod::recent(12)
        );
    }

    private function period(): PayPeriod
    {
        return PayPeriod::fromStart($this->payPeriod);
    }

    public function getControlCenterProperty(): array
    {
        return app(\App\Services\PayrollControlCenter::class)->summary($this->period());
    }

    public function lockPeriod(): void
    {
        \App\Support\PeopleAccess::hr();
        app(\App\Services\PayrollControlCenter::class)->lock($this->period());
        session()->flash('success', 'Payroll period locked.');
    }

    public function getEmployeesProperty()
    {
        return DB::table('employees')
            ->join('users', 'employees.user_id', '=', 'users.user_id')
            ->leftJoin('departments', 'employees.department_id', '=', 'departments.department_id')
            ->leftJoin('hr_payroll', function($join) {
                $join->on('employees.employee_id', '=', 'hr_payroll.employee_id')
                    ->where('hr_payroll.period_start', '=', $this->payPeriod);
            })
            ->select(
                'employees.employee_id',
                'employees.user_id',
                'users.full_name',
                'users.email',
                'employees.job_title',
                'departments.department_name',
                'employees.salary',
                'employees.status as employee_status',
                'hr_payroll.payroll_id',
                'hr_payroll.gross_pay',
                'hr_payroll.deductions',
                'hr_payroll.net_pay',
                DB::raw('COALESCE(hr_payroll.status, "not_processed") as payroll_status'),
                'hr_payroll.period_start',
            'hr_payroll.period_end',
            'hr_payroll.notes',
            'hr_payroll.overtime_pay',
            'hr_payroll.holiday_pay',
            'hr_payroll.nsd_pay',
            'hr_payroll.basic_pay',
            'hr_payroll.time_deduction',
            'hr_payroll.employer_sss',
            'hr_payroll.employer_ec',
            'hr_payroll.employer_philhealth',
            'hr_payroll.employer_pagibig',
            'hr_payroll.taxable_compensation',
            'hr_payroll.statutory_rule_version',
            'hr_payroll.loan_deduction',
            'hr_payroll.sss',
            'hr_payroll.philhealth',
            'hr_payroll.pagibig',
            'hr_payroll.tax'
            )
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('users.full_name', 'like', '%' . $this->search . '%')
                      ->orWhere('users.email', 'like', '%' . $this->search . '%')
                      ->orWhere('employees.job_title', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->departmentFilter, function ($query) {
                $query->where('departments.department_id', $this->departmentFilter);
            })
            ->when($this->statusFilter, function ($query) {
                $query->where('employees.status', $this->statusFilter);
            })
            ->orderBy('users.full_name')
            ->paginate(20);
    }
    
    public function getDepartmentsProperty()
    {
        return DB::table('departments')
            ->orderBy('department_name')
            ->get();
    }
    
    /**
     * What this period comes to.
     *
     * The totals used to be joined on status = 'paid', so the cards read
     * PHP 0.00 for the whole life of a payroll run while the rows beneath
     * them listed real figures - the screen contradicted itself from
     * generation until the money went out, which is exactly when somebody is
     * looking at it.
     *
     * The sums now cover the period. Only the headcount is about payment, and
     * it says so.
     */
    public function getPayrollStatsProperty()
    {
        return DB::table('employees')
            ->join('hr_payroll', function ($join) {
                $join->on('employees.employee_id', '=', 'hr_payroll.employee_id')
                    ->where('hr_payroll.period_start', '=', $this->payPeriod);
            })
            ->select(
                DB::raw('COUNT(DISTINCT employees.employee_id) as total_employees'),
                DB::raw('COUNT(DISTINCT CASE WHEN hr_payroll.status = "paid" THEN employees.employee_id END) as total_paid'),
                DB::raw('COALESCE(SUM(hr_payroll.gross_pay), 0) as total_gross'),
                DB::raw('COALESCE(SUM(hr_payroll.deductions), 0) as total_deductions'),
                DB::raw('COALESCE(SUM(hr_payroll.net_pay), 0) as total_net')
            )
            ->first();
    }
    
    public function viewPayrollDetails($employeeId)
    {
        $this->selectedEmployee = DB::table('employees')
            ->join('users', 'employees.user_id', '=', 'users.user_id')
            ->leftJoin('departments', 'employees.department_id', '=', 'departments.department_id')
            ->leftJoin('hr_payroll', function($join) {
                $join->on('employees.employee_id', '=', 'hr_payroll.employee_id')
                    ->where('hr_payroll.period_start', '=', $this->payPeriod);
            })
            ->select(
                'employees.employee_id',
                'employees.user_id',
                'users.full_name',
                'users.email',
                'employees.job_title',
                'departments.department_name',
                'employees.salary',
                'employees.hire_date',
                'employees.status as employee_status',
                'hr_payroll.*'
            )
            ->where('employees.employee_id', $employeeId)
            ->first();
        
        // Calculate Philippines deductions for display
        $this->calculatePhilippinesDeductions();
        
        $this->showPayrollDetails = true;
    }
    
    private function calculatePhilippinesDeductions()
    {
        if (! $this->selectedEmployee) return;

        $row = $this->selectedEmployee;

        if ($row->payroll_id) {
            $breakdown = \App\Support\PayslipBreakdown::fromPayroll($row);

            $this->payrollBreakdown['sss']        = (float) $row->sss;
            $this->payrollBreakdown['philhealth'] = (float) $row->philhealth;
            $this->payrollBreakdown['pagibig']    = (float) $row->pagibig;
            $this->payrollBreakdown['tax']        = (float) $row->tax;
            $this->payrollBreakdown['time']       = $breakdown['time'];
            $this->payrollBreakdown['loan']       = $breakdown['loan'];
            $this->payrollBreakdown['other_deductions'] = $breakdown['other_deductions'];
            $this->payrollBreakdown['total_deductions'] = $breakdown['total_deductions'];

            return;
        }

        // No payslip yet, so this is a preview of what one would hold.
        $calc = $this->computePayroll((float) $row->salary);

        $this->payrollBreakdown['sss']        = $calc['sss'];
        $this->payrollBreakdown['philhealth'] = $calc['philhealth'];
        $this->payrollBreakdown['pagibig']    = $calc['pagibig'];
        $this->payrollBreakdown['tax']        = $calc['tax'];
        $this->payrollBreakdown['time']       = 0;
        $this->payrollBreakdown['loan']       = 0;
        $this->payrollBreakdown['other_deductions'] = 0;
        $this->payrollBreakdown['total_deductions'] = $calc['deductions'];
    }
    
    public function closePayrollDetails()
    {
        $this->showPayrollDetails = false;
        $this->selectedEmployee = null;
        $this->payrollBreakdown = [
            'sss' => 0,
            'philhealth' => 0,
            'pagibig' => 0,
            'tax' => 0,
            'time' => 0,
            'loan' => 0,
            'other_deductions' => 0,
            'total_deductions' => 0
        ];
    }
    
    public function processPayroll($employeeId)
    {
        \App\Support\PeopleAccess::hr();
        $id = app(\App\Services\PayrollRun::class)->generate((int) $employeeId, $this->period());
        session()->flash($id ? 'success' : 'info', $id ? 'Payroll processed successfully!' : 'Payroll already exists or this employee is not eligible.');
        if ($id) $this->viewPayrollDetails($employeeId);
    }
    /**
     * The statutory deductions for one monthly salary.
     *
     * Preview of the configured Philippine statutory calculation for an
     * unprocessed monthly-paid employee. The live payroll service uses the
     * effective rule snapshot and records that snapshot on the payslip.
     */
    private function computePayroll(float $monthlySalary, float $lateDeduction = 0.0): array
    {
        return PayrollCalculator::forCutoff($monthlySalary, $lateDeduction, $this->period()->isSecondCutoff);
    }

    /**
     * Runs the period for everyone at once.
     *
     * Only active employees, only those without a row for this period already,
     * and only those on a salary above zero - an employee whose salary has not
     * been entered yet would otherwise get a payslip for nothing, which looks
     * like a decision rather than missing data.
     */
    public function generatePeriod(): void
    {
        $periodStart = $this->period()->start;
        $periodEnd   = $this->period()->end;

        $already = DB::table('hr_payroll')
            ->where('period_start', $periodStart)
            ->pluck('employee_id')
            ->all();

        $employees = DB::table('employees')
            ->where('status', 'active')
            ->where('salary', '>', 0)
            ->whereNotIn('employee_id', $already ?: [0])
            ->select('employee_id', 'salary')
            ->get();

        if ($employees->isEmpty()) {
            session()->flash('info', 'Nothing to generate: everyone active with a salary already has a payslip for '
                .$this->period()->label().'.');

            return;
        }

        \App\Support\PeopleAccess::hr();
        $generated = DB::transaction(function () use ($employees) {
            $count = 0;
            foreach ($employees as $employee) {
                if (app(\App\Services\PayrollRun::class)->generate((int) $employee->employee_id, $this->period())) $count++;
            }
            return $count;
        });
        $skipped = DB::table('employees')->where('status', 'active')->where('salary', '<=', 0)->count();

        $message = 'Generated '.$generated.' payslip'.($generated === 1 ? '' : 's')
            .' for '.$this->period()->label().'. They are calculated, not yet approved.';

        if ($skipped > 0) {
            $message .= ' '.$skipped.' active employee'.($skipped === 1 ? ' has' : 's have')
                .' no salary set and were skipped.';
        }

        session()->flash('success', $message);
        $this->resetPage();
    }

    /**
     * Approves everything calculated for the period in one go.
     */
    public function approvePeriod(): void
    {
        \App\Support\PeopleAccess::hr();
        try {
            $n = app(\App\Services\PayrollControlCenter::class)->approve($this->period());
            session()->flash($n ? 'success' : 'info', $n ? 'Approved '.$n.' payslip'.($n === 1 ? '' : 's').'.' : 'Nothing was waiting for approval in this period.');
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function markPeriodPaid(): void
    {
        \App\Support\PeopleAccess::hr();
        try {
            $n = app(\App\Services\PayrollControlCenter::class)->markPaid($this->period());
            session()->flash($n ? 'success' : 'info', $n ? 'Marked '.$n.' payslip'.($n === 1 ? '' : 's').' as paid and locked the period.' : 'Nothing was approved and waiting to be paid in this period.');
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    /**
     * What the buttons should offer for the period currently selected.
     */
    public ?int $answering = null;
    public string $resolutionNotes = '';

    /**
     * Disputes about a payslip.
     *
     * Employees could raise these and nothing ever showed them: the rows went
     * into payroll_corrections and no screen read the table. Payroll takes real
     * money off people now, so this is the channel they push back through, and
     * it has to end somewhere a person is looking.
     */
    public function getCorrectionsProperty()
    {
        return DB::table('payroll_corrections as c')
            ->join('employees as e', 'e.employee_id', '=', 'c.employee_id')
            ->join('users as u', 'u.user_id', '=', 'e.user_id')
            ->leftJoin('hr_payroll as p', 'p.payroll_id', '=', 'c.payroll_id')
            ->orderByRaw("CASE WHEN c.status = 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('c.correction_id')
            ->limit(25)
            ->select('c.*', 'u.full_name', 'p.period_start', 'p.period_end', 'p.net_pay', 'p.notes as payslip_notes')
            ->get();
    }

    public function getPendingCorrectionsProperty(): int
    {
        return DB::table('payroll_corrections')->where('status', 'pending')->count();
    }

    public function answerCorrection(int $id): void
    {
        $this->answering = $id;
        $this->resolutionNotes = '';
        $this->resetErrorBag();
    }

    public function cancelAnswer(): void
    {
        $this->answering = null;
        $this->resolutionNotes = '';
    }

    /**
     * Closes a dispute, either way. A note is required for both: "rejected"
     * with no reason is how somebody ends up asking again.
     */
    public function closeCorrection(string $outcome): void
    {
        $this->validate([
            'resolutionNotes' => ['required', 'string', 'min:5', 'max:2000'],
        ], [], ['resolutionNotes' => 'reply']);

        // The column's own words: approved means the payslip was wrong and has
        // been put right, rejected means it was not.
        abort_unless(in_array($outcome, ['approved', 'rejected'], true), 422);

        $correction = DB::table('payroll_corrections')->where('correction_id', $this->answering)->first();

        if (! $correction || $correction->status !== 'pending') {
            session()->flash('error', 'That request has already been answered.');
            $this->cancelAnswer();

            return;
        }

        DB::table('payroll_corrections')->where('correction_id', $this->answering)->update([
            'status'           => $outcome,
            'resolution_notes' => $this->resolutionNotes,
            'resolved_by'      => auth()->id(),
            'resolved_at'      => now(),
            'updated_at'       => now(),
        ]);

        \App\Services\Auditor::record('update', 'payroll_corrections', $this->answering,
            ['status' => 'pending'], ['status' => $outcome, 'resolution_notes' => $this->resolutionNotes]);

        $this->cancelAnswer();
        session()->flash('success', 'The employee can see your reply on their payroll screen.');
    }

    public function getThirteenthRowsProperty(): array
    {
        return (new \App\Services\ThirteenthMonth)->forYear($this->thirteenthYearOrNow());
    }

    public function thirteenthYearOrNow(): int
    {
        return (int) ($this->thirteenthYear ?: now()->year);
    }

    public function toggleThirteenth(): void
    {
        $this->showThirteenth = ! $this->showThirteenth;

        if ($this->thirteenthYear === '') {
            $this->thirteenthYear = (string) now()->year;
        }
    }

    /**
     * Records the 13th month for the year on screen. It is one payment per
     * employee per year, so running it twice adds nothing.
     */
    public function generateThirteenth(): void
    {
        $year = $this->thirteenthYearOrNow();
        $recorded = (new \App\Services\ThirteenthMonth)->generate($year);

        session()->flash($recorded > 0 ? 'success' : 'info', $recorded > 0
            ? $recorded.' 13th month payslip(s) recorded for '.$year.'.'
            : 'Nothing to record for '.$year.' - everybody owed one already has it.');
    }

    /**
     * Days in this cutoff where somebody has no attendance at all.
     *
     * A missed day is now money, and the usual reason for a gap is not that
     * nobody came in - it is that the scanner was off, or a sync was never run.
     * So the gaps are counted and shown before anything is generated, because
     * once a payslip is approved and paid the money has gone.
     */
    public function getAttendanceGapsProperty(): array
    {
        $start = $this->period()->start;
        $end   = min($this->period()->end, today()->toDateString());

        if ($end < $start) {
            return ['days' => 0, 'people' => 0];
        }

        $holidays = DB::table('holidays')->whereBetween('date', [$start, $end])->pluck('date')
            ->map(fn ($date) => substr((string) $date, 0, 10))->all();

        $employees = DB::table('employees')->where('status', 'active')->where('salary', '>', 0)
            ->select('employee_id', 'rest_days', 'hire_date')->get();

        $present = DB::table('hr_attendance')->whereBetween('date', [$start, $end])->whereNotNull('time_in')
            ->get(['employee_id', 'date'])
            ->map(fn ($row) => $row->employee_id.'|'.substr((string) $row->date, 0, 10))->flip();

        $leaves = DB::table('leaves')->where('status', 'approved')
            ->where('start_date', '<=', $end)->where('end_date', '>=', $start)->get();

        $days = 0;
        $people = [];

        foreach ($employees as $employee) {
            for ($day = \Carbon\Carbon::parse($start); $day->lte(\Carbon\Carbon::parse($end)); $day->addDay()) {
                $date = $day->toDateString();

                if (in_array($date, $holidays, true)
                    || \App\Support\WorkWeek::restsOn($employee->rest_days, $day)
                    || ($employee->hire_date && $date < substr((string) $employee->hire_date, 0, 10))
                    || $present->has($employee->employee_id.'|'.$date)) {
                    continue;
                }

                $onLeave = $leaves->contains(fn ($leave) => $leave->employee_id === $employee->employee_id
                    && substr((string) $leave->start_date, 0, 10) <= $date
                    && substr((string) $leave->end_date, 0, 10) >= $date);

                if ($onLeave) {
                    continue;
                }

                $days++;
                $people[$employee->employee_id] = true;
            }
        }

        return ['days' => $days, 'people' => count($people)];
    }

    public function getPeriodCountsProperty(): array
    {
        $periodStart = $this->period()->start;

        $byStatus = DB::table('hr_payroll')
            ->where('period_start', $periodStart)
            ->selectRaw('status, COUNT(*) as n')
            ->groupBy('status')
            ->pluck('n', 'status')
            ->all();

        $eligible = DB::table('employees')
            ->where('status', 'active')
            ->where('salary', '>', 0)
            ->count();

        $existing = array_sum($byStatus);

        return [
            'eligible'   => $eligible,
            'pending'    => max(0, $eligible - $existing),
            'calculated' => $byStatus['calculated'] ?? 0,
            'approved'   => $byStatus['approved'] ?? 0,
            'paid'       => $byStatus['paid'] ?? 0,
        ];
    }

    public function payrollRuleVersion(): string
    {
        return \App\Support\Statutory::version();
    }

    public function approvePayroll($employeeId)
    {
        $payroll = DB::table('hr_payroll')
            ->where('employee_id', $employeeId)
            ->where('period_start', '=', $this->payPeriod)
            ->first();
            
        if ($payroll && $payroll->status === 'calculated') {
            DB::table('hr_payroll')
                ->where('payroll_id', $payroll->payroll_id)
                ->update(['status' => 'approved', 'updated_at' => now()]);

            \App\Services\Auditor::record('update', 'hr_payroll', $payroll->payroll_id,
                ['status' => 'calculated'], ['status' => 'approved']);
                
            session()->flash('success', 'Payroll approved successfully!');
            
            // Refresh the view
            $this->viewPayrollDetails($employeeId);
        }
    }
    
    public function markAsPaid($employeeId)
    {
        $payroll = DB::table('hr_payroll')
            ->where('employee_id', $employeeId)
            ->where('period_start', '=', $this->payPeriod)
            ->first();
            
        // This used to take a payslip straight from calculated to paid, skipping
        // approval and skipping PayrollRun::markPaid - so a loan installment
        // reserved against that payslip was never settled and the loan never
        // closed. Paid means approved first, and through the service.
        if ($payroll && $payroll->status === 'approved') {
            app(\App\Services\PayrollRun::class)->markPaid($payroll->period_start);

            \App\Services\Auditor::record('update', 'hr_payroll', $payroll->payroll_id,
                ['status' => 'approved'], ['status' => 'paid']);

            session()->flash('success', 'Payroll marked as paid!');
            $this->viewPayrollDetails($employeeId);
        } elseif ($payroll) {
            session()->flash('error', 'Approve the payslip before marking it paid.');
        }
    }
    
    public function exportPayroll()
    {
        $employees = $this->employees->items();
        $period = $this->period()->label();
        
        // No thousands separator: a comma inside an unquoted CSV field splits
        // it into two columns, so every amount landed one cell to the right.
        $money = fn ($amount) => number_format((float) $amount, 2, '.', '');

        $csvData = "Employee Name,Department,Position,Basic Salary,Gross Pay,Deductions,Net Pay,Status
";

        foreach ($employees as $emp) {
            $csvData .= '"'.str_replace('"', '""', (string) $emp->full_name).'",';
            $csvData .= '"'.str_replace('"', '""', (string) $emp->department_name).'",';
            $csvData .= '"'.str_replace('"', '""', (string) $emp->job_title).'",';
            $csvData .= $money($emp->salary).",";
            $csvData .= $money($emp->gross_pay ?? 0).",";
            $csvData .= $money($emp->deductions ?? 0).",";
            $csvData .= $money($emp->net_pay ?? 0).",";
            $csvData .= $emp->payroll_status;
            $csvData .= "
";
        }

        $filename = "payroll-export-{$this->payPeriod}-" . date('YmdHis') . ".csv";
        
        return response()->streamDownload(function () use ($csvData) {
            echo $csvData;
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
?>

<div class="p-6 md:p-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header Section -->
        <div class="md:flex md:items-center md:justify-between mb-6">
            <div class="flex-1 min-w-0">
                <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">
                    Payroll Overview
                </h2>
                <p class="mt-1 text-sm text-gray-500">
                    View and manage all employee payroll for the selected period
                </p>
            </div>
            <div class="mt-4 flex md:mt-0 md:ml-4 space-x-3">
                <button wire:click="exportPayroll" 
                        class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <svg class="mr-2 h-4 w-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Export CSV
                </button>
            </div>
        </div>

        <!-- Flash Messages -->
        @if (session()->has('success'))
            <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-green-700">{{ session('success') }}</p>
                    </div>
                </div>
            </div>
        @endif

        @if (session()->has('error'))
            <div class="mb-4 p-4 bg-slate-50 border border-gray-200 rounded-lg">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-red-700">{{ session('error') }}</p>
                    </div>
                </div>
            </div>
        @endif

        @if (session('info'))
            <div class="mb-6 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3">
                <p class="text-sm text-blue-800">{{ session('info') }}</p>
            </div>
        @endif

        {{-- Said before generating, not after: a missed day now costs a day's pay,
             and the usual cause of a gap is a scanner that was off rather than
             somebody who stayed home. --}}
        @if ($this->attendanceGaps['days'] > 0)
            <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                <strong>{{ $this->attendanceGaps['days'] }} day(s) with no attendance record</strong>
                across {{ $this->attendanceGaps['people'] }} employee(s) in this cutoff.
                Each will be treated as a day missed and deducted.
                If the scanner was down, sync or correct attendance before generating.
            </div>
        @endif

        {{-- Where a dispute lands. --}}
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-5 mb-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">
                        Correction requests
                        @if ($this->pendingCorrections > 0)
                            <span class="ml-2 px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 text-xs font-semibold align-middle">
                                {{ $this->pendingCorrections }} waiting
                            </span>
                        @endif
                    </h2>
                    <p class="text-sm text-gray-600 mt-1">Payslips an employee has questioned.</p>
                </div>
            </div>

            @forelse ($this->corrections as $correction)
                <div class="mt-4 rounded-lg border {{ $correction->status === 'pending' ? 'border-amber-200 bg-amber-50' : 'border-gray-200' }} p-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <strong class="text-gray-900">{{ $correction->full_name }}</strong>
                            <span class="text-sm text-gray-600">
                                @if ($correction->period_start)
                                    · {{ \Illuminate\Support\Carbon::parse($correction->period_start)->format('j M Y') }}
                                    &ndash; {{ \Illuminate\Support\Carbon::parse($correction->period_end)->format('j M Y') }}
                                    · net PHP {{ number_format((float) $correction->net_pay, 2) }}
                                @endif
                            </span>
                        </div>
                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold
                            @if ($correction->status === 'pending') bg-amber-100 text-amber-800
                            @elseif ($correction->status === 'approved') bg-green-100 text-green-800
                            @else bg-gray-100 text-gray-700 @endif">
                            {{ $correction->status === 'approved' ? 'Corrected' : ucfirst($correction->status) }}
                        </span>
                    </div>

                    <p class="mt-2 text-sm text-gray-800">{{ $correction->description }}</p>

                    @if ($correction->payslip_notes)
                        <p class="mt-1 text-xs text-gray-500">Payslip said: {{ $correction->payslip_notes }}</p>
                    @endif

                    @if ($correction->resolution_notes)
                        <p class="mt-2 text-sm text-gray-700"><strong>Reply:</strong> {{ $correction->resolution_notes }}</p>
                    @endif

                    @if ($correction->status === 'pending')
                        @if ($answering === (int) $correction->correction_id)
                            <div class="mt-3">
                                <textarea wire:model="resolutionNotes" rows="3"
                                          placeholder="What you found, and what you did about it."
                                          class="form-input w-full"></textarea>
                                @error('resolutionNotes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <button wire:click="closeCorrection('approved')" class="btn-primary">Corrected</button>
                                    <button wire:click="closeCorrection('rejected')" class="btn-secondary">No change needed</button>
                                    <button wire:click="cancelAnswer" class="btn-secondary">Cancel</button>
                                </div>
                            </div>
                        @else
                            <button wire:click="answerCorrection({{ $correction->correction_id }})" class="btn-secondary mt-3">
                                Answer
                            </button>
                        @endif
                    @endif
                </div>
            @empty
                <p class="mt-4 text-sm text-gray-500">Nobody has questioned a payslip.</p>
            @endforelse
        </div>

        {{-- 13th month pay: a twelfth of the basic salary actually earned over
             the year, so absence and unpaid leave reduce it, and overtime and
             holiday premiums do not inflate it. Payable on or before 24
             December, which is the date the payslip carries. --}}
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-5 mb-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">13th month pay</h2>
                    <p class="text-sm text-gray-600 mt-1">
                        One twelfth of the basic salary earned in the year. Due on or before 24 December.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <input type="number" wire:model.live="thirteenthYear" min="2000" max="2100"
                           class="form-input w-28" placeholder="{{ now()->year }}">
                    <button wire:click="toggleThirteenth" class="btn-secondary">
                        {{ $showThirteenth ? 'Hide' : 'Show' }}
                    </button>
                </div>
            </div>

            @if ($showThirteenth)
                @php
                    $rows = $this->thirteenthRows;
                    $outstanding = collect($rows)->whereNull('recorded');
                @endphp

                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left text-gray-600 border-b border-gray-200">
                                <th class="py-2">Employee</th>
                                <th class="py-2">Basic earned</th>
                                <th class="py-2">13th month</th>
                                <th class="py-2">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $row)
                                <tr class="border-b border-gray-100">
                                    <td class="py-2">{{ $row['name'] }}</td>
                                    <td class="py-2 text-gray-600">
                                        PHP {{ number_format($row['basic'], 2) }}
                                        <span class="block text-xs text-gray-500">{{ $row['payslips'] }} payslip(s)</span>
                                    </td>
                                    <td class="py-2 font-semibold">PHP {{ number_format($row['amount'], 2) }}</td>
                                    <td class="py-2">
                                        @if ($row['recorded'] !== null)
                                            <span class="text-green-700">Recorded</span>
                                        @else
                                            <span class="text-gray-500">Not yet recorded</span>
                                        @endif
                                        @if ($row['taxableExcess'] > 0)
                                            <span class="block text-xs text-amber-700">
                                                PHP {{ number_format($row['taxableExcess'], 2) }} above the exemption is taxable
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="py-3 text-gray-500">
                                    Nobody has a payslip for {{ $this->thirteenthYearOrNow() }} yet, so there is nothing to work from.
                                </td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($outstanding->isNotEmpty())
                    <div class="mt-4 flex items-center gap-3">
                        <button wire:click="generateThirteenth"
                                wire:confirm="Record 13th month pay for {{ $outstanding->count() }} employee(s) for {{ $this->thirteenthYearOrNow() }}?"
                                class="btn-primary">
                            Record ({{ $outstanding->count() }})
                        </button>
                        <span class="text-sm text-gray-600">
                            Total PHP {{ number_format($outstanding->sum('amount'), 2) }}
                        </span>
                    </div>
                @endif

                <p class="mt-3 text-xs text-gray-500">
                    The first PHP 90,000 a year is tax exempt. Anything above it is taxable and is flagged
                    rather than withheld, because that depends on the whole year's pay.
                </p>
            @endif
        </div>

        {{-- Payroll control center: exceptions are visible before approval. --}}
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-5 mb-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Control center</p>
                    <h2 class="text-lg font-semibold text-gray-900 mt-1">{{ $this->period()->label() }}</h2>
                    <p class="text-sm text-gray-600 mt-1">
                        Rule version <strong>{{ $this->controlCenter['rule_version'] }}</strong> ·
                        Period status <strong>{{ ucfirst($this->controlCenter['control']->status) }}</strong>
                    </p>
                </div>
                @if($this->controlCenter['control']->status === 'locked' || $this->controlCenter['control']->status === 'paid')
                    <span class="status-badge status-active">Locked</span>
                @elseif($this->controlCenter['issues'])
                    <span class="status-badge status-pending">{{ count($this->controlCenter['issues']) }} exception(s)</span>
                @else
                    <span class="status-badge status-active">Ready for review</span>
                @endif
            </div>

            <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mt-4">
                <div class="rounded-lg bg-gray-50 p-3"><div class="text-xs text-gray-500">Eligible</div><div class="text-xl font-semibold">{{ $this->controlCenter['eligible'] }}</div></div>
                <div class="rounded-lg bg-gray-50 p-3"><div class="text-xs text-gray-500">Missing payslip</div><div class="text-xl font-semibold">{{ $this->controlCenter['pending'] }}</div></div>
                <div class="rounded-lg bg-gray-50 p-3"><div class="text-xs text-gray-500">Calculated</div><div class="text-xl font-semibold">{{ $this->controlCenter['calculated'] }}</div></div>
                <div class="rounded-lg bg-gray-50 p-3"><div class="text-xs text-gray-500">Approved</div><div class="text-xl font-semibold">{{ $this->controlCenter['approved'] }}</div></div>
                <div class="rounded-lg bg-gray-50 p-3"><div class="text-xs text-gray-500">Paid</div><div class="text-xl font-semibold">{{ $this->controlCenter['paid'] }}</div></div>
            </div>

            @if($this->controlCenter['issues'])
                <div class="mt-4 border border-amber-200 bg-amber-50 rounded-lg p-4">
                    <h3 class="font-semibold text-amber-900">Resolve before approval</h3>
                    <ul class="mt-2 space-y-1 text-sm text-amber-900">
                        @foreach($this->controlCenter['issues'] as $issue)
                            <li>• {{ $issue['count'] }} — {{ $issue['label'] }}</li>
                        @endforeach
                    </ul>
                </div>
            @else
                <div class="mt-4 border border-green-200 bg-green-50 rounded-lg p-4 text-sm text-green-900">
                    No control-center exceptions detected for this period.
                </div>
            @endif

            @if($this->controlCenter['control']->status === 'paid')
                <div class="mt-3 text-sm text-gray-600">Paid periods are automatically locked to prevent silent changes.</div>
            @endif
        </div>

        {{-- The run itself. Payroll is the one thing in here that moves money,
             so it is three deliberate steps rather than one button: generate
             the figures, approve them, then record them as paid. Each says how
             many it will touch before it is pressed. --}}
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-5 mb-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">
                        Payroll run &mdash; {{ $this->period()->label() }}
                    </h2>
                    <p class="text-sm text-gray-600 mt-1">
                        {{ $this->periodCounts['eligible'] }} active
                        {{ $this->periodCounts['eligible'] === 1 ? 'employee' : 'employees' }} with a salary set.
                        @if ($this->periodCounts['pending'] > 0)
                            <span class="text-amber-700 font-medium">
                                {{ $this->periodCounts['pending'] }} still without a payslip this period.
                            </span>
                        @else
                            Everyone has a payslip for this period.
                        @endif
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button wire:click="generatePeriod"
                            wire:confirm="Generate payslips for {{ $this->periodCounts['pending'] }} employee(s) for {{ $this->period()->label() }}?"
                            @disabled($this->periodCounts['pending'] === 0)
                            class="btn-primary disabled:opacity-40 disabled:cursor-not-allowed">
                        <i class="fas fa-calculator"></i>
                        Generate ({{ $this->periodCounts['pending'] }})
                    </button>

                    <button wire:click="approvePeriod"
                            wire:confirm="Approve {{ $this->periodCounts['calculated'] }} calculated payslip(s)?"
                            @disabled($this->periodCounts['calculated'] === 0)
                            class="btn-secondary disabled:opacity-40 disabled:cursor-not-allowed">
                        <i class="fas fa-check"></i>
                        Approve ({{ $this->periodCounts['calculated'] }})
                    </button>

                    <button wire:click="markPeriodPaid"
                            wire:confirm="Mark {{ $this->periodCounts['approved'] }} approved payslip(s) as paid? This records that the money has gone out."
                            @disabled($this->periodCounts['approved'] === 0)
                            class="btn-secondary disabled:opacity-40 disabled:cursor-not-allowed">
                        <i class="fas fa-money-bill-wave"></i>
                        Mark paid ({{ $this->periodCounts['approved'] }})
                    </button>
                </div>
            </div>

            <div class="flex flex-wrap gap-2 mt-4 pt-4 border-t border-gray-200">
                <span class="status-badge status-pending">
                    {{ $this->periodCounts['calculated'] }} calculated
                </span>
                <span class="status-badge status-onleave">
                    {{ $this->periodCounts['approved'] }} approved
                </span>
                <span class="status-badge status-active">
                    {{ $this->periodCounts['paid'] }} paid
                </span>
            </div>

            <p class="text-xs text-gray-500 mt-4">
                Payroll uses the effective Philippine rule snapshot shown above. Keep that snapshot
                verified against current government issuances before each live payroll run.
            </p>
        </div>

        <!-- Filters and Controls -->
        <div class="bg-white shadow rounded-lg p-4 mb-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <!-- Pay Period Selector -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Pay Period</label>
                    <select wire:model.live="payPeriod" 
                            class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        @foreach($this->getPayPeriods() as $period)
                            <option value="{{ $period['value'] }}">{{ $period['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                
                <!-- Department Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                    <select wire:model.live="departmentFilter" 
                            class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        <option value="">All Departments</option>
                        @foreach($this->departments as $dept)
                            <option value="{{ $dept->department_id }}">{{ $dept->department_name }}</option>
                        @endforeach
                    </select>
                </div>
                
                <!-- Status Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Employee Status</label>
                    <select wire:model.live="statusFilter" 
                            class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="on_leave">On Leave</option>
                        <option value="">All Status</option>
                    </select>
                </div>
                
                <!-- Search -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                    <input type="text" 
                           wire:model.live.debounce.300ms="search" 
                           placeholder="Search by name, email, or job title..."
                           class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="h-6 w-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Total Employees</dt>
                                <dd class="text-lg font-medium text-gray-900">{{ $this->employees->total() }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="h-6 w-6 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Total Gross Pay</dt>
                                <dd class="text-lg font-medium text-gray-900">
                                    ₱{{ number_format($this->payrollStats?->total_gross ?? 0, 2) }}
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="h-6 w-6 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Total Deductions</dt>
                                <dd class="text-lg font-medium text-gray-900">
                                    ₱{{ number_format($this->payrollStats?->total_deductions ?? 0, 2) }}
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="h-6 w-6 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Total Net Pay</dt>
                                <dd class="text-lg font-medium text-gray-900">
                                    ₱{{ number_format($this->payrollStats?->total_net ?? 0, 2) }}
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payroll Table -->
        <div class="bg-white shadow overflow-hidden sm:rounded-lg">
            {{-- On a phone this table is 984px of columns in a 247px column,
                 so it wanted four screens of sideways scrolling to read one
                 person's pay. A table that wide cannot be made to fit; below
                 md it stops being a table and each person becomes a card. --}}
            <div class="md:hidden divide-y divide-gray-200">
                @forelse($this->employees as $employee)
                    @php
                        $statusColors = [
                            'draft' => 'bg-gray-100 text-gray-800',
                            'calculated' => 'bg-blue-100 text-blue-800',
                            'approved' => 'bg-yellow-100 text-yellow-800',
                            'paid' => 'bg-green-100 text-green-800',
                            'cancelled' => 'bg-gray-100 text-gray-700',
                            'not_processed' => 'bg-gray-100 text-gray-800',
                        ];
                        $payrollStatus = $employee->payroll_status;
                        $colorClass = $statusColors[$payrollStatus] ?? 'bg-gray-100 text-gray-800';
                        $statusText = $payrollStatus === 'not_processed' ? 'Not Processed' : ucfirst($payrollStatus);
                    @endphp

                    <div class="p-4" wire:key="pay-card-{{ $employee->employee_id }}">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-center min-w-0">
                                <div class="h-9 w-9 shrink-0 rounded-full bg-blue-100 flex items-center justify-center">
                                    <span class="text-blue-600 font-medium">{{ substr($employee->full_name, 0, 1) }}</span>
                                </div>
                                <div class="ml-3 min-w-0">
                                    <p class="font-medium text-gray-900 truncate">{{ $employee->full_name }}</p>
                                    <p class="text-xs text-gray-500 truncate">
                                        {{ $employee->job_title }}@if($employee->department_name) &middot; {{ $employee->department_name }}@endif
                                    </p>
                                </div>
                            </div>
                            <span class="shrink-0 px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $colorClass }}">
                                {{ $statusText }}
                            </span>
                        </div>

                        {{-- Net pay is what anybody actually looks for, so it is the
                             one given room; the other two sit under it. --}}
                        <div class="mt-3 flex items-end justify-between gap-3">
                            <div>
                                <p class="text-xs text-gray-500">Net pay</p>
                                <p class="text-lg font-semibold text-gray-900">
                                    &#8369;{{ number_format($employee->net_pay ?? 0, 2) }}
                                </p>
                            </div>
                            <div class="text-right text-xs text-gray-500 leading-5">
                                <p>Gross &#8369;{{ number_format($employee->gross_pay ?? 0, 2) }}</p>
                                <p class="text-red-600">Less &#8369;{{ number_format($employee->deductions ?? 0, 2) }}</p>
                            </div>
                        </div>

                        <div class="mt-3 flex flex-wrap gap-2">
                            <button wire:click="viewPayrollDetails({{ $employee->employee_id }})"
                                    class="px-3 py-1.5 text-sm rounded-lg bg-blue-50 text-blue-700">
                                View
                            </button>

                            @if($employee->payroll_status === 'not_processed')
                                <button wire:click="processPayroll({{ $employee->employee_id }})"
                                        class="px-3 py-1.5 text-sm rounded-lg bg-green-50 text-green-700">
                                    Process
                                </button>
                            @elseif($employee->payroll_status === 'calculated')
                                <button wire:click="approvePayroll({{ $employee->employee_id }})"
                                        class="px-3 py-1.5 text-sm rounded-lg bg-yellow-50 text-yellow-700">
                                    Approve
                                </button>
                            @elseif($employee->payroll_status === 'approved')
                                <button wire:click="markAsPaid({{ $employee->employee_id }})"
                                        class="px-3 py-1.5 text-sm rounded-lg bg-green-50 text-green-700">
                                    Mark Paid
                                </button>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="p-6 text-center text-gray-500">
                        No employees found matching your criteria.
                    </div>
                @endforelse
            </div>

            <div class="hidden md:block overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Employee
                            </th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Department & Position
                            </th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Gross Pay
                            </th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Deductions
                            </th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Net Pay
                            </th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Payroll Status
                            </th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($this->employees as $employee)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-4">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10">
                                            <div class="h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center">
                                                <span class="text-blue-600 font-medium">
                                                    {{ substr($employee->full_name, 0, 1) }}
                                                </span>
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">
                                                {{ $employee->full_name }}
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                {{ $employee->email }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="text-sm text-gray-900">{{ $employee->department_name ?? 'N/A' }}</div>
                                    <div class="text-sm text-gray-500">{{ $employee->job_title }}</div>
                                </td>
                                <td class="px-4 py-4">
                                    <span class="text-sm text-gray-900">
                                        ₱{{ number_format($employee->gross_pay ?? 0, 2) }}
                                    </span>
                                </td>
                                <td class="px-4 py-4">
                                    <span class="text-sm text-red-600">
                                        -₱{{ number_format($employee->deductions ?? 0, 2) }}
                                    </span>
                                </td>
                                <td class="px-4 py-4">
                                    <span class="text-sm font-medium text-red-600">
                                        ₱{{ number_format($employee->net_pay ?? 0, 2) }}
                                    </span>
                                </td>
                                <td class="px-4 py-4">
                                    @php
                                        $statusColors = [
                                            'draft' => 'bg-gray-100 text-gray-800',
                                            'calculated' => 'bg-blue-100 text-blue-800',
                                            'approved' => 'bg-yellow-100 text-yellow-800',
                                            'paid' => 'bg-green-100 text-green-800',
                                            'cancelled' => 'bg-gray-100 text-gray-700',
                                            'not_processed' => 'bg-gray-100 text-gray-800',
                                        ];
                                        $payrollStatus = $employee->payroll_status;
                                        $colorClass = $statusColors[$payrollStatus] ?? 'bg-gray-100 text-gray-800';
                                        $statusText = $payrollStatus === 'not_processed' ? 'Not Processed' : ucfirst($payrollStatus);
                                    @endphp
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $colorClass }}">
                                        {{ $statusText }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 text-sm font-medium space-x-2">
                                    <button wire:click="viewPayrollDetails({{ $employee->employee_id }})" 
                                            class="text-blue-600 hover:text-blue-900">
                                        View
                                    </button>
                                    
                                    @if($employee->payroll_status === 'not_processed')
                                        <button wire:click="processPayroll({{ $employee->employee_id }})" 
                                                class="text-green-600 hover:text-green-900">
                                            Process
                                        </button>
                                    @elseif($employee->payroll_status === 'calculated')
                                        <button wire:click="approvePayroll({{ $employee->employee_id }})" 
                                                class="text-yellow-600 hover:text-yellow-900">
                                            Approve
                                        </button>
                                    @elseif($employee->payroll_status === 'approved')
                                        <button wire:click="markAsPaid({{ $employee->employee_id }})" 
                                                class="text-green-600 hover:text-green-900">
                                            Mark Paid
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-4 text-center text-gray-500">
                                    No employees found matching your criteria.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            @if($this->employees->hasPages())
                <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                    {{ $this->employees->links() }}
                </div>
            @endif
        </div>
        
        <!-- Summary Section -->
        <div class="mt-6 bg-gray-50 p-4 rounded-lg">
            <h3 class="text-lg font-medium text-gray-900 mb-3">Pay Period Summary: {{ $this->period()->label() }}</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="text-center p-3 bg-white rounded shadow">
                    <div class="text-2xl font-bold text-blue-600">
                        {{ $this->payrollStats?->total_paid ?? 0 }}
                    </div>
                    <div class="text-sm text-gray-600">
                        Paid of {{ $this->payrollStats?->total_employees ?? 0 }}
                    </div>
                </div>
                <div class="text-center p-3 bg-white rounded shadow">
                    <div class="text-2xl font-bold text-green-600">
                        ₱{{ number_format($this->payrollStats?->total_net ?? 0, 2) }}
                    </div>
                    <div class="text-sm text-gray-600">Total Net Pay</div>
                </div>
                <div class="text-center p-3 bg-white rounded shadow">
                    <div class="text-2xl font-bold text-gray-600">
                        @php
                            $gross = $this->payrollStats?->total_gross ?? 0;
                            $deductions = $this->payrollStats?->total_deductions ?? 0;
                            $rate = $gross > 0 ? ($deductions / $gross * 100) : 0;
                        @endphp
                        {{ round($rate, 1) }}%
                    </div>
                    <div class="text-sm text-gray-600">Average Deduction Rate</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Payroll Details Modal -->
    @if($showPayrollDetails && $selectedEmployee)
        <div class="fixed z-10 inset-0 overflow-y-auto">
            <div class="flex items-end justify-center min-h-screen text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>

                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                {{-- A payslip is two columns of figures, and on a phone it had
                     32px of wrapper padding and 32px of its own before one was
                     drawn. Below sm it takes the whole screen - edge to edge,
                     no rounded corners to waste the sides - and above it goes
                     wider than the 3xl it used to sit in. --}}
                <div class="inline-block align-bottom bg-white text-left shadow-xl transform transition-all
                            w-full min-h-screen px-3 pt-5 pb-10 overflow-y-auto
                            sm:min-h-0 sm:max-h-[92vh] sm:rounded-lg sm:my-8 sm:align-middle
                            sm:max-w-6xl sm:w-full sm:px-8 sm:py-6">
                    <!-- Modal Header -->
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">
                                Payroll Details - {{ $selectedEmployee->full_name }}
                            </h3>
                            <p class="text-sm text-gray-500 mt-1">
                                Period: {{ date('F d, Y', strtotime($selectedEmployee->period_start ?? $this->period()->start)) }} 
                                to {{ date('F d, Y', strtotime($selectedEmployee->period_end ?? $this->period()->end)) }}
                            </p>
                        </div>
                        <button wire:click="closePayrollDetails" type="button" class="text-gray-400 hover:text-gray-500">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    
                    <!-- Employee Information -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6 mb-6">
                        <div>
                            <h4 class="text-sm font-medium text-gray-500 mb-2">Employee Information</h4>
                            <div class="space-y-2">
                                <div>
                                    <span class="text-xs text-gray-500">Employee ID:</span>
                                    <p class="text-sm">{{ $selectedEmployee->employee_id }}</p>
                                </div>
                                <div>
                                    <span class="text-xs text-gray-500">Department:</span>
                                    <p class="text-sm">{{ $selectedEmployee->department_name ?? 'N/A' }}</p>
                                </div>
                                <div>
                                    <span class="text-xs text-gray-500">Position:</span>
                                    <p class="text-sm">{{ $selectedEmployee->job_title }}</p>
                                </div>
                                <div>
                                    <span class="text-xs text-gray-500">Hire Date:</span>
                                    <p class="text-sm">{{ date('M d, Y', strtotime($selectedEmployee->hire_date)) }}</p>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <h4 class="text-sm font-medium text-gray-500 mb-2">Contact Information</h4>
                            <div class="space-y-2">
                                <div>
                                    <span class="text-xs text-gray-500">Email:</span>
                                    <p class="text-sm">{{ $selectedEmployee->email }}</p>
                                </div>
                                <div>
                                    <span class="text-xs text-gray-500">Status:</span>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        {{ $selectedEmployee->employee_status === 'active' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800' }}">
                                        {{ ucfirst($selectedEmployee->employee_status) }}
                                    </span>
                                </div>
                                <div>
                                    <span class="text-xs text-gray-500">Basic Salary:</span>
                                    <p class="text-sm font-medium">₱{{ number_format($selectedEmployee->salary, 2) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Payroll Breakdown -->
                    @if($selectedEmployee->payroll_id)
                        <div class="border-t border-gray-200 pt-4">
                            <h4 class="text-sm font-medium text-gray-500 mb-3">Payroll Breakdown</h4>
                            
                            <!-- Earnings -->
                            <div class="mb-4">
                                <h5 class="text-xs font-medium text-red-600 mb-2">EARNINGS</h5>
                                <div class="space-y-1">
                                    <div class="flex justify-between">
                                        <span class="text-sm">Basic Salary:</span>
                                        <span class="text-sm">₱{{ number_format($selectedEmployee->salary, 2) }}</span>
                                    </div>
                                    @if(($selectedEmployee->overtime_pay ?? 0) > 0)
                                    <div class="flex justify-between"><span class="text-sm">Overtime:</span><span class="text-sm">₱{{ number_format($selectedEmployee->overtime_pay, 2) }}</span></div>
                                    @endif
                                    @if(($selectedEmployee->holiday_pay ?? 0) > 0)
                                    <div class="flex justify-between"><span class="text-sm">Holiday premium:</span><span class="text-sm">₱{{ number_format($selectedEmployee->holiday_pay, 2) }}</span></div>
                                    @endif
                                    @if(($selectedEmployee->nsd_pay ?? 0) > 0)
                                    <div class="flex justify-between"><span class="text-sm">Night shift differential:</span><span class="text-sm">₱{{ number_format($selectedEmployee->nsd_pay, 2) }}</span></div>
                                    @endif
                                    <div class="flex justify-between border-t border-gray-200 pt-1 font-medium">
                                        <span>Total Gross Pay:</span>
                                        <span class="text-red-600">₱{{ number_format($selectedEmployee->gross_pay, 2) }}</span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Philippines-Specific Deductions -->
                            <div class="mb-4">
                                <h5 class="text-xs font-medium text-red-600 mb-2">PHILIPPINES DEDUCTIONS</h5>
                                <div class="space-y-1">
                                    @if($this->payrollBreakdown['sss'] > 0)
                                    <div class="flex justify-between">
                                        <span class="text-sm">SSS Contribution:</span>
                                        <span class="text-sm">-₱{{ number_format($this->payrollBreakdown['sss'], 2) }}</span>
                                    </div>
                                    @endif
                                    @if($this->payrollBreakdown['philhealth'] > 0)
                                    <div class="flex justify-between">
                                        <span class="text-sm">PhilHealth Contribution:</span>
                                        <span class="text-sm">-₱{{ number_format($this->payrollBreakdown['philhealth'], 2) }}</span>
                                    </div>
                                    @endif
                                    @if($this->payrollBreakdown['pagibig'] > 0)
                                    <div class="flex justify-between">
                                        <span class="text-sm">Pag-IBIG Contribution:</span>
                                        <span class="text-sm">-₱{{ number_format($this->payrollBreakdown['pagibig'], 2) }}</span>
                                    </div>
                                    @endif
                                    @if($this->payrollBreakdown['tax'] > 0)
                                    <div class="flex justify-between">
                                        <span class="text-sm">Tax:</span>
                                        <span class="text-sm">-₱{{ number_format($this->payrollBreakdown['tax'], 2) }}</span>
                                    </div>
                                    @endif
                                </div>
                            </div>
                            
                            <!-- Everything else that came off, by name -->
                            @if(($this->payrollBreakdown['time'] ?? 0) > 0 || ($this->payrollBreakdown['loan'] ?? 0) > 0 || $this->payrollBreakdown['other_deductions'] != 0)
                            <div class="mb-4">
                                <h5 class="text-xs font-medium text-gray-500 mb-2">OTHER DEDUCTIONS</h5>
                                <div class="space-y-1">
                                    @if(($this->payrollBreakdown['time'] ?? 0) > 0)
                                        <div class="flex justify-between">
                                            <span class="text-sm">Late, undertime and absence:</span>
                                            <span class="text-sm">-₱{{ number_format($this->payrollBreakdown['time'], 2) }}</span>
                                        </div>
                                    @endif
                                    @if(($this->payrollBreakdown['loan'] ?? 0) > 0)
                                        <div class="flex justify-between">
                                            <span class="text-sm">Loan repayment:</span>
                                            <span class="text-sm">-₱{{ number_format($this->payrollBreakdown['loan'], 2) }}</span>
                                        </div>
                                    @endif
                                    @if($this->payrollBreakdown['other_deductions'] != 0)
                                        <div class="flex justify-between">
                                            <span class="text-sm">Unaccounted for:</span>
                                            <span class="text-sm">-₱{{ number_format($this->payrollBreakdown['other_deductions'], 2) }}</span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                            @endif
                            
                            <div class="mb-4 border-t border-gray-200 pt-3">
                                <h5 class="text-xs font-medium text-gray-500 mb-2">EMPLOYER COST</h5>
                                <div class="grid grid-cols-2 gap-1 text-sm">
                                    <span>SSS + EC</span><span class="text-right">₱{{ number_format((float)($selectedEmployee->employer_sss ?? 0) + (float)($selectedEmployee->employer_ec ?? 0), 2) }}</span>
                                    <span>PhilHealth</span><span class="text-right">₱{{ number_format((float)($selectedEmployee->employer_philhealth ?? 0), 2) }}</span>
                                    <span>Pag-IBIG</span><span class="text-right">₱{{ number_format((float)($selectedEmployee->employer_pagibig ?? 0), 2) }}</span>
                                </div>
                                @if($selectedEmployee->statutory_rule_version)
                                    <p class="mt-2 text-xs text-gray-500">Rules: {{ $selectedEmployee->statutory_rule_version }}</p>
                                @endif
                            </div>

                            <!-- Total Summary -->
                            <div class="bg-gray-50 p-3 rounded-lg">
                                <div class="flex justify-between mb-1">
                                    <span class="font-medium">Total Deductions:</span>
                                    <span class="font-medium text-red-600">-₱{{ number_format($selectedEmployee->deductions, 2) }}</span>
                                </div>
                                <div class="flex justify-between text-lg font-bold">
                                    <span>NET PAY:</span>
                                    <span class="text-red-600">₱{{ number_format($selectedEmployee->net_pay, 2) }}</span>
                                </div>
                            </div>
                            
                            <!-- Payroll Status -->
                            <div class="mt-4">
                                <div class="flex justify-between items-center">
                                    <div>
                                        <span class="text-xs text-gray-500">Payroll Status:</span>
                                        @php
                                            $statusColors = [
                                                'draft' => 'bg-gray-100 text-gray-800',
                                                'calculated' => 'bg-blue-100 text-blue-800',
                                                'approved' => 'bg-yellow-100 text-yellow-800',
                                                'paid' => 'bg-green-100 text-green-800',
                                            ];
                                            $payrollStatus = $selectedEmployee->status ?? 'draft';
                                            $colorClass = $statusColors[$payrollStatus] ?? 'bg-gray-100 text-gray-800';
                                        @endphp
                                        <span class="ml-2 px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $colorClass }}">
                                            {{ ucfirst($payrollStatus) }}
                                        </span>
                                    </div>
                                    <div class="space-x-2">
                                        @if($selectedEmployee->status === 'calculated')
                                            <button wire:click="approvePayroll({{ $selectedEmployee->employee_id }})" 
                                                    class="px-3 py-1 bg-yellow-100 text-yellow-800 text-sm rounded hover:bg-yellow-200">
                                                Approve
                                            </button>
                                        @endif
                                        @if($selectedEmployee->status === 'approved')
                                            <button wire:click="markAsPaid({{ $selectedEmployee->employee_id }})" 
                                                    class="px-3 py-1 bg-green-100 text-green-800 text-sm rounded hover:bg-green-200">
                                                Mark as Paid
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="text-center py-8">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">No payroll data</h3>
                            <p class="mt-1 text-sm text-gray-500">Payroll has not been processed for this period.</p>
                            <div class="mt-6">
                                <button wire:click="processPayroll({{ $selectedEmployee->employee_id }})" 
                                        type="button" 
                                        class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    Process Payroll
                                </button>
                            </div>
                        </div>
                    @endif
                    
                    @if($selectedEmployee->payroll_id)
                        <div class="mt-4 border-t border-gray-200 pt-4">
                            <a href="{{ route('payslip.show', $selectedEmployee->payroll_id) }}" target="_blank"
                               class="btn-secondary inline-flex items-center gap-2">
                                <i class="fas fa-print"></i> Open printable payslip
                            </a>
                        </div>
                    @endif

                    <!-- Notes -->
                    @if($selectedEmployee->notes)
                    <div class="mt-4 border-t border-gray-200 pt-4">
                        <h4 class="text-sm font-medium text-gray-500 mb-2">Notes</h4>
                        <p class="text-sm text-gray-700 bg-gray-50 p-2 rounded">{{ $selectedEmployee->notes }}</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
