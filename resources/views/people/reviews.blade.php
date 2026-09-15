@if($hr)
<details class="card" @if($errors->any()) open @endif><summary>Open a performance review</summary>
<form method="POST" action="{{ $base }}" class="grid divider">@csrf
    @include('people.employee-select')
    <x-people.field name="period_start" label="Review period starts" type="date" />
    <x-people.field name="period_end" label="Review period ends" type="date" />
    <x-people.field name="due_on" label="Review due date" type="date" />
    <div><button>Open review</button></div>
</form></details>
@endif
@forelse($rows as $row)
<article class="card"><div class="row between"><h2>{{ $row->full_name }}</h2><span class="badge">{{ $row->status }}</span></div>
<p class="muted">{{ $row->period_start }} to {{ $row->period_end }} · Due {{ $row->due_on }}</p>
<h2>Goals</h2>
@forelse($extra['goals']->get($row->id, collect()) as $goal)
<div class="divider"><p>{{ $goal->title }} · <strong>{{ $goal->progress }}%</strong></p><progress value="{{ $goal->progress }}" max="100" aria-label="{{ $goal->title }} progress" style="width:100%"></progress>
@if($row->status !== 'finalized')<form method="POST" action="{{ $base }}/{{ $row->id }}" class="row">@csrf<input type="hidden" name="goal_id" value="{{ $goal->id }}"><label>Progress % <input type="number" name="progress" value="{{ $goal->progress }}" min="0" max="100" required></label><button name="action" value="progress" class="secondary">Update progress</button></form>@endif</div>
@empty<p class="muted">No goals assigned yet.</p>@endforelse
@if($hr && $row->status !== 'finalized')<form method="POST" action="{{ $base }}/{{ $row->id }}" class="grid divider">@csrf<x-people.field name="title" label="New goal / measurable target" maxlength="200" /><div><button name="action" value="goal" class="secondary">Add goal</button></div></form>@endif
<div class="divider"><h2>Self-assessment</h2><p class="prose">{{ $row->self_assessment ?: 'Not submitted yet.' }}</p></div>
@if(!$hr && $row->status !== 'finalized')<form method="POST" action="{{ $base }}/{{ $row->id }}">@csrf<label class="people-field"><span>Achievements, challenges and development needs</span><textarea name="self_assessment" minlength="10" maxlength="10000" required>{{ $row->self_assessment }}</textarea></label><button name="action" value="self-assessment">Submit assessment</button></form>@endif
<div class="divider"><h2>Evaluation</h2><p class="prose">{{ $row->feedback ?: 'Evaluation pending.' }}</p>@if($row->rating)<p><strong>Rating: {{ $row->rating }}/5</strong> · Finalized {{ $row->finalized_at }}</p>@endif</div>
@if($hr && $row->status !== 'finalized')<form method="POST" action="{{ $base }}/{{ $row->id }}" class="grid" onsubmit="return confirm('Finalize and lock this review?')">@csrf
<label class="people-field"><span>Rating</span><select name="rating" required><option value="">Choose rating</option><option value="1">1 · Needs significant improvement</option><option value="2">2 · Developing</option><option value="3">3 · Meets expectations</option><option value="4">4 · Exceeds expectations</option><option value="5">5 · Outstanding</option></select></label>
<label class="people-field wide"><span>Feedback and next steps</span><textarea name="feedback" minlength="10" maxlength="10000" required></textarea></label><div><button name="action" value="finalize">Finalize review</button></div></form>@endif
</article>
@empty<div class="card muted">No performance reviews yet. Completed reviews remain here as history.</div>@endforelse
