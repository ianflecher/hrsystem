<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * The interviews this person has been given, and what they made of them.
 *
 * HR schedules an interview and names who will conduct it. Until this screen
 * existed that name was written by HR and read back only by HR, so a
 * supervisor could be handed an interview with no way to know, and no way to
 * say afterwards whether they would take the person on.
 *
 * Scoped to the signed-in interviewer throughout. A supervisor sees the
 * candidates given to them and nothing else of the pipeline - not the other
 * applicants, not the other interviewers' notes, not the decision.
 *
 * The 201 file is shown without the disclosures: health, medication and
 * criminal history stay with HR. The interview is about the person, their
 * schooling and their work.
 */
new #[Layout('components.layouts.employeeland')] class extends Component
{
    /** Which interview is open, by interview id. Null closes the panel. */
    public ?int $openId = null;

    public string $recommendation = '';
    public string $notes = '';

    /** Filled for whichever interview is open; the 201 partial reads these. */
    public $profile = null;
    public array $education = [];
    public array $employment = [];
    public array $references = [];
    public array $siblings = [];
    public array $relatives = [];
    public $disclosures = null;   // never rendered here; the partial is told not to

    public array $educationLabels = [
        'elementary'  => 'Elementary',
        'junior_high' => 'Junior high',
        'senior_high' => 'Senior high',
        'high_school' => 'High school',
        'vocational'  => 'Vocational',
        'tertiary'    => 'College / tertiary',
    ];

    public array $verdicts = [
        'recommend'     => 'Recommend',
        'not_recommend' => 'Do not recommend',
        'undecided'     => 'Undecided',
    ];

    /**
     * The rounds given to this person - one row each, so being asked back for
     * a second interview is two entries rather than one overwriting the other.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    public function interviews()
    {
        return DB::table('application_interviews as ai')
            ->select(
                'ai.*',
                'ja.application_id', 'ja.position_applied', 'ja.status as application_status',
                'u.full_name', 'u.email', 'u.user_id as applicant_user_id',
            )
            ->join('job_applications as ja', 'ai.application_id', '=', 'ja.application_id')
            ->join('users as u', 'ja.user_id', '=', 'u.user_id')
            ->where('ai.interviewer_id', Auth::id())
            ->where('ai.status', '!=', 'cancelled')
            ->orderBy('ai.scheduled_at')
            ->get();
    }

    /** Still to happen, and not yet answered: what the screen leads with. */
    public function upcomingCount(): int
    {
        return $this->interviews()->filter(fn ($i) => $i->recommendation === null)->count();
    }

    public function open(int $interviewId): void
    {
        $row = $this->mine($interviewId);

        $this->openId = $interviewId;
        $this->recommendation = (string) ($row->recommendation ?? '');
        $this->notes = (string) ($row->recommendation_notes ?? '');
        $this->resetValidation();

        $applicantId = DB::table('job_applications')->where('application_id', $row->application_id)->value('user_id');
        $this->loadProfile((int) $applicantId);
    }

    public function close(): void
    {
        $this->openId = null;
        $this->profile = null;
        $this->education = $this->employment = $this->references = [];
        $this->siblings = $this->relatives = [];
    }

    public function record(): void
    {
        $row = $this->mine((int) $this->openId);

        $this->validate([
            'recommendation' => ['required', 'in:'.implode(',', array_keys($this->verdicts))],
            'notes'          => ['nullable', 'string', 'max:2000'],
        ], [], ['recommendation' => 'recommendation']);

        // Against this round, not against the application - so a later round
        // by somebody else cannot end up wearing this person's words.
        DB::table('application_interviews')->where('interview_id', $row->interview_id)->update([
            'recommendation'       => $this->recommendation,
            'recommendation_notes' => $this->notes === '' ? null : $this->notes,
            'recommended_at'       => now(),
            // It has plainly happened if there is a verdict on it.
            'status'               => 'completed',
            'updated_at'           => now(),
        ]);

        $this->close();
        session()->flash('success', 'Your recommendation has been recorded. HR can see it on the application.');
    }

    /**
     * The row, only if it was given to the person asking.
     *
     * Every action goes through here rather than trusting the id that arrived
     * from the browser - otherwise a supervisor could read and write any
     * application by changing a number.
     */
    private function mine(int $interviewId): object
    {
        $row = DB::table('application_interviews')
            ->where('interview_id', $interviewId)
            ->where('interviewer_id', Auth::id())
            ->first();

        abort_unless($row, 403);

        return $row;
    }

    private function loadProfile(int $userId): void
    {
        $this->profile = DB::table('applicant_profiles')->where('user_id', $userId)->first();
        $this->education = $this->employment = $this->references = [];
        $this->siblings = $this->relatives = [];
        $this->disclosures = null;

        if (! $this->profile) {
            return;
        }

        $this->education  = DB::table('applicant_education')->where('user_id', $userId)->get()->all();
        $this->employment = DB::table('applicant_employment')->where('user_id', $userId)->orderBy('sort_order')->get()->all();
        $this->references = DB::table('applicant_references')->where('user_id', $userId)->orderBy('sort_order')->get()->all();
        $this->siblings   = DB::table('applicant_siblings')->where('user_id', $userId)->orderBy('sort_order')->get()->all();
    }

    /** The order a 201 file is read in, not the order the table stores. */
    public function educationInOrder(): array
    {
        $rows = collect($this->education)->keyBy('level');

        return collect(array_keys($this->educationLabels))->map(fn ($l) => $rows->get($l))->filter()->all();
    }
}; ?>

{{-- Picks up an interview booked while this page was already open.

     Suspended whenever a panel is open, and that is not a nicety: wire:model
     is deferred, so a half-typed recommendation lives only in the browser
     until something is called. A poll landing mid-sentence would re-render the
     textarea from the server and take the sentence with it.

     .visible so a tab left open in the background stops asking. --}}
<div class="p-6 md:p-8 max-w-[90rem] mx-auto"
     @if ($openId === null) wire:poll.30s.visible @endif>
    <div class="mb-5">
        <h1 class="text-2xl font-semibold text-gray-900">My interviews</h1>
        <p class="text-sm text-gray-600 mt-1">
            Candidates HR has asked you to interview. Read their details before you meet them,
            and say afterwards whether you would take them on.
        </p>
    </div>

    @if (session('success'))
        <div class="mb-5 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
            {{ session('success') }}
        </div>
    @endif

    @php $rows = $this->interviews(); @endphp

    @if ($rows->isEmpty())
        <div class="rounded-xl border border-dashed border-gray-300 bg-white px-6 py-12 text-center">
            <i class="fas fa-calendar-day text-3xl text-gray-300"></i>
            <p class="mt-3 font-medium text-gray-700">No interviews assigned to you.</p>
            <p class="text-sm text-gray-500">HR will schedule one and put your name on it.</p>
        </div>
    @else
        <div class="space-y-4">
            @foreach ($rows as $row)
                @php
                    $when = \Illuminate\Support\Carbon::parse($row->scheduled_at);
                    $done = $row->recommendation !== null;
                    $past = $when->isPast();
                @endphp

                <div class="bg-white rounded-xl border border-gray-200 shadow-sm" wire:key="iv-{{ $row->interview_id }}">
                    <div class="px-5 py-4 flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <div class="flex items-center gap-2">
                                <h2 class="font-semibold text-gray-900">{{ $row->full_name }}</h2>
                                {{-- Which round this is. A candidate asked back is two
                                     entries now, not one written over the other. --}}
                                @if ($row->round > 1)
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-blue-100 text-blue-800">
                                        Round {{ $row->round }}
                                    </span>
                                @endif
                                @if ($done)
                                    <span class="text-xs px-2 py-0.5 rounded-full
                                        {{ $row->recommendation === 'recommend' ? 'bg-green-100 text-green-800' : '' }}
                                        {{ $row->recommendation === 'not_recommend' ? 'bg-red-100 text-red-800' : '' }}
                                        {{ $row->recommendation === 'undecided' ? 'bg-gray-100 text-gray-700' : '' }}">
                                        {{ $verdicts[$row->recommendation] }}
                                    </span>
                                @elseif ($past)
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-amber-100 text-amber-800">
                                        Waiting on you
                                    </span>
                                @endif
                            </div>
                            <p class="text-sm text-gray-600">{{ $row->position_applied }}</p>
                        </div>

                        <div class="text-sm text-right">
                            <p class="font-medium text-gray-900">{{ $when->format('D j M Y, g:ia') }}</p>
                            <p class="text-gray-500">
                                {{ ucfirst(str_replace('_', ' ', (string) $row->type)) }}
                                @if (! $past) &middot; {{ $when->diffForHumans() }} @endif
                            </p>
                        </div>
                    </div>

                    {{-- What they said last time, without having to open it again. --}}
                    @if ($done && trim((string) $row->recommendation_notes) !== '')
                        <div class="px-5 pb-3 -mt-1">
                            <div class="rounded bg-gray-50 p-3 text-sm text-gray-700">
                                {{ $row->recommendation_notes }}
                                @if ($row->recommended_at)
                                    <span class="block text-xs text-gray-500 mt-1">
                                        Recorded {{ \Illuminate\Support\Carbon::parse($row->recommended_at)->format('j M Y, g:ia') }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    @endif

                    {{-- HR's own note when they booked it - what they want asked. --}}
                    @if (trim((string) $row->hr_notes) !== '')
                        <div class="px-5 pb-3 -mt-1">
                            <p class="text-sm text-gray-600">
                                <span class="text-gray-400">From HR:</span> {{ $row->hr_notes }}
                            </p>
                        </div>
                    @endif

                    <div class="px-5 py-3 border-t border-gray-100 flex flex-wrap gap-2">
                        @if ($openId === $row->interview_id)
                            <button type="button" wire:click="close" class="btn-secondary text-sm">
                                <i class="fas fa-xmark mr-2"></i>Close
                            </button>
                        @else
                            <button type="button" wire:click="open({{ $row->interview_id }})" class="btn-primary text-sm">
                                <i class="fas fa-id-card mr-2"></i>{{ $done ? 'View and revise' : 'Open details' }}
                            </button>
                        @endif
                    </div>

                    @if ($openId === $row->interview_id)
                        <div class="px-5 pb-5 border-t border-gray-100 pt-4">
                            {{-- Health, medication and criminal history are not shown: they
                                 stay with HR. Nor is the print link, which is theirs. --}}
                            @include('partials.hr-application-201', [
                                'selectedApplication' => $row,
                                'showDisclosures'     => false,
                                'showPrint'           => false,
                            ])

                            <div class="mt-5 rounded-lg border border-gray-200 p-4">
                                <h3 class="font-medium text-gray-900">Your recommendation</h3>
                                <p class="text-sm text-gray-600 mt-0.5">
                                    HR makes the decision; this is what they will weigh.
                                </p>

                                <div class="flex flex-wrap gap-5 mt-3">
                                    @foreach ($verdicts as $value => $label)
                                        <label class="flex items-center gap-2 text-sm">
                                            <input type="radio" value="{{ $value }}" wire:model="recommendation">
                                            {{ $label }}
                                        </label>
                                    @endforeach
                                </div>
                                @error('recommendation') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror

                                <div class="mt-3">
                                    <label class="form-label" for="notes-{{ $row->interview_id }}">Notes</label>
                                    <textarea id="notes-{{ $row->interview_id }}" wire:model="notes" rows="3"
                                              class="form-input" placeholder="How the interview went, and why."></textarea>
                                    @error('notes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>

                                <div class="mt-3 flex items-center gap-3">
                                    <button type="button" wire:click="record" class="btn-primary">
                                        {{ $done ? 'Update recommendation' : 'Record recommendation' }}
                                    </button>
                                    @if ($row->recommended_at)
                                        <span class="text-sm text-gray-500">
                                            Recorded {{ \Illuminate\Support\Carbon::parse($row->recommended_at)->format('j M Y, g:ia') }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
