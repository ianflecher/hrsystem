<?php

use App\Services\CutoffAttendanceSummary;
use App\Support\PayPeriod;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

/*
 * One cutoff at a glance: who was late, absent, short, on leave, or never
 * punched out - checked before payroll is generated.
 */
new #[Layout('components.layouts.humanresource')] class extends Component
{
    public string $cutoff = '';
    public string $company = '';
    public string $department = '';
    public string $search = '';
    public string $show = 'issues';

    public function mount(): void
    {
        $this->cutoff = PayPeriod::recent(1)[0]->start;
    }

    public function getCutoffsProperty(): array
    {
        return PayPeriod::recent(12);
    }

    public function getRowsProperty(): array
    {
        $rows = (new CutoffAttendanceSummary)->forPeriod(PayPeriod::fromStart($this->cutoff),
            $this->company ?: null, $this->department ? (int) $this->department : null);

        return array_values(array_filter($rows, function ($r) {
            if ($this->search !== '' && ! str_contains(mb_strtolower($r['name'].' '.$r['employee_no']), mb_strtolower($this->search))) return false;

            return match ($this->show) {
                'late' => $r['late_count'] > 0,
                'absent' => $r['absent'] > 0,
                'no_out' => count($r['no_out']) > 0,
                'suspended' => $r['suspended'] > 0,
                'issues' => $r['late_count'] || $r['absent'] || $r['undertime'] || $r['no_out'] || $r['unpaid_leave'] || $r['suspended'],
                default => true,
            };
        }));
    }

    public function export()
    {
        $period = PayPeriod::fromStart($this->cutoff);
        $all = (new CutoffAttendanceSummary)->forPeriod($period, $this->company ?: null, $this->department ? (int) $this->department : null);
        $rows = array_map(fn ($r) => [
            $r['employee_no'], $r['name'], $r['department'], $r['company'], $r['worked'],
            $r['late_count'], $r['late_minutes'], $r['late_penalty_hours'], implode(', ', $r['late_days']),
            $r['absent'], implode(', ', $r['absent_days']), $r['suspended'], implode(', ', $r['suspended_days']), $r['undertime'], implode(', ', $r['undertime_days']), $r['paid_leave'], $r['unpaid_leave'], implode(', ', $r['unpaid_leave_days']), count($r['no_out']), implode(', ', $r['no_out']),
            $r['rest_worked'], $r['ot_approved'], $r['ot_pending'],
        ], $all);

        return \App\Support\SpreadsheetWriter::download(
            'attendance-summary-'.$period->start.'.xlsx', 'Summary '.$period->start,
            ['ID', 'Name', 'Department', 'Company', 'Days worked', 'Times late', 'Late minutes', 'Late deduction (hours)', 'Late days',
                'Absent', 'Absent days', 'Suspended', 'Suspended days', 'Undertime', 'Undertime days', 'Paid leave', 'Unpaid leave', 'Unpaid leave days', 'No time-out', 'No time-out days',
                'Rest days worked', 'OT approved (h)', 'OT pending (h)'],
            $rows);
    }

    public function with(): array
    {
        $rows = $this->rows;

        return [
            'rows' => $rows,
            'period' => PayPeriod::fromStart($this->cutoff),
            'departments' => DB::table('departments')->orderBy('department_name')->get(['department_id', 'department_name']),
            'totals' => [
                'late_people' => count(array_filter($rows, fn ($r) => $r['late_count'] > 0)),
                'late_times' => array_sum(array_column($rows, 'late_count')),
                'absent' => array_sum(array_column($rows, 'absent')),
                'suspended' => array_sum(array_column($rows, 'suspended')),
                'no_out' => array_sum(array_map(fn ($r) => count($r['no_out']), $rows)),
                'unpaid_leave' => array_sum(array_column($rows, 'unpaid_leave')),
            ],
        ];
    }
};
?>

<div class="p-6 md:p-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        <div class="md:flex md:items-end md:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">Attendance summary</h2>
                <p class="mt-1 text-sm text-gray-500">Per cutoff: who was late, absent, short, on leave, or never punched out. Days up to yesterday.</p>
            </div>
            <button wire:click="export" class="mt-4 md:mt-0 inline-flex items-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                <i class="fas fa-file-excel text-green-600"></i> Export to Excel
            </button>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-6 gap-3">
            @foreach ([
                ['Late (people)', $totals['late_people'], 'text-amber-700 bg-amber-50', 'late'],
                ['Times late', $totals['late_times'], 'text-amber-700 bg-amber-50', 'late'],
                ['Absences', $totals['absent'], 'text-red-700 bg-red-50', 'absent'],
                ['Suspended days', $totals['suspended'], 'text-gray-800 bg-gray-200', 'suspended'],
                ['No time-out', $totals['no_out'], 'text-purple-700 bg-purple-50', 'no_out'],
                ['Unpaid leave days', $totals['unpaid_leave'], 'text-orange-700 bg-orange-50', 'issues'],
            ] as [$label, $value, $tone, $filter])
                <button wire:click="$set('show', '{{ $filter }}')" class="rounded-xl border border-gray-100 bg-white p-4 text-left shadow-sm hover:border-gray-300">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $label }}</div>
                    <div class="mt-1 inline-block rounded-lg px-2 text-2xl font-bold {{ $tone }}">{{ $value }}</div>
                </button>
            @endforeach
        </div>

        <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100 grid grid-cols-1 md:grid-cols-5 gap-3">
            <label class="text-sm"><span class="form-label">Cutoff</span>
                <select wire:model.live="cutoff" class="form-input">
                    @foreach ($this->cutoffs as $c)
                        <option value="{{ $c->start }}">{{ $c->label() }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm"><span class="form-label">Company</span>
                <select wire:model.live="company" class="form-input">
                    <option value="">Both</option>
                    <option value="GKLASAM OPC">GKLASAM OPC</option>
                    <option value="Imprint Cafe">Imprint Cafe</option>
                </select>
            </label>
            <label class="text-sm"><span class="form-label">Department</span>
                <select wire:model.live="department" class="form-input">
                    <option value="">All</option>
                    @foreach ($departments as $d)
                        <option value="{{ $d->department_id }}">{{ $d->department_name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm"><span class="form-label">Show</span>
                <select wire:model.live="show" class="form-input">
                    <option value="issues">Anyone with something to check</option>
                    <option value="late">Late only</option>
                    <option value="absent">Absent only</option>
                    <option value="suspended">Suspended only</option>
                    <option value="no_out">No time-out only</option>
                    <option value="all">Everyone</option>
                </select>
            </label>
            <label class="text-sm"><span class="form-label">Search</span>
                <input type="search" wire:model.live.debounce.300ms="search" class="form-input" placeholder="Name or ID">
            </label>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-x-auto" wire:loading.class="opacity-50">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-4 py-3 text-left">Employee</th>
                        <th class="px-3 py-3 text-center">Worked</th>
                        <th class="px-3 py-3 text-left">Late</th>
                        <th class="px-3 py-3 text-center">Absent</th>
                        <th class="px-3 py-3 text-center">Suspended</th>
                        <th class="px-3 py-3 text-center">Undertime</th>
                        <th class="px-3 py-3 text-center">Leave</th>
                        <th class="px-3 py-3 text-left">No time-out</th>
                        <th class="px-3 py-3 text-center">Overtime</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($rows as $r)
                        <tr class="align-top hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-900">{{ $r['name'] }}</div>
                                <div class="text-xs text-gray-500">{{ $r['employee_no'] }} · {{ $r['department'] ?: 'No department' }}@if($r['no_scanner']) · <span class="text-orange-600">no scanner ID</span>@endif</div>
                            </td>
                            <td class="px-3 py-3 text-center">{{ $r['worked'] }}@if($r['rest_worked'])<div class="text-xs text-gray-500">{{ $r['rest_worked'] }} on rest day</div>@endif</td>
                            <td class="px-3 py-3">
                                @if ($r['late_count'])
                                    <div class="font-semibold text-amber-700">{{ $r['late_count'] }}× · {{ $r['late_minutes'] }} min</div>
                                    @if ($r['late_penalty_hours'])<div class="text-xs text-red-600">−{{ rtrim(rtrim(number_format($r['late_penalty_hours'], 1), '0'), '.') }} h from basic</div>@endif
                                    <div class="text-xs text-gray-500 max-w-xs whitespace-normal">{{ implode(', ', $r['late_days']) }}</div>
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-3 text-center">@if($r['absent'])<span class="font-semibold text-red-700">{{ $r['absent'] }}</span><div class="text-xs text-gray-500 max-w-[9rem] mx-auto whitespace-normal">{{ implode(', ', $r['absent_days']) }}</div>@else<span class="text-gray-300">—</span>@endif</td>
                            <td class="px-3 py-3 text-center">@if($r['suspended'])<span class="rounded bg-gray-200 px-2 font-semibold text-gray-800">{{ $r['suspended'] }}</span><div class="text-xs text-gray-500 max-w-[9rem] mx-auto whitespace-normal">{{ implode(', ', $r['suspended_days']) }}</div>@else<span class="text-gray-300">—</span>@endif</td>
                            <td class="px-3 py-3 text-center">@if($r['undertime'])<span class="font-semibold text-orange-700">{{ $r['undertime'] }}</span><div class="text-xs text-gray-500 max-w-[9rem] mx-auto whitespace-normal">{{ implode(', ', $r['undertime_days']) }}</div>@else<span class="text-gray-300">—</span>@endif</td>
                            <td class="px-3 py-3 text-center whitespace-nowrap">
                                @if($r['paid_leave'])<span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-800">{{ $r['paid_leave'] }} paid</span>@endif
                                @if($r['unpaid_leave'])<span class="rounded-full bg-orange-100 px-2 py-0.5 text-xs font-semibold text-orange-800">{{ $r['unpaid_leave'] }} unpaid</span><div class="text-xs text-gray-500">{{ implode(', ', $r['unpaid_leave_days']) }}</div>@endif
                                @if(! $r['paid_leave'] && ! $r['unpaid_leave'])<span class="text-gray-300">—</span>@endif
                            </td>
                            <td class="px-3 py-3">@if($r['no_out'])<span class="font-semibold text-purple-700">{{ count($r['no_out']) }}</span> <span class="text-xs text-gray-500">{{ implode(', ', $r['no_out']) }}</span>@else<span class="text-gray-300">—</span>@endif</td>
                            <td class="px-3 py-3 text-center whitespace-nowrap">
                                @if($r['ot_approved'])<div class="text-xs"><span class="font-semibold">{{ rtrim(rtrim(number_format($r['ot_approved'], 2), '0'), '.') }} h</span> approved</div>@endif
                                @if($r['ot_pending'])<div class="text-xs text-gray-500">{{ rtrim(rtrim(number_format($r['ot_pending'], 2), '0'), '.') }} h waiting</div>@endif
                                @if(! $r['ot_approved'] && ! $r['ot_pending'])<span class="text-gray-300">—</span>@endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-4 py-10 text-center text-gray-500">Nobody to show for {{ $period->label() }}.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p class="text-xs text-gray-500">Late: more than 5 minutes after the scheduled start (6-15 minutes costs 1 hour, 16 or more half a day; guards are paid per duty and not docked). Absent: a scheduled working day with no scan, leave, official business or suspension. Suspended: set by the supervisor or HR, unpaid. Counted the same way payroll counts them.</p>
    </div>
</div>
