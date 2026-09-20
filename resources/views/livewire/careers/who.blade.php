<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Support\CareersMedia;
use App\Support\CareersStory;

/*
 * Who is welcome to apply, stated plainly. The people it is aimed at -
 * students, working students, OJT trainees, anybody without a degree -
 * routinely screen themselves out before they reach an openings list, which is
 * why this is a page of its own rather than a paragraph on one.
 *
 * Laid out the way Uniqlo builds its Store Support Center page: a photograph
 * with the headline over it and a way into the openings, then the ways in, the
 * people already here, and last the trades you would be joining. The order is
 * the argument - who we take, who you would stand beside, what you would do.
 */
new #[Layout('components.layouts.careers', ['onDarkHero' => true])] class extends Component {
    public array $welcome = [
        ['title' => 'Students and part-timers',
         'body'  => 'Shifts arranged around class schedules, including weekends and semestral breaks.',
         'icon'  => 'fas fa-clock', 'pic' => 'welcome-part-time'],
        ['title' => 'OJT and work immersion',
         'body'  => 'We take senior high and college trainees, sign the paperwork your school needs, and pay immersion in full.',
         'icon'  => 'fas fa-graduation-cap', 'pic' => 'welcome-ojt'],
        ['title' => 'Fresh graduates',
         'body'  => 'First job? Good. We would rather train you our way than undo habits picked up somewhere else.',
         'icon'  => 'fas fa-seedling', 'pic' => 'welcome-fresh-grad'],
        ['title' => 'No degree needed',
         'body'  => 'Senior high, vocational, self-taught or straight off another trade - what matters is that you can do the work.',
         'icon'  => 'fas fa-door-open', 'pic' => 'welcome-no-degree'],
    ];

    public function pic(string $name): ?string
    {
        return CareersMedia::pic($name);
    }

    public function areas(): array
    {
        return CareersStory::areas();
    }
}; ?>

<div>
    {{-- ------------------------------------------------------------ hero --}}
    @php $whoHero = $this->pic('who-hero') ?: $this->pic('explore-people'); @endphp
    <section class="hero hero--story" id="top">
        <div class="hero__img {{ $whoHero ? '' : 'hero__img--empty' }}"
             @if ($whoHero) style="background-image: url('{{ $whoHero }}')" @endif></div>

        <div class="careers__wrap">
            <span class="careers__eyebrow hero__eyebrow">Who we hire &middot; Philippines</span>
            <h1 class="careers__display">Everyone starts somewhere.</h1>
            <p class="hero__lede">
                No degree required and no experience assumed. If you are willing
                to learn the craft, there is a way in here &mdash; and we will
                teach you the rest.
            </p>
            <a class="cta__btn" href="{{ route('careers.jobs') }}" style="margin-top: 28px">
                Explore openings <i class="fas fa-arrow-right"></i>
            </a>
        </div>
    </section>

    {{-- -------------------------------------------------------- ways in --}}
    <section class="section welcome" id="who-we-hire">
        <div class="careers__wrap">
            <div class="explore__head">
                <div class="section__head" style="margin-bottom: 0">
                    <span class="careers__eyebrow">Ways in</span>
                    <h2>Four ways to start.</h2>
                </div>
                <p class="explore__note">
                    None of them is the back door. People hired through all four
                    are on the floor today, and some of them run it.
                </p>
            </div>

            <ul class="welcome__list">
                @foreach ($welcome as $group)
                    @php $groupPic = $this->pic($group['pic']); @endphp
                    {{-- All four are cards whether or not a photograph exists.
                         The ones without get a navy panel carrying the icon, so
                         a missing picture reads as a design rather than a gap -
                         which is what the plain rows next to photo cards did. --}}
                    <li class="welcome__item">
                        @if ($groupPic)
                            <div class="welcome__pic" style="background-image: url('{{ $groupPic }}')"></div>
                        @else
                            <div class="welcome__pic welcome__pic--icon"><i class="{{ $group['icon'] }}"></i></div>
                        @endif
                        <div class="welcome__row">
                            <h3>{{ $group['title'] }}</h3>
                            <p>{{ $group['body'] }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- --------------------------------------------------- store portraits --}}
    {{-- Three of the shop staff, standing, looking straight at you.

         The quote cards used to sit here - the same three faces and the same
         words as the home page. One page saying it once is enough, and this
         one is addressed to somebody wondering whether they would fit, which
         a face looking back answers better than a second copy of a quote.

         All three or none: two portraits in a three-wide grid leaves a hole. --}}
    @php
        $standing = array_values(array_filter([
            $this->pic('who-1'), $this->pic('who-2'), $this->pic('who-3'),
        ]));
    @endphp
    @if (count($standing) === 3)
    <section class="section section--wash">
        <div class="careers__wrap">
            <div class="explore__head">
                <div class="section__head" style="margin-bottom: 0">
                    <span class="careers__eyebrow">On the team</span>
                    <h2>The people you would join.</h2>
                </div>
                <p class="explore__note">
                    Out front at the store, on an ordinary trading day.
                </p>
            </div>

            <div class="faces">
                @foreach ($standing as $portrait)
                    <figure class="face">
                        <img src="{{ $portrait }}" alt="" loading="lazy" decoding="async">
                    </figure>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    {{-- ---------------------------------------------------------- trades --}}
    {{-- Uniqlo closes the page with "Teams behind the brand". Ours is the
         trades, because here the trade is the job. The number in the heading is
         counted rather than typed - the list went from six to ten in one sitting
         and a headline saying "six" would have quietly started lying. --}}
    <section class="section section--wash">
        <div class="careers__wrap">
            <div class="explore__head">
                <div class="section__head" style="margin-bottom: 0">
                    <span class="careers__eyebrow">Where you would land</span>
                    <h2>{{ \App\Support\CareersStory::areaCountWord() }} trades to learn.</h2>
                </div>
                <p class="explore__note">
                    Nobody is expected to arrive knowing any of them. You are
                    put with somebody who does and taught on the work itself.
                </p>
            </div>

            <div class="floor">
                @foreach ($this->areas() as $area)
                    @php $areaPic = $this->pic($area['pic']); @endphp
                    <div class="floor__card">
                        @if ($areaPic)
                            <div class="floor__pic" style="background-image: url('{{ $areaPic }}')"></div>
                        @else
                            <div class="floor__pic floor__pic--empty"><i class="fas fa-image"></i></div>
                        @endif
                        <div class="floor__body">
                            <h3>{{ $area['name'] }}</h3>
                            <p>{{ $area['body'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    @include('partials.careers-cta')
</div>
