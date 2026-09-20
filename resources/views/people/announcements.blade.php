@if($hr)
<details class="card" @if($errors->any()) open @endif><summary>Publish announcement</summary>
    <form method="POST" action="{{ $base }}" class="grid divider">@csrf
        <x-people.field name="title" label="Title" maxlength="160" />
        <label class="people-field wide"><span>Body</span><textarea name="body" required minlength="5" maxlength="10000">{{ old('body') }}</textarea></label>
        <label class="people-field"><span>Audience</span>
            <select name="department_id">
                <option value="">Everyone</option>
                @foreach($departments as $department)<option value="{{ $department->department_id }}">{{ $department->department_name }}</option>@endforeach
            </select>
        </label>
        <x-people.field name="published_at" label="Publish at" type="datetime-local" :required="false" />
        <x-people.field name="expires_at" label="Expires at" type="datetime-local" :required="false" />
        <div><button>Publish</button></div>
    </form>
</details>
@endif

@forelse($rows as $row)
<article class="card">
    <div class="row between">
        <h2>{{ $row->title }}</h2>
        <span class="badge">{{ $row->archived ? 'archived' : ($row->department_name ?: 'everyone') }}</span>
    </div>
    <p class="prose">{{ $row->body }}</p>
    <p class="muted">
        {{ $row->published_at ? \Illuminate\Support\Carbon::parse($row->published_at)->format('j M Y, g:i A') : 'Draft' }}
        @if($row->expires_at) · until {{ \Illuminate\Support\Carbon::parse($row->expires_at)->format('j M Y') }} @endif
        @if($hr) · {{ (int) ($extra['readCounts'][$row->id] ?? 0) }} acknowledgement(s) @endif
    </p>
    @if($hr)
        <form method="POST" action="{{ $base }}/{{ $row->id }}" class="divider">@csrf
            <button name="action" value="{{ $row->archived ? 'restore' : 'archive' }}" class="secondary">{{ $row->archived ? 'Restore' : 'Archive' }}</button>
        </form>
    @elseif(!$row->acknowledged_at)
        <form method="POST" action="{{ $base }}/{{ $row->id }}" class="divider">@csrf
            <button name="action" value="acknowledge">Acknowledge</button>
        </form>
    @else
        <p class="muted">Acknowledged {{ \Illuminate\Support\Carbon::parse($row->acknowledged_at)->format('j M Y, g:i A') }}</p>
    @endif
</article>
@empty
    <div class="card muted">No announcements yet.</div>
@endforelse
