@if($hr)
    {{-- The roster, not a queue of paperwork. Everybody on staff is listed, so
         "no checklist started" is something you can see rather than something
         you have to remember to look for. --}}
    <form method="GET" class="card row">
        <label class="people-field" style="flex:1">
            <span>Find an employee</span>
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Name">
        </label>
        <div><button class="secondary">Search</button></div>
        @if(request('search') || request('all'))<div><a class="button secondary" href="{{ $base }}">Back to what needs doing</a></div>@endif
        @if(! request('search') && ! request('all'))<div><a class="button secondary" href="{{ $base }}?all=1">Show everyone</a></div>@endif
    </form>

    @unless(request('search') || request('all'))
        <p class="muted">New starters, anyone leaving, and checklists still in progress. Everyone else is under “Show everyone”.</p>
    @endunless

    @forelse($rows as $person)
        @php($lists = $extra['checklists']->get($person->employee_id, collect()))
        <article class="card">
            <div class="row between">
                <h2>{{ $person->full_name }}</h2>
                <span>
                    <span class="badge">{{ $person->job_title }}</span>
                    @if($person->status !== 'active')<span class="badge">{{ str_replace('_', ' ', $person->status) }}</span>@endif
                </span>
            </div>

            @php($leaving = in_array($person->status, \App\Http\Controllers\PeopleController::LEAVING, true))
            @php($needs = $leaving ? 'offboarding' : 'onboarding')

            {{-- Said whether or not they have other checklists: somebody who has
                 left still needs clearing even with a finished onboarding. --}}
            @unless($lists->contains('type', $needs))
                <p class="muted">
                    No {{ $needs }} checklist ·
                    {{ $leaving ? 'has left and has not been cleared' : 'started '.\Illuminate\Support\Carbon::parse($person->hire_date)->diffForHumans() }}
                </p>
            @endunless

            @foreach($lists as $list)
                @include('people.checklist-body')
            @endforeach

            <details class="divider"><summary>Start a checklist</summary>
                <form method="POST" action="{{ $base }}" class="grid divider">@csrf
                    <input type="hidden" name="employee_id" value="{{ $person->employee_id }}">
                    <label class="people-field"><span>Checklist type</span>
                        <select name="type">
                            <option value="onboarding" @selected($needs === 'onboarding')>Onboarding</option>
                            <option value="offboarding" @selected($needs === 'offboarding')>Offboarding</option>
                        </select>
                    </label>
                    <x-people.field name="due_on" label="Due date" type="date" />
                    <p class="muted wide">Starts with tasks for documents, equipment, access and handover. Add anything company-specific once it exists.</p>
                    <div><button>Create checklist</button></div>
                </form>
            </details>
        </article>
    @empty
        <div class="card muted">No employees{{ request('search') ? ' match that name' : ' yet' }}.</div>
    @endforelse
@else
    @forelse($rows as $list)
        <article class="card">@include('people.checklist-body')</article>
    @empty
        <div class="card muted">No checklists yet.</div>
    @endforelse
@endif
