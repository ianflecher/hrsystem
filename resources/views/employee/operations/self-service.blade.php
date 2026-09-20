<x-layouts.employeeland :title="'Employee Self-Service'">
<div class="p-6 md:p-8 max-w-7xl mx-auto space-y-6">
    @if(session('success')) <div class="rounded-lg bg-green-50 text-green-800 px-4 py-3">{{ session('success') }}</div> @endif
    <div><h1 class="text-2xl font-bold">Employee Self-Service</h1><p class="text-gray-500">Welcome, {{ $employee->full_name }}.</p></div>
    <div class="grid md:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl shadow p-5"><div class="text-sm text-gray-500">Latest Net Pay</div><div class="text-2xl font-bold">₱{{ number_format((float)($payslips->first()->net_pay ?? 0),2) }}</div></div>
        <div class="bg-white rounded-xl shadow p-5"><div class="text-sm text-gray-500">Pending Leave</div><div class="text-2xl font-bold">{{ $leaves->where('status','pending')->count() }}</div></div>
        <div class="bg-white rounded-xl shadow p-5"><div class="text-sm text-gray-500">Unread Notices</div><div class="text-2xl font-bold">{{ $notifications->whereNull('read_at')->count() }}</div></div>
        <div class="bg-white rounded-xl shadow p-5"><div class="text-sm text-gray-500">Attendance Corrections</div><div class="text-2xl font-bold">{{ $corrections->where('status','pending')->count() }}</div></div>
    </div>
    <div class="grid lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl shadow p-6"><h2 class="font-semibold mb-4">Request HR Service</h2>
            <form method="POST" action="{{ route('employee.operations.request') }}" class="space-y-3">@csrf
                <select name="type" class="w-full rounded-lg border-gray-300"><option value="coe">Certificate of Employment</option><option value="document">Document Request</option><option value="profile_change">Profile Change</option></select>
                <input name="request_date" type="date" class="w-full rounded-lg border-gray-300">
                <textarea name="details" required placeholder="Describe your request" class="w-full rounded-lg border-gray-300"></textarea>
                <button class="px-4 py-2 rounded-lg bg-gray-900 text-white">Submit Request</button>
            </form>
        </div>
        <div class="bg-white rounded-xl shadow p-6"><h2 class="font-semibold mb-4">Attendance Correction</h2>
            <form method="POST" action="{{ route('employee.operations.attendance-correction') }}" class="space-y-3">@csrf
                <input name="attendance_date" type="date" required class="w-full rounded-lg border-gray-300">
                <div class="grid grid-cols-2 gap-3"><input name="requested_time_in" type="time" class="rounded-lg border-gray-300"><input name="requested_time_out" type="time" class="rounded-lg border-gray-300"></div>
                <textarea name="reason" required placeholder="Reason for correction" class="w-full rounded-lg border-gray-300"></textarea>
                <button class="px-4 py-2 rounded-lg bg-gray-900 text-white">Submit Correction</button>
            </form>
        </div>
    </div>
    <div class="grid lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl shadow overflow-hidden"><div class="p-5 border-b font-semibold">Recent Payslips</div><div class="divide-y">@forelse($payslips as $p)<div class="p-4 flex justify-between"><span>{{ $p->period_start }} – {{ $p->period_end }}</span><span class="font-semibold">₱{{ number_format((float)$p->net_pay,2) }}</span></div>@empty<div class="p-5 text-gray-500">No payslips yet.</div>@endforelse</div></div>
        <div class="bg-white rounded-xl shadow overflow-hidden"><div class="p-5 border-b font-semibold">Notifications</div><div class="divide-y">@forelse($notifications as $n)<div class="p-4"><div class="flex justify-between"><b>{{ $n->title }}</b>@if(!$n->read_at)<form method="POST" action="{{ route('employee.operations.notification.read',$n->id) }}">@csrf @method('PATCH')<button class="text-xs text-blue-600">Mark read</button></form>@endif</div><p class="text-sm text-gray-600">{{ $n->message }}</p></div>@empty<div class="p-5 text-gray-500">No notifications.</div>@endforelse</div></div>
    </div>
</div>
</x-layouts.employeeland>
