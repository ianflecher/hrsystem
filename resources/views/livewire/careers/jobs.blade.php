<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Support\CareersMedia;

/*
 * Every opening, with the search and the filters.
 *
 * The filters live in the querystring so a search can be linked to and shared,
 * which is how people actually pass a job around - and it is how the search box
 * on the home page hands over to this page.
 */
new #[Layout('components.layouts.careers', ['onDarkHero' => true])] class extends Component
{
    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'type', except: '')]
    public string $employmentType = '';

    #[Url(as: 'dept', except: '')]
    public string $department = '';

    public array $employmentTypes = [
        'full_time'  => 'Full-time',
        'part_time'  => 'Part-time',
        'contract'   => 'Contract',
        'internship' => 'Internship',
    ];

    public function jobs()
    {
        return DB::table('job_positions as p')
            ->leftJoin('departments as d', 'p.department_id', '=', 'd.department_id')
            ->where('p.is_open', true)
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(fn ($w) => $w->where('p.title', 'like', $term)
                    ->orWhere('p.description', 'like', $term)
                    ->orWhere('d.department_name', 'like', $term));
            })
            ->when($this->employmentType !== '', fn ($q) => $q->where('p.employment_type', $this->employmentType))
            ->when($this->department !== '', fn ($q) => $q->where('d.department_name', $this->department))
            ->select('p.position_id', 'p.title', 'p.employment_type', 'p.description', 'p.image_path', 'p.created_at', 'd.department_name')
            ->orderByDesc('p.created_at')
            ->get();
    }

    public function departments()
    {
        return DB::table('job_positions as p')
            ->join('departments as d', 'p.department_id', '=', 'd.department_id')
            ->where('p.is_open', true)
            ->distinct()
            ->orderBy('d.department_name')
            ->pluck('d.department_name');
    }

    /**
     * The ways in, each with a live count, the way Uniqlo opens its jobs page:
     * "Early Careers - 3 open positions". A pathway is a filter that is worth
     * naming, so it is a link into the list below rather than a page of its own.
     *
     * Only pathways with something behind them are listed. An empty one is a
     * door into a room with nothing in it.
     *
     * @return list<array{label: string, note: string, count: int, href: string}>
     */
    public function pathways(): array
    {
        $paths = [];

        $earlyTypes = ['internship', 'part_time'];

        $early = DB::table('job_positions')
            ->where('is_open', true)
            ->whereIn('employment_type', $earlyTypes)
            ->count();

        if ($early > 0) {
            $paths[] = [
                'label' => 'Early careers',
                'note'  => 'OJT, working students and part-time',
                'count' => $early,
                'href'  => route('careers.jobs', ['type' => 'internship']).'#openings',
                'pic'   => CareersMedia::pic('path-early') ?: CareersMedia::pic('welcome-part-time'),
            ];
        }

        $byTeam = DB::table('job_positions as p')
            ->join('departments as d', 'p.department_id', '=', 'd.department_id')
            ->where('p.is_open', true)
            ->groupBy('d.department_name')
            ->orderBy('d.department_name')
            ->select('d.department_name', DB::raw('count(*) as total'))
            ->get();

        // A photograph per pathway. A team can have one of its own by name -
        // path-sewing.jpg for a Sewing department - and otherwise takes the next
        // picture from the shop gallery, so a new department is never a grey
        // card and no two cards on the page show the same photograph.
        $pool = ['gallery-1', 'gallery-2', 'gallery-3', 'gallery-5', 'gallery-6', 'inventory'];
        $next = 0;

        foreach ($byTeam as $team) {
            $named = CareersMedia::pic('path-'.Str::slug($team->department_name));

            $paths[] = [
                'label' => $team->department_name,
                'note'  => '',
                'count' => (int) $team->total,
                'href'  => route('careers.jobs', ['dept' => $team->department_name]).'#openings',
                'pic'   => $named ?: CareersMedia::pic($pool[$next++ % count($pool)]),
            ];
        }

        return $paths;
    }

    public function totalOpen(): int
    {
        return DB::table('job_positions')->where('is_open', true)->count();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'employmentType', 'department']);
    }
}; ?>

<div>
    {{-- Built the way Uniqlo builds its jobs page: a photograph with the
         headline and the search over it, then a mosaic of pathway cards each
         carrying its own live count, and the list itself below. --}}
    @php $jobsHero = CareersMedia::pic('jobs-hero') ?: CareersMedia::pic('hero'); @endphp
    <section class="hero hero--jobs" id="top">
        <div class="hero__img {{ $jobsHero ? '' : 'hero__img--empty' }}"
             @if ($jobsHero) style="background-image: url('{{ $jobsHero }}')" @endif></div>

        <div class="careers__wrap">
            <span class="careers__eyebrow hero__eyebrow">Open positions &middot; Philippines</span>
            <h1 class="careers__display">Find your next role.</h1>
            <p class="hero__lede">
                Choose a pathway to explore open roles at Imprint Customs, or
                search the whole list. Apply once and you can follow your
                application the whole way through.
            </p>

            <form class="search" wire:submit.prevent>
                <div class="search__field">
                    <i class="fas fa-magnifying-glass"></i>
                    <input type="search"
                           wire:model.live.debounce.400ms="search"
                           placeholder="Search jobs by title, team or keyword"
                           aria-label="Search jobs by title, team or keyword">
                </div>
            </form>
        </div>
    </section>

    <section class="section" style="padding-bottom: 24px">
        <div class="careers__wrap">

            {{-- Choose a pathway first, then the list. Somebody who knows they
                 want the production floor should not have to read the store's
                 adverts to find out there are none. Drawn only when there is
                 something to route people into. --}}
            @php $pathways = $this->pathways(); @endphp
            @if (count($pathways) > 1)
                <div @class(['mosaic', 'mosaic--two' => count($pathways) === 2])>
                    @foreach ($pathways as $way)
                        <a class="mosaic__card" href="{{ $way['href'] }}">
                            <span class="mosaic__img {{ $way['pic'] ? '' : 'mosaic__img--empty' }}"
                                  @if ($way['pic']) style="background-image: url('{{ $way['pic'] }}')" @endif></span>
                            <span class="mosaic__label">
                                <span class="mosaic__text">
                                    <b>{{ $way['label'] }}</b>
                                    <span class="mosaic__count">{{ $way['count'] }} open {{ Str::plural('position', $way['count']) }}</span>
                                    @if ($way['note'] !== '')
                                        <span class="mosaic__note">{{ $way['note'] }}</span>
                                    @endif
                                </span>
                                <i class="fas fa-chevron-right mosaic__go"></i>
                            </span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <section class="section" id="openings" style="padding-top: 0">
        <div class="careers__wrap">
            @php $jobs = $this->jobs(); $filtering = $search !== '' || $employmentType !== '' || $department !== ''; @endphp

            <div class="jobs__bar">
                <span class="jobs__count">
                    <b>{{ count($jobs) }}</b> {{ \Illuminate\Support\Str::plural('role', count($jobs)) }}
                    @if ($filtering) matching your search @else open right now @endif
                </span>

                @if ($this->departments()->isNotEmpty())
                    <select wire:model.live="department" aria-label="Filter by team">
                        <option value="">All teams</option>
                        @foreach ($this->departments() as $name)
                            <option value="{{ $name }}">{{ $name }}</option>
                        @endforeach
                    </select>
                @endif

                <select wire:model.live="employmentType" aria-label="Filter by employment type">
                    <option value="">All types</option>
                    @foreach ($employmentTypes as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>

                @if ($filtering)
                    <button type="button" class="jobs__clear" wire:click="clearFilters">Clear</button>
                @endif
            </div>

            @forelse ($jobs as $job)
                <div class="job">
                    {{-- Optional. A role posted without one is a plain row, the
                         way the whole list used to be. --}}
                    @if ($job->image_path)
                        <div class="job__photo"
                             style="background-image: url('{{ \Illuminate\Support\Facades\Storage::disk('public')->url($job->image_path) }}')"
                             role="img" aria-label="{{ $job->title }}"></div>
                    @endif

                    <div class="job__body">
                        <h3 class="job__title">{{ $job->title }}</h3>
                        <div class="job__meta">
                            <span class="job__tag">{{ $employmentTypes[$job->employment_type] ?? $job->employment_type }}</span>
                            <span><i class="fas fa-users" style="margin-right:7px"></i>{{ $job->department_name ?? 'Imprint Customs' }}</span>
                            <span><i class="fas fa-location-dot" style="margin-right:7px"></i>Philippines</span>
                            <span><i class="fas fa-clock" style="margin-right:7px"></i>Posted {{ \Illuminate\Support\Carbon::parse($job->created_at)->diffForHumans() }}</span>
                        </div>
                        @if ($job->description)
                            <p class="job__desc">{{ $job->description }}</p>
                        @endif
                    </div>

                    {{-- The position travels in the querystring so the application
                         form opens with this role already chosen. --}}
                    <a class="job__apply" href="{{ route('applicant.login', ['position' => $job->title]) }}">
                        Apply <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            @empty
                <div class="jobs__empty">
                    <i class="fas fa-folder-open"></i>
                    @if ($filtering)
                        <p style="margin:0 0 14px">Nothing matches that search.</p>
                        <button type="button" class="jobs__clear" wire:click="clearFilters">Show all {{ $this->totalOpen() }} open roles</button>
                    @else
                        <p style="margin:0 0 14px">No roles are posted at the moment.</p>
                        <a class="jobs__clear" href="{{ route('applicant.login') }}" style="display:inline-block">
                            Send an application anyway &mdash; we keep it on file
                        </a>
                    @endif
                </div>
            @endforelse
        </div>
    </section>


    @include('partials.careers-cta')
</div>
