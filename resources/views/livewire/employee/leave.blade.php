<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

new #[Layout('components.layouts.employeeland')] class extends Component
{
    public string $leave_type = 'vacation';
    public string $pay_status = 'paid';
    public string $start_date = '';
    public string $end_date = '';
    public int $total_days = 0;
    public string $reason = '';

    /**
     * Get logged-in employee_id (NOT user_id)
     */
    public function employeeId()
    {
        $id = DB::table('employees')
            ->where('user_id', Auth::id())
            ->value('employee_id');

        // Null here meant filing leave against nobody, and reading properties
        // off the rows it failed to find. HR and admin have no employee record.
        abort_unless($id, 403, 'An employee record is required.');

        return $id;
    }

    /**
     * Leave history
     */
    /** What this employee has left to take this year, by type. */
    public function getBalancesProperty(): array
    {
        return (new \App\Services\LeaveBalances)->forEmployee($this->employeeId());
    }

    public function getLeavesProperty()
    {
        return DB::table('leaves')
            ->where('employee_id', $this->employeeId())
            ->orderByDesc('created_at')
            ->get();
    }

    protected function rules()
    {
        return [
            'leave_type' => 'required|in:vacation,sick,emergency,maternity,paternity,bereavement,unpaid',
            'pay_status' => 'required|in:paid,unpaid',
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
            'reason'     => 'required|min:5',
        ];
    }

    public function updatedStartDate()
    {
        $this->calculateDays();
    }

    public function updatedEndDate()
    {
        $this->calculateDays();
    }

    private function calculateDays()
    {
        if ($this->start_date && $this->end_date) {
            $this->total_days =
                Carbon::parse($this->start_date)
                    ->diffInDays(Carbon::parse($this->end_date)) + 1;
        } else {
            $this->total_days = 0;
        }
    }

    public function submit()
    {
        abort(403, 'Leave is filed by the supervisor or team leader.');

        $this->validate();

        $employeeId = $this->employeeId();

        if (!$employeeId) {
            abort(403, 'Employee record not found.');
        }

        $payStatus = (new \App\Services\LeaveBalances)->payStatusForRequest(
            $employeeId,
            $this->leave_type,
            (float) $this->total_days,
            $this->pay_status,
            (int) substr($this->start_date, 0, 4)
        );

        // A supervisor's leave is decided by the supervisors' approver
        // (config/leave.php) before HR, never by their own team. The
        // approver's own leave goes straight to HR.
        $status = (int) auth()->id() === (int) config('leave.supervisor_approver_user_id') ? 'pending_hr' : 'pending';
        $row = fn (string $pay, string $from, string $to, float $days) => [
            'employee_id' => $employeeId,
            'leave_type'  => $this->leave_type,
            'pay_status'  => $pay,
            'start_date'  => $from,
            'end_date'    => $to,
            'total_days'  => $days,
            'reason'      => $this->reason,
            'status'      => $status,
            'created_at'  => now(),
            'updated_at'  => now(),
        ];

        // Leave paid per occasion (bereavement): the first days are paid and
        // anything past them is filed alongside as unpaid.
        $paidDays = config('leave.per_occasion.'.$this->leave_type) !== null
            ? (int) ((new \App\Services\LeaveBalances)->forEmployee($employeeId)[$this->leave_type]['entitled'] ?? config('leave.per_occasion.'.$this->leave_type))
            : null;
        if ($paidDays && $this->pay_status === 'paid' && $this->total_days > $paidDays) {
            $split = Carbon::parse($this->start_date)->addDays($paidDays);
            DB::table('leaves')->insert([
                $row('paid', $this->start_date, $split->copy()->subDay()->toDateString(), (float) $paidDays),
                $row('unpaid', $split->toDateString(), $this->end_date, (float) $this->total_days - $paidDays),
            ]);
            $payStatus = 'split';
        } elseif ($this->pay_status === 'paid' && $payStatus === 'unpaid'
            && ($left = (new \App\Services\LeaveBalances)->paidDaysFor($employeeId, $this->leave_type, (float) $this->total_days, (int) substr($this->start_date, 0, 4))) > 0) {
            // More days than the paid balance has left: the balance's worth paid, the rest unpaid.
            $paidDays = (int) $left;
            $split = Carbon::parse($this->start_date)->addDays($paidDays);
            DB::table('leaves')->insert([
                $row('paid', $this->start_date, $split->copy()->subDay()->toDateString(), (float) $paidDays),
                $row('unpaid', $split->toDateString(), $this->end_date, (float) $this->total_days - $paidDays),
            ]);
            $payStatus = 'split';
        } else {
            DB::table('leaves')->insert($row($payStatus, $this->start_date, $this->end_date, (float) $this->total_days));
        }

        session()->flash('success', $payStatus === 'split'
            ? 'Leave request submitted: the first '.$paidDays.' day(s) are paid - all the paid leave left - and the rest is unpaid.'
            : ($payStatus === $this->pay_status
            ? 'Leave request submitted successfully.'
            : 'Leave request submitted as unpaid because no paid balance is available for that leave type.'));

        $this->reset(['leave_type', 'pay_status', 'start_date', 'end_date', 'total_days', 'reason']);
        $this->leave_type = 'vacation';
        $this->pay_status = 'paid';
    }
};
?>

<div class="p-6 md:p-8">
    <div class="max-w-7xl mx-auto space-y-8">
        
        <!-- HEADER -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">
                    Leave Management
                </h1>
                <p class="text-gray-600 mt-2">Track leave requests filed by your supervisor or team leader</p>
            </div>

        </div>

        {{-- Before the form, not after: knowing the balance is what decides
             whether the request is worth making. --}}
        @php($balances = collect($this->balances)->filter(fn ($b) => $b['entitled'] !== null))
        @if($balances->isNotEmpty())
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h2 class="text-lg font-bold text-gray-900">Your leave this year</h2>
                <div class="mt-4 grid grid-cols-2 md:grid-cols-4 gap-4">
                    @foreach($balances as $type => $balance)
                        <div class="rounded-xl border border-gray-200 p-4">
                            <div class="text-xs uppercase tracking-wide text-gray-500">{{ ucfirst($type) }}</div>
                            <div class="mt-1 text-2xl font-bold text-gray-900">
                                {{ rtrim(rtrim(number_format(max(0, $balance['remaining']), 1), '0'), '.') }}
                            </div>
                            <div class="text-xs text-gray-500">
                                of {{ rtrim(rtrim(number_format($balance['entitled'], 1), '0'), '.') }} days left
                                @if($balance['pending'] > 0)
                                    <span class="block text-amber-700">
                                        {{ rtrim(rtrim(number_format($balance['pending'], 1), '0'), '.') }} awaiting a decision
                                    </span>
                                @endif
                                @unless($balance['eligible'])
                                    <span class="block text-amber-700">
                                        Earned after {{ $balance['afterMonths'] }} months
                                    </span>
                                @endunless
                            </div>
                        </div>
                    @endforeach
                </div>
                <p class="mt-3 text-xs text-gray-500">
                    Days awaiting a decision are already held against the paid balance. Ordinary paid leave with no remaining balance is submitted as unpaid.
                </p>
            </div>
        @endif

        <!-- MAIN GRID -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <!-- LEAVE REQUEST INFO -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-8 py-6 border-b border-gray-200">
                        <h2 class="text-xl font-bold text-gray-900">Leave requests</h2>
                        <p class="text-sm text-gray-600 mt-1">Your supervisor or team leader files leave for you. You can monitor the status on this page.</p>
                    </div>

                    @if (session()->has('success'))
                        <div class="mx-6 mt-6 p-4 rounded-xl bg-gray-50 border border-green-200 shadow-sm animate-pulse-once">
                            <div class="flex items-center">
                                <svg class="w-5 h-5 text-green-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                <span class="font-semibold text-emerald-700">{{ session('success') }}</span>
                            </div>
                        </div>
                    @endif

                    <div class="p-8">
                        <div class="rounded-xl border border-blue-100 bg-blue-50 p-5 text-sm text-blue-900">
                            Talk to your supervisor or team leader when you need leave. Once they file it, it appears in the history below with the current approval status.
                        </div>
                    </div>
                </div>
            </div>

            <!-- QUICK STATS & GUIDELINES -->
            <div class="space-y-6">
                <!-- Stats -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h3 class="text-xl font-bold text-gray-800 mb-4">Leave Overview</h3>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between p-3 rounded-xl bg-slate-100">
                            <div class="flex items-center">
                                <div class="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center mr-3">
                                    <span class="text-emerald-600 font-bold">{{ $this->leaves->whereIn('status', ['pending', 'pending_hr'])->count() }}</span>
                                </div>
                                <span class="font-medium text-gray-700">Pending</span>
                            </div>
                            <div class="w-2 h-6 bg-yellow-500 rounded-full"></div>
                        </div>
                        <div class="flex items-center justify-between p-3 rounded-xl bg-green-50">
                            <div class="flex items-center">
                                <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center mr-3">
                                    <span class="text-emerald-600 font-bold">{{ $this->leaves->where('status', 'approved')->count() }}</span>
                                </div>
                                <span class="font-medium text-gray-700">Approved</span>
                            </div>
                            <div class="w-2 h-6 bg-green-500 rounded-full"></div>
                        </div>
                        <div class="flex items-center justify-between p-3 rounded-xl bg-green-50">
                            <div class="flex items-center">
                                <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center mr-3">
                                    <span class="text-green-600 font-bold">{{ $this->leaves->count() }}</span>
                                </div>
                                <span class="font-medium text-gray-700">Total Requests</span>
                            </div>
                            <div class="w-1.5 h-5 bg-red-600 rounded-full"></div>
                        </div>
                    </div>
                </div>

                <!-- Guidelines -->
                <div class="bg-gray-50 rounded-xl border border-gray-200 p-6">
                    <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-slate-500" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                        </svg>
                        Quick Guidelines
                    </h3>
                    <ul class="space-y-3">
                        <li class="flex items-start">
                            <div class="w-1.5 h-1.5 bg-slate-400 rounded-full mt-2 mr-3"></div>
                            <span class="text-sm text-gray-700">Inform your supervisor at least 3 days in advance when possible</span>
                        </li>
                        <li class="flex items-start">
                            <div class="w-1.5 h-1.5 bg-slate-400 rounded-full mt-2 mr-3"></div>
                            <span class="text-sm text-gray-700">Attach medical certificate for sick leave</span>
                        </li>
                        <li class="flex items-start">
                            <div class="w-1.5 h-1.5 bg-slate-400 rounded-full mt-2 mr-3"></div>
                            <span class="text-sm text-gray-700">Check with your team before submission</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- LEAVE HISTORY TABLE -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-8 py-6 border-b border-gray-200">
                <h2 class="text-xl font-bold text-gray-900">Leave History</h2>
                <p class="text-emerald-500 mt-1">Track all your leave requests and their status</p>
            </div>

            <div class="p-6">
                @if($this->leaves->isNotEmpty())
                    <div class="overflow-x-auto rounded-xl border border-gray-200">
                        <table class="min-w-full divide-y divide-red-100">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-4 text-left text-sm font-bold text-red-800 uppercase tracking-wider">Type</th>
                                    <th class="px-6 py-4 text-left text-sm font-bold text-red-800 uppercase tracking-wider">Period</th>
                                    <th class="px-6 py-4 text-left text-sm font-bold text-red-800 uppercase tracking-wider">Days</th>
                                    <th class="px-6 py-4 text-left text-sm font-bold text-emerald-800 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-4 text-left text-sm font-bold text-red-800 uppercase tracking-wider">Reason</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-red-50">
                                @foreach ($this->leaves as $leave)
                                    <tr class="hover:bg-red-50/50 transition duration-150">
                                        <td class="px-6 py-4 flex items-center">
                                            <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center mr-3">@switch($leave->leave_type) @case('vacation') @break @case('sick') @break @case('emergency') @break @case('maternity') @break @case('paternity') ‍ @break @case('bereavement') @break @case('study') @break @case('unpaid') @break @default @endswitch</div>
                                            <span class="font-medium text-gray-700">{{ ucfirst($leave->leave_type) }}</span>
                                        </td>
                                        <td class="px-6 py-4 text-gray-600">{{ $leave->start_date }} - {{ $leave->end_date }}</td>
                                        <td class="px-6 py-4 font-bold text-red-700">{{ $leave->total_days }}</td>
                                        <td class="px-6 py-4">
                                            @if($leave->status === 'approved')
                                                <span class="bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs font-semibold">Approved</span>
                                            @elseif($leave->status === 'pending')
                                                <span class="bg-yellow-100 text-yellow-700 px-3 py-1 rounded-full text-xs font-semibold">Supervisor Review</span>
                                            @elseif($leave->status === 'pending_hr')
                                                <span class="bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs font-semibold">Waiting for Ma'am An</span>
                                            @else
                                                <span class="bg-gray-100 text-gray-700 px-3 py-1 rounded-full text-xs font-semibold">{{ ucfirst(str_replace('_', ' ', $leave->status)) }}</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 text-gray-600">{{ $leave->reason }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-gray-500 text-center py-8">You have no leave history yet.</p>
                @endif
            </div>
        </div>

    </div>
</div>
