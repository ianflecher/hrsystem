<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Support\CareersStory;
use App\Support\CareersMedia;

/*
 * Who we are: what the company makes, how the work is divided, and how big it
 * actually is.
 *
 * The same account the applicant company profile gives, and drawn from the same
 * place - but public, because somebody deciding whether to apply should not
 * have to register first to find out what the company does.
 *
 * Uniqlo puts a founder's message on this page. There is a slot for one here,
 * filled only when there are real words to put in it: an invented message from
 * a named owner is worse than no message at all.
 */
new #[Layout('components.layouts.careers', ['onDarkHero' => true])] class extends Component
{
    public function areas(): array
    {
        return CareersStory::areas();
    }

    public function pic(string $name): ?string
    {
        return CareersMedia::pic($name);
    }
}; ?>

<div>
    {{-- The words sit on the photograph rather than under it: a band of image
         with the heading below reads as a decorative stripe, not a hero. --}}
    @php $storyHero = $this->pic('story-hero'); @endphp
    <section class="hero hero--story" id="top">
        <div class="hero__img {{ $storyHero ? '' : 'hero__img--empty' }}"
             @if ($storyHero) style="background-image: url('{{ $storyHero }}')" @endif></div>

        <div class="careers__wrap">
            <span class="careers__eyebrow hero__eyebrow">Who we are &middot; Philippines</span>
            <h1 class="careers__display">Made here, start to finish.</h1>
            <p class="hero__lede">
                Imprint Customs PH is a custom printed apparel shop. Jerseys,
                uniforms, shirts and merchandise &mdash; designed, printed,
                embroidered, cut, sewn, pressed and packed in the same building,
                by the same team.
            </p>
            <a class="cta__btn" href="{{ route('careers.jobs') }}" style="margin-top: 28px">
                Explore openings <i class="fas fa-arrow-right"></i>
            </a>
        </div>
    </section>

    {{-- A word from the founder, laid out the way Uniqlo's CEO message is: a
         tall portrait on the left, and on the right a name-and-role label, one
         line set large in quotation marks, then the message under it.

         Hidden entirely until App\Support\CareersFounder has a message - an
         empty quote panel with nobody's name on it is worse than no panel. --}}
    @if (\App\Support\CareersFounder::has())
        @php $founder = \App\Support\CareersFounder::get(); @endphp
        <section class="section section--wash">
            <div class="careers__wrap">
                <div class="explore__head">
                    <div class="section__head" style="margin-bottom: 0">
                        <span class="careers__eyebrow">Founder&rsquo;s message</span>
                        <h2>A word from our founder.</h2>
                    </div>
                    @if ($founder['name'] !== '')
                        {{-- Built in PHP rather than with a directive inside the
                             sentence: an inline @if/@endif mid-line tripped the
                             Blade compiler. --}}
                        @php
                            $who = $founder['name'];

                            if ($founder['role'] !== '') {
                                $who .= ', '.strtolower($founder['role']).' of Imprint Customs';
                            }
                        @endphp
                        <p class="explore__note">
                            {{ $who }}, on why the whole thing is made under one roof.
                        </p>
                    @endif
                </div>

                <div class="founder">
                    {{-- The photograph is optional on top of the message: a
                         brand panel with a quote mark stands in until there is
                         one, rather than a grey box. --}}
                    <div class="founder__pic {{ $founder['pic'] ? '' : 'founder__pic--empty' }}"
                         @if ($founder['pic']) style="background-image: url('{{ $founder['pic'] }}')" @endif>
                        @unless ($founder['pic']) <i class="fas fa-quote-left"></i> @endunless
                    </div>

                    <div class="founder__body">
                        <div class="founder__who">
                            @if ($founder['name'] !== '')
                                <b>{{ $founder['name'] }}</b>
                            @endif
                            @if ($founder['role'] !== '')
                                <span>&middot; {{ $founder['role'] }}</span>
                            @endif
                        </div>

                        @if ($founder['headline'] !== '')
                            <p class="founder__headline">&ldquo;{{ $founder['headline'] }}&rdquo;</p>
                        @endif

                        <blockquote>{{ $founder['message'] }}</blockquote>
                    </div>
                </div>
            </div>
        </section>
    @endif

    {{-- --------------------------------------------------------- events --}}
    {{-- What the trades grid used to be. The six trades are still on Who we
         hire, where somebody deciding whether to apply is actually reading;
         this page had the same grid a second time. A company sportsfest says
         something the workstations cannot. --}}
    @php $events = \App\Support\CareersEvents::all(); @endphp
    @if (count($events) > 0)
    <section class="section section--wash" id="events">
        <div class="careers__wrap">
            <div class="section__head">
                <span class="careers__eyebrow">Outside the work</span>
                <h2>What we get up to.</h2>
                <p>The company shuts the court down for its own tournament, and everybody plays.</p>
            </div>

            @foreach ($events as $event)
                <div class="event">
                    <div class="event__head">
                        <div>
                            <span class="event__when">{{ $event['when'] }}</span>
                            <h3>{{ $event['title'] }}</h3>
                        </div>
                        <p>{{ $event['body'] }}</p>
                    </div>

                    <figure class="event__hero" style="background-image: url('{{ $event['hero'] }}')">
                        @if ($event['heroCaption'] !== '')
                            <figcaption>{{ $event['heroCaption'] }}</figcaption>
                        @endif
                    </figure>

                    @if (count($event['shots']) > 0)
                        <div class="event__grid">
                            @foreach ($event['shots'] as $shot)
                                <figure class="station">
                                    <img src="{{ $shot['src'] }}" alt="{{ $shot['caption'] }}" loading="lazy" decoding="async">
                                    @if ($shot['caption'] !== '')
                                        <figcaption>{{ $shot['caption'] }}</figcaption>
                                    @endif
                                </figure>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach

            @php $storyDetail = \App\Support\CareersMedia::pic('story-detail'); @endphp
            @if ($storyDetail)
                <div class="story__detail" style="background-image: url('{{ $storyDetail }}')">
                    <div class="bento__photo-cap">
                        <span class="bento__eyebrow">Out front</span>
                        <p>The counter where the work meets the customer.</p>
                    </div>
                </div>
            @endif

            <p style="margin: 26px 0 0">
                <a href="{{ route('careers.inside') }}" class="path__go">
                    See every workstation <i class="fas fa-arrow-right"></i>
                </a>
            </p>
        </div>
    </section>
    @endif

    {{-- The "explore" block from Uniqlo's Who We Are: two large cards that
         carry a photograph and three small ones that are only a label. Theirs
         point at corporate microsites; ours point at the rest of this site,
         because that is where the answers actually are. --}}
    <section class="section">
        <div class="careers__wrap">
            <div class="explore__head">
                <div class="section__head" style="margin-bottom: 0">
                    <span class="careers__eyebrow">Explore</span>
                    <h2>Get to know the company.</h2>
                </div>
                <p class="explore__note">
                    Everything an applicant usually has to ask for &mdash; what the
                    work is, who we take on, what the pay comes with, and who you
                    would be working beside.
                </p>
            </div>

            <div class="explore">
                @foreach ([
                    ['route' => 'careers.inside', 'pic' => 'station-12', 'icon' => 'fas fa-industry',
                     'eyebrow' => 'The floor', 'title' => 'Every workstation',
                     'body' => 'Printing, embroidery, cutting, sewing, press and the store - each place the work passes through.',
                     'link' => 'Look inside'],
                    ['route' => 'careers.people', 'pic' => 'explore-people', 'icon' => 'fas fa-people-group', 'rule' => true,
                     'eyebrow' => 'Our people', 'title' => 'The team you would join',
                     'body' => 'Sales, social, marketing and everybody on the floor - the people you would be working beside.',
                     'link' => 'Meet the team'],
                ] as $card)
                    @php $cardPic = $this->pic($card['pic']); @endphp
                    <a class="explore__big {{ ($card['rule'] ?? false) ? 'explore__big--rule' : '' }}"
                       href="{{ route($card['route']) }}"
                       @if ($cardPic) style="background-image: url('{{ $cardPic }}')" @endif>
                        <span class="explore__chip"><i class="{{ $card['icon'] }}"></i></span>
                        <div class="explore__big-body">
                            <span class="bento__eyebrow">{{ $card['eyebrow'] }}</span>
                            <h3>{{ $card['title'] }}</h3>
                            <p>{{ $card['body'] }}</p>
                            <span class="explore__link">{{ $card['link'] }} <i class="fas fa-arrow-right"></i></span>
                        </div>
                    </a>
                @endforeach
            </div>

            <div class="explore__small">
                @foreach ([
                    ['route' => 'careers.jobs',     'icon' => 'fas fa-briefcase',      'eyebrow' => 'Open roles', 'title' => 'Jobs'],
                    ['route' => 'careers.jobs',     'icon' => 'fas fa-peso-sign',      'eyebrow' => 'Pay',        'title' => 'What the job pays'],
                    ['route' => 'careers.who',      'icon' => 'fas fa-graduation-cap', 'eyebrow' => 'Who we hire','title' => 'Students, OJT and first jobs'],
                ] as $link)
                    <a class="explore__tile" href="{{ route($link['route']) }}">
                        <span class="explore__tile-icon"><i class="{{ $link['icon'] }}"></i></span>
                        <span class="explore__tile-text">
                            <span class="bento__eyebrow bento__eyebrow--red">{{ $link['eyebrow'] }}</span>
                            <b>{{ $link['title'] }}</b>
                        </span>
                        <i class="fas fa-arrow-up-right-from-square explore__tile-go"></i>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    @include('partials.careers-cta')
</div>
