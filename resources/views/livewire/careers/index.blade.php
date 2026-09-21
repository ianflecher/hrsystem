<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Support\CareersMedia;
use Illuminate\Support\Facades\DB;

/*
 * The careers home page: the hero, a short account of who we hire, and the way
 * out to the pages that carry the detail.
 *
 * It is the only careers page that opens on a full-bleed photograph, which is
 * why it passes onDarkHero - the bar starts transparent here and turns solid
 * once the hero has scrolled by.
 */
new #[Layout('components.layouts.careers', ['onDarkHero' => true])] class extends Component
{
    public function openCount(): int
    {
        return DB::table('job_positions')->where('is_open', true)->count();
    }

    /** The four routes into the company, each a page of its own. */
    public function paths(): array
    {
        return [
            ['route' => 'careers.jobs', 'eyebrow' => 'Open roles',
             'title' => 'Jobs', 'pic' => 'gallery-1',
             'body'  => 'Everything posted is genuinely open. Apply once and follow it the whole way through.'],
            ['route' => 'careers.who', 'eyebrow' => 'Who we hire',
             'title' => 'Students, OJT and first jobs', 'pic' => 'welcome-part-time',
             'body'  => 'No degree required and no experience assumed. If you will learn the craft, there is a way in.'],
            ['route' => 'careers.jobs', 'eyebrow' => 'Pay and benefits',
             'title' => 'What comes with the job', 'pic' => 'gallery-5',
             'body'  => 'Competitive pay, half price on our own apparel, and every peso named on your payslip.'],
            ['route' => 'careers.inside', 'eyebrow' => 'Inside',
             'title' => 'Where the work happens', 'pic' => 'gallery-2',
             'body'  => 'Printing, embroidery, cutting, sewing and the store out front - all under one roof.'],
        ];
    }

    public function pic(string $name): ?string
    {
        return CareersMedia::pic($name);
    }
}; ?>

<div>
    @include('partials.careers-hero')

    {{-- The shape Uniqlo's home page has: the people, why you would stay, and
         something to watch - each a taster with the full account a click away.
         A home page that is only a menu of links gives a visitor no reason to
         believe any of it. --}}
    @include('partials.careers-voices')

    {{-- All five, not a first three: this is the only place the benefits are
         shown now, so a limit here would hide two of them from the whole site. --}}
    @include('partials.careers-bento')

    <section class="section">
        <div class="careers__wrap">
            <div class="section__head">
                <span class="careers__eyebrow">Watch</span>
                <h2>Inside the shop.</h2>
                <p>Presses running, screens burning, orders going out the door.</p>
            </div>

            @include('partials.careers-clip', ['clipSlot' => 2])

            <p style="margin: 20px 0 0">
                <a href="{{ route('careers.inside') }}" class="path__go">
                    See every workstation <i class="fas fa-arrow-right"></i>
                </a>
            </p>
        </div>
    </section>

    {{-- The four ways in, last before the call to action - Uniqlo's running
         order. A menu of links at the top of a page asks somebody to choose
         before anything has given them a reason to; down here the case has
         already been made. Each is a door rather than a summary, so the pages
         behind them still have something to say. --}}
    <section class="section">
        <div class="careers__wrap">
            <div class="section__head">
                <span class="careers__eyebrow">Start here</span>
                <h2>Four ways in.</h2>
                <p>
                    @if ($this->openCount() > 0)
                        {{ $this->openCount() }} {{ \Illuminate\Support\Str::plural('role', $this->openCount()) }} open right now.
                    @else
                        Nothing is posted this minute, but applications are always read.
                    @endif
                </p>
            </div>

            <div class="paths">
                @foreach ($this->paths() as $path)
                    @php $pathPic = $this->pic($path['pic']); @endphp
                    <a class="path" href="{{ route($path['route']) }}">
                        <div class="path__pic {{ $pathPic ? '' : 'path__pic--empty' }}"
                             @if ($pathPic) style="background-image: url('{{ $pathPic }}')" @endif></div>
                        <div class="path__body">
                            <span class="bento__eyebrow bento__eyebrow--red">{{ $path['eyebrow'] }}</span>
                            <h3>{{ $path['title'] }}</h3>
                            <p>{{ $path['body'] }}</p>
                            <span class="path__go">Have a look <i class="fas fa-arrow-right"></i></span>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    @include('partials.careers-cta')
</div>
