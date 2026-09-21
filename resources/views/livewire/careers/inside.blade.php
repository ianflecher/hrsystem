<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Support\CareersMedia;
use App\Support\Workstations;

/*
 * Where the work happens.
 *
 * Laid out the way Uniqlo's In-Store page is: a hero you step into, something
 * to watch, the places themselves, then what the first day actually gives you.
 *
 * It opens on a photograph, so the bar starts transparent over it.
 */
new #[Layout('components.layouts.careers', ['onDarkHero' => true])] class extends Component
{
    public function workstations(): array
    {
        return Workstations::all();
    }

    public function pic(string $name): ?string
    {
        return CareersMedia::pic($name);
    }

    /*
     * What the first day gives you. Every line is something the rest of this
     * site already commits to, so there is nothing new here to stand behind.
     */
    public array $dayOne = [
        ['icon' => 'fas fa-chalkboard-user', 'title' => 'Somebody teaching you',
         'body' => 'You are put with a person, not a manual. Most of the floor learned the trade here.'],
        ['icon' => 'fas fa-id-card',         'title' => 'Your own station',
         'body' => 'A place to work and the kit that goes with it, from the first shift.'],
        ['icon' => 'fas fa-calendar-check',  'title' => 'A rest day that suits you',
         'body' => 'Set per person, so it can fit around classes or a long commute.'],
        ['icon' => 'fas fa-receipt',         'title' => 'A payslip that explains itself',
         'body' => 'Every peso named - pay, overtime, contributions and anything taken off.'],
    ];
}; ?>

<div>
    {{-- ------------------------------------------------------------ hero --}}
    @php $insideHero = $this->pic('inside-hero'); @endphp
    <section class="hero hero--story" id="top">
        <div class="hero__img {{ $insideHero ? '' : 'hero__img--empty' }}"
             @if ($insideHero) style="background-image: url('{{ $insideHero }}')" @endif></div>

        <div class="careers__wrap">
            <span class="careers__eyebrow hero__eyebrow">The production floor &middot; Philippines</span>
            <h1 class="careers__display">Step onto the floor.</h1>
            <p class="hero__lede">
                Printing, embroidery, cutting, sewing, press and the store out
                front &mdash; every order passes through the same building, and
                through somebody&rsquo;s hands.
            </p>
            <a class="cta__btn" href="{{ route('careers.jobs') }}" style="margin-top: 28px">
                Explore openings <i class="fas fa-arrow-right"></i>
            </a>
        </div>
    </section>

    {{-- ----------------------------------------------------------- watch --}}
    <section class="section section--wash">
        <div class="careers__wrap">
            <div class="explore__head">
                <div class="section__head" style="margin-bottom: 0">
                    <span class="careers__eyebrow">Watch</span>
                    <h2>An ordinary day.</h2>
                </div>
                <p class="explore__note">
                    Presses running, screens burning, orders going out of the door.
                </p>
            </div>

            @include('partials.careers-clip', ['clipSlot' => 2])
        </div>
    </section>

    {{-- ----------------------------------------------------------- faces --}}
    {{-- Uniqlo's "Faces of Our Stores". Hidden until there is at least one
         photograph: drop face-1.jpg .. face-12.jpg into public/img/careers/
         and the section appears on its own. --}}
    @if (\App\Support\CareersFaces::any())
        @php $faces = \App\Support\CareersFaces::all(); @endphp
        <section class="section">
            <div class="careers__wrap">
                <div class="explore__head">
                    <div class="section__head" style="margin-bottom: 0">
                        <span class="careers__eyebrow">Meet the team</span>
                        <h2>Faces of the floor.</h2>
                    </div>
                    <p class="explore__note">
                        The people who would be teaching you the trade.
                    </p>
                </div>

                <div class="faces">
                    @foreach ($faces as $face)
                        <figure class="face">
                            <img src="{{ $face['src'] }}" alt="{{ $face['caption'] }}"
                                 loading="lazy" decoding="async">
                            @if ($face['caption'] !== '')
                                <figcaption>{{ $face['caption'] }}</figcaption>
                            @endif
                        </figure>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- ---------------------------------------------------- workstations --}}
    @php $stations = $this->workstations(); @endphp
    @if (count($stations) > 0)
    <section class="section">
        <div class="careers__wrap">
            <div class="explore__head">
                <div class="section__head" style="margin-bottom: 0">
                    <span class="careers__eyebrow">Every workstation</span>
                    <h2>{{ count($stations) }} places the work passes through.</h2>
                </div>
                <p class="explore__note">
                    You would be at one of them. Most people start in one and end up
                    knowing three.
                </p>
            </div>

            <div class="stations">
                @foreach ($stations as $station)
                    <figure class="station">
                        {{-- Lazy: two dozen photographs, all below the fold. --}}
                        <img src="{{ $station['src'] }}" alt="{{ $station['caption'] }}"
                             loading="lazy" decoding="async">
                        <figcaption>{{ $station['caption'] }}</figcaption>
                    </figure>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    {{-- --------------------------------------------------------- day one --}}
    <section class="section section--wash">
        <div class="careers__wrap">
            <div class="section__head">
                <span class="careers__eyebrow">Highlights</span>
                <h2>What you get on day one.</h2>
            </div>

            <div class="dayone">
                @foreach ($dayOne as $item)
                    <div class="dayone__card">
                        <span class="dayone__icon"><i class="{{ $item['icon'] }}"></i></span>
                        <h3>{{ $item['title'] }}</h3>
                        <p>{{ $item['body'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    @include('partials.careers-cta')
</div>
