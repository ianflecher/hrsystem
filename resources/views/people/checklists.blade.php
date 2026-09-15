@if($hr)
<details class="card" @if($errors->any()) open @endif><summary>Start a checklist</summary>
<form method="POST" action="{{ $base }}" class="grid divider">@csrf
    @include('people.employee-select')
    <label class="people-field"><span>Checklist type</span><select name="type"><option value="onboarding">Onboarding</option><option value="offboarding">Offboarding</option></select></label>
    <x-people.field name="due_on" label="Due date" type="date" />
    <p class="muted wide">Starts with a checklist for documents, equipment, access and handover. Add company-specific tasks below.</p>
    <div><button>Create checklist</button></div>
</form></details>
@endif
@forelse($rows as $row)
@php($items = $extra['items']->get($row->id, collect()))
<article class="card"><div class="row between"><h2>{{ $row->full_name }} · {{ ucfirst($row->type) }}</h2><span class="badge">{{ $row->completed_at ? 'Completed' : 'In progress' }}</span></div>
<p class="{{ !$row->completed_at && $row->due_on < today()->toDateString() ? 'alert warning' : 'muted' }}">Due {{ $row->due_on }} · {{ $items->whereNotNull('completed_at')->count() }}/{{ $items->count() }} tasks complete</p>
@foreach($items as $item)
<div class="row between divider"><div>{{ $item->completed_at ? '✓' : '○' }} {{ $item->title }} <span class="badge">{{ $item->owner }}</span></div>
@if(!$row->completed_at && ($hr || $item->owner === 'employee'))<form method="POST" action="{{ $base }}/{{ $row->id }}">@csrf<input type="hidden" name="item_id" value="{{ $item->id }}"><button class="secondary" name="action" value="toggle">{{ $item->completed_at ? 'Reopen task' : 'Mark done' }}</button></form>@endif</div>
@endforeach
@if($hr && !$row->completed_at)
<form method="POST" action="{{ $base }}/{{ $row->id }}" class="grid divider">@csrf
    <x-people.field name="title" label="Additional task" maxlength="200" />
    <label class="people-field"><span>Responsible party</span><select name="owner"><option value="hr">HR</option><option value="employee">Employee</option></select></label>
    <div><button name="action" value="add" class="secondary">Add task</button></div>
</form>
@if($items->whereNull('completed_at')->isEmpty())<form method="POST" action="{{ $base }}/{{ $row->id }}">@csrf<button name="action" value="complete">Complete {{ $row->type === 'offboarding' ? 'clearance' : 'onboarding' }}</button></form>@endif
@endif
</article>
@empty<div class="card muted">No checklists yet.</div>@endforelse
