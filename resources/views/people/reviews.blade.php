@use('App\Services\ReviewSummary')

@if($hr)
    {{-- HR writes the review. The employee answers notices and reads the
         result - see the other half of this file. --}}
    <details class="card" @if($errors->any()) open @endif><summary>Start a review</summary>
        <form method="POST" action="{{ $base }}" class="grid divider">@csrf
            @include('people.employee-select')
            <x-people.field name="period_start" label="Period from" type="date" />
            <x-people.field name="period_end" label="Period to" type="date" />
            <x-people.field name="due_on" label="Due date" type="date" />
            <p class="muted wide">The attendance figures are read from the record for that period. Notices are added below.</p>
            <div><button>Create review</button></div>
        </form>
    </details>
@else
    {{-- Notices first: one of these carries a deadline, and it is the only
         thing on this page the employee has to do something about. --}}
    @php($awaiting = $extra['awaiting'] ?? collect())
    @php($termination = $extra['termination'] ?? null)

    @if($termination)
        <article class="card">
            <h2>Notice of Termination</h2>
            <p class="alert error">
                Your employment ends on <strong>{{ $termination->effective_on }}</strong>.
            </p>
            <p class="prose">{{ $termination->allegation }}</p>
            <p class="muted">
                Issued {{ substr((string) $termination->issued_at, 0, 10) }}
                @if($termination->issued_by_name) by {{ $termination->issued_by_name }} @endif
                · following the notice you were asked to explain on {{ $termination->occurred_on }}.
            </p>
            <p class="muted">
                Speak to HR about your final pay and clearance. Your payslips stay available here.
            </p>
        </article>
    @endif

    @if($awaiting->isNotEmpty())
        <article class="card">
            <h2>Notice to explain</h2>
            <p class="muted">
                You have been asked to explain the following in writing. This is a request for your
                side of it, not a decision - nothing is decided until HR has read your answer.
            </p>

            @foreach($awaiting as $notice)
                <div class="divider">
                    <div class="row between">
                        <strong>{{ ReviewSummary::TYPES[$notice->type] ?? $notice->type }}</strong>
                        <span class="badge">{{ $notice->occurred_on }}</span>
                    </div>

                    <p class="prose">{{ $notice->allegation }}</p>

                    <p class="{{ ReviewSummary::isOverdue($notice) ? 'alert warning' : 'muted' }}">
                        @if(ReviewSummary::isOverdue($notice))
                            Your answer was due {{ $notice->respond_by }}. Send it as soon as you can.
                        @else
                            Please answer by {{ $notice->respond_by }}.
                        @endif
                        @if($notice->issued_by_name) · Issued by {{ $notice->issued_by_name }} @endif
                    </p>

                    <form method="POST" action="{{ route('people.notices.act', $notice->id) }}" class="grid">@csrf
                        <input type="hidden" name="action" value="explain">
                        <label class="people-field wide"><span>Your explanation</span>
                            <textarea name="explanation" required minlength="10" maxlength="10000"
                                      placeholder="What happened, in your own words."></textarea>
                        </label>
                        <div><button>Send explanation</button></div>
                    </form>
                </div>
            @endforeach
        </article>
    @endif
@endif

@forelse($rows as $row)
    @php($attendance = $extra['attendance'][$row->id] ?? null)
    @php($notices = $extra['notices'][$row->id] ?? collect())

    <article class="card">
        <div class="row between">
            <h2>{{ $hr ? $row->full_name : 'Your review' }}</h2>
            <span class="badge">{{ $row->status === 'finalized' ? ReviewSummary::grade($row->rating) : ucfirst($row->status) }}</span>
        </div>

        <p class="muted">
            {{ $row->period_start }} to {{ $row->period_end }}
            @if($row->status !== 'finalized') · Due {{ $row->due_on }} @endif
        </p>

        {{-- Read from the attendance record rather than remembered by anybody. --}}
        @if($attendance)
            <div class="divider">
                <h3>Attendance for this period</h3>
                <div class="scroll"><table>
                    <thead><tr><th>Days worked</th><th>On time</th><th>Late</th><th>Undertime</th><th>Absent</th><th>On leave</th></tr></thead>
                    <tbody><tr>
                        <td>{{ $attendance['days'] }}</td>
                        <td>{{ $attendance['onTime'] }}</td>
                        <td>{{ $attendance['late'] }}</td>
                        <td>{{ $attendance['undertime'] }}</td>
                        <td>{{ $attendance['absent'] }}</td>
                        <td>{{ $attendance['leave'] }}</td>
                    </tr></tbody>
                </table></div>
            </div>
        @endif

        <div class="divider">
            <h3>Notices</h3>

            @forelse($notices as $notice)
                <div class="goal">
                    @if($notice->kind === 'not')
                        <div class="row between">
                            <strong>Notice of Termination · effective {{ $notice->effective_on }}</strong>
                            <span class="badge">Issued</span>
                        </div>
                        <p class="prose">{{ $notice->allegation }}</p>
                        @continue
                    @endif

                    <div class="row between">
                        <strong>{{ ReviewSummary::TYPES[$notice->type] ?? $notice->type }} · {{ $notice->occurred_on }}</strong>
                        <span class="badge">{{ ReviewSummary::STATUSES[$notice->status] ?? $notice->status }}</span>
                    </div>

                    <p class="prose">{{ $notice->allegation }}</p>

                    @if($notice->explanation)
                        <p class="muted">Explanation, {{ substr((string) $notice->explained_at, 0, 10) }}:</p>
                        <p class="prose">{{ $notice->explanation }}</p>
                    @endif

                    @if($notice->status === 'closed')
                        <p><strong>Decision: {{ ReviewSummary::decision($notice->decision) }}</strong>
                            @if($notice->decided_by_name) · {{ $notice->decided_by_name }} @endif</p>
                        <p class="prose">{{ $notice->decision_notes }}</p>

                        @php($issued = ($extra['terminations'] ?? collect())->get($notice->id))

                        @if($issued)
                            <p class="alert warning">
                                Notice of Termination issued · effective {{ $issued->effective_on }}
                            </p>
                        @elseif($hr && ReviewSummary::canTerminate($notice))
                            {{-- Only reachable from a case that was explained and
                                 decided as termination. --}}
                            <details class="divider"><summary>Issue the Notice of Termination</summary>
                                <form method="POST" action="{{ route('people.notices.act', $notice->id) }}" class="grid divider"
                                      onsubmit="return confirm('Issue a Notice of Termination? This ends their employment and cannot be undone here.')">@csrf
                                    <input type="hidden" name="action" value="terminate">
                                    <x-people.field name="effective_on" label="Last day of employment" type="date" />
                                    <label class="people-field wide"><span>Grounds, as the employee will read them</span>
                                        <textarea name="allegation" required minlength="10" maxlength="5000"></textarea>
                                    </label>
                                    <p class="muted wide">
                                        This is the second notice. It marks them terminated and appears on their own screen.
                                    </p>
                                    <div><button class="danger">Issue notice</button></div>
                                </form>
                            </details>
                        @endif
                    @elseif($hr)
                        @if($notice->status === 'issued')
                            <p class="muted">
                                Waiting for their explanation, due {{ $notice->respond_by }}.
                                A decision can only be recorded once they have answered, or once that date has passed.
                            </p>
                        @endif

                        @if($notice->status === 'explained' || ReviewSummary::isOverdue($notice))
                            <form method="POST" action="{{ route('people.notices.act', $notice->id) }}" class="grid divider">@csrf
                                <input type="hidden" name="action" value="decide">
                                <label class="people-field"><span>Decision</span>
                                    <select name="decision">
                                        @foreach(ReviewSummary::DECISIONS as $key => $label)
                                            <option value="{{ $key }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="people-field wide"><span>Reasons</span>
                                    <textarea name="decision_notes" required minlength="10" maxlength="10000"></textarea>
                                </label>
                                <div><button>Record decision</button></div>
                            </form>
                        @endif
                    @endif
                </div>
            @empty
                <p class="muted">No notices in this period.</p>
            @endforelse

            @if($hr && $row->status !== 'finalized')
                <details class="divider"><summary>Issue a notice to explain</summary>
                    <form method="POST" action="{{ $base }}/{{ $row->id }}" class="grid divider">@csrf
                        <input type="hidden" name="action" value="notice">
                        <x-people.field name="occurred_on" label="Date it happened" type="date" />
                        <label class="people-field"><span>About</span>
                            <select name="type">
                                @foreach(ReviewSummary::TYPES as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="people-field wide"><span>What they are being asked to explain</span>
                            <textarea name="allegation" required minlength="10" maxlength="5000"></textarea>
                        </label>
                        <x-people.field name="respond_by" label="Answer by"
                                        type="date" :value="today()->addDays(ReviewSummary::DAYS_TO_RESPOND)->toDateString()" />
                        <p class="muted wide">They see this on their own screen and answer it there.</p>
                        <div><button>Issue notice</button></div>
                    </form>
                </details>
            @endif
        </div>

        <div class="divider">
            <h3>Evaluation</h3>

            @if($row->status === 'finalized')
                <p><strong>Overall grade: {{ ReviewSummary::grade($row->rating) }} ({{ $row->rating }}/5)</strong></p>
                <p class="prose">{{ $row->feedback }}</p>
                <p class="muted">Finalised {{ substr((string) $row->finalized_at, 0, 10) }}</p>
            @elseif($hr)
                <form method="POST" action="{{ $base }}/{{ $row->id }}" class="grid"
                      onsubmit="return confirm('Finalise this review? It cannot be edited afterwards.')">@csrf
                    <input type="hidden" name="action" value="finalize">
                    <label class="people-field"><span>Overall grade</span>
                        <select name="rating">
                            @foreach(ReviewSummary::GRADES as $value => $label)
                                <option value="{{ $value }}">{{ $value }} · {{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="people-field wide"><span>Remarks</span>
                        <textarea name="feedback" required minlength="10" maxlength="10000"></textarea>
                    </label>
                    <p class="muted wide">Once finalised the employee sees this, and it can no longer be changed.</p>
                    <div><button>Finalise review</button></div>
                </form>
            @else
                <p class="muted">Evaluation pending.</p>
            @endif
        </div>
    </article>
@empty
    <div class="card muted">
        {{ $hr ? 'No reviews yet.' : 'You have no completed reviews yet. One will appear here when HR finalises it.' }}
    </div>
@endforelse
