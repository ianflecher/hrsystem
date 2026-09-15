{{-- One checklist, drawn the same way on both portals.
     $list is the employee_checklists row, $items its tasks. --}}
@php($items = $extra['items']->get($list->id, collect()))
@php($done = $items->whereNotNull('completed_at')->count())
<div class="divider">
    <div class="row between">
        <strong>{{ ucfirst($list->type) }}</strong>
        <span class="badge">{{ $list->completed_at ? 'Completed' : $done.'/'.$items->count().' done' }}</span>
    </div>
    <p class="{{ ! $list->completed_at && $list->due_on < today()->toDateString() ? 'alert warning' : 'muted' }}">
        Due {{ $list->due_on }}@if($list->completed_at) · completed {{ \Illuminate\Support\Carbon::parse($list->completed_at)->toDateString() }}@endif
    </p>

    @foreach($items as $item)
        <div class="row between">
            <div>{{ $item->completed_at ? '✓' : '○' }} {{ $item->title }} <span class="badge">{{ $item->owner }}</span></div>
            @if(! $list->completed_at && ($hr || $item->owner === 'employee'))
                <form method="POST" action="{{ $base }}/{{ $list->id }}">@csrf
                    <input type="hidden" name="item_id" value="{{ $item->id }}">
                    <button class="secondary" name="action" value="toggle">{{ $item->completed_at ? 'Reopen task' : 'Mark done' }}</button>
                </form>
            @endif
        </div>
    @endforeach

    @if($hr && ! $list->completed_at)
        <form method="POST" action="{{ $base }}/{{ $list->id }}" class="grid divider">@csrf
            <x-people.field name="title" label="Additional task" maxlength="200" />
            <label class="people-field"><span>Responsible party</span>
                <select name="owner"><option value="hr">HR</option><option value="employee">Employee</option></select>
            </label>
            <div><button name="action" value="add" class="secondary">Add task</button></div>
        </form>
        @if($items->whereNull('completed_at')->isEmpty())
            <form method="POST" action="{{ $base }}/{{ $list->id }}">@csrf
                <button name="action" value="complete">Complete {{ $list->type === 'offboarding' ? 'clearance' : 'onboarding' }}</button>
            </form>
        @endif
    @endif
</div>
