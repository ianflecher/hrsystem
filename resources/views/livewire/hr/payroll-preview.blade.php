<?php
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Support\PayPeriod;

new #[Layout('components.layouts.humanresource')] #[Title('Payroll Preview')] class extends Component
{
    public string $payPeriod=''; public array $preview=[];
    public function mount(){ \App\Support\PeopleAccess::hr(); $this->payPeriod=PayPeriod::recent(1)[0]->start; $this->refreshPreview(); }
    public function updatedPayPeriod(){ $this->refreshPreview(); }
    public function refreshPreview(){ $this->preview=app(\App\Services\PayrollPreview::class)->period(PayPeriod::fromStart($this->payPeriod)); }
    public function generateExceptions(){ $n=app(\App\Services\PayrollPreview::class)->rebuildExceptions(PayPeriod::fromStart($this->payPeriod)); session()->flash('success',"Created {$n} new payroll exceptions."); $this->refreshPreview(); }
    public function getPeriods(){ return array_map(fn($p)=>['value'=>$p->start,'label'=>$p->label()],PayPeriod::recent(12)); }
}
?>
<div class="p-6 md:p-8">
 <div class="flex flex-wrap justify-between gap-4 mb-6"><div><h1 class="text-2xl font-bold">Payroll Preview & Exceptions</h1><p class="text-sm text-gray-600 mt-1">Review projected payroll before generating or approving payslips.</p></div><select wire:model.live="payPeriod" class="form-input w-64">@foreach($this->getPeriods() as $p)<option value="{{ $p['value'] }}">{{ $p['label'] }}</option>@endforeach</select></div>
 @if(session('success'))<div class="mb-4 p-3 rounded-lg bg-green-50 text-green-800">{{ session('success') }}</div>@endif
 <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">@foreach([['Employees',$preview['totals']['employees']??0],['Gross',$preview['totals']['gross']??0],['Deductions',$preview['totals']['deductions']??0],['Net',$preview['totals']['net']??0],['Employer cost',$preview['totals']['employer_cost']??0]] as $card)<div class="bg-white border rounded-xl p-4"><div class="text-xs text-gray-500">{{ $card[0] }}</div><div class="text-xl font-bold mt-1">{{ $card[0]==='Employees' ? $card[1] : '₱'.number_format($card[1],2) }}</div></div>@endforeach</div>
 <div class="bg-white border rounded-xl p-5 mb-6"><div class="flex justify-between items-center mb-4"><h2 class="font-semibold">Exceptions</h2><button wire:click="generateExceptions" class="btn-secondary">Save exceptions</button></div>@forelse($preview['exceptions']??[] as $employeeId=>$messages)<div class="border-t py-3"><div class="font-medium">Employee #{{ $employeeId }}</div><ul class="list-disc ml-5 text-sm text-red-700">@foreach($messages as $m)<li>{{ $m }}</li>@endforeach</ul></div>@empty<div class="text-sm text-green-700">No preview exceptions detected.</div>@endforelse</div>
 <div class="bg-white border rounded-xl p-5 overflow-auto"><h2 class="font-semibold mb-4">Projected employees</h2><table class="min-w-full text-sm"><thead><tr class="text-left text-gray-500"><th class="py-2">Employee</th><th>Basis</th><th>Paid days</th><th>Gross</th><th>Deductions</th><th>Net</th></tr></thead><tbody>@foreach($preview['rows']??[] as $r)<tr class="border-t"><td class="py-2">#{{ $r['employee_id'] }}</td><td>{{ ucfirst($r['pay_basis']??'monthly') }}</td><td>{{ number_format($r['paid_days']??0,2) }}</td><td>₱{{ number_format($r['gross']??0,2) }}</td><td>₱{{ number_format($r['deductions']??0,2) }}</td><td>₱{{ number_format($r['net']??0,2) }}</td></tr>@endforeach</tbody></table></div>
</div>
