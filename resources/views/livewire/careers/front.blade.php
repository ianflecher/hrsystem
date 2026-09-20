<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Support\CareersMedia;
use App\Support\CareersFront;

/*
 * Out front: Imprint Store, 21 & Co Barbershop and Imprint Cafe.
 *
 * The other side of the business from the production floor - the part a
 * customer actually walks into. Somebody applying for a counter job wants to
 * see this, not the presses.
 *
 * Opens on a photograph, so the bar starts transparent over it.
 */
new #[Layout('components.layouts.careers', ['onDarkHero' => true])] class extends Component
{
    public function pic(string $name): ?string
    {
        return CareersMedia::pic($name);
    }

    public function store(): array
    {
        return CareersFront::store();
    }

    public function barber(): array
    {
        return CareersFront::barber();
    }

    public function cafe(): array
    {
        return CareersFront::cafe();
    }
}; ?>

<div>
    {{-- ------------------------------------------------------------ hero --}}
    @php $frontHero = $this->pic('front-hero'); @endphp
    <section class="hero hero--story" id="top">
        <div class="hero__img {{ $frontHero ? '' : 'hero__img--empty' }}"
             @if ($frontHero) style="background-image: url('{{ $frontHero }}')" @endif></div>

        <div class="careers__wrap">
            <span class="careers__eyebrow hero__eyebrow">Imprint Store, 21 &amp; Co &amp; Imprint Caf&eacute;</span>
            <h1 class="careers__display">Out front.</h1>
            <p class="hero__lede">
                The side of the business a customer walks into &mdash; the shop
                where the work is sold, the barbershop and the caf&eacute;
                beside it.
            </p>
            <a class="cta__btn" href="{{ route('applicant.login') }}" style="margin-top: 28px">
                Apply now <i class="fas fa-arrow-right"></i>
            </a>
        </div>
    </section>

    {{-- ----------------------------------------------------------- store --}}
    @php $store = $this->store(); @endphp
    @if (count($store) > 0)
    <section class="section">
        <div class="careers__wrap">
            <div class="explore__head">
                <div class="section__head" style="margin-bottom: 0">
                    <span class="careers__eyebrow">Imprint Store</span>
                    <h2>Where the work is sold.</h2>
                </div>
                <p class="explore__note">
                    Walk-in customers see the same jerseys, uniforms and merchandise
                    that were printed and sewn in the building behind them.
                </p>
            </div>

            <div class="faces">
                @foreach ($store as $shot)
                    <figure class="face">
                        <img src="{{ $shot['src'] }}" alt="{{ $shot['caption'] }}" loading="lazy" decoding="async">
                        @if ($shot['caption'] !== '')
                            <figcaption>{{ $shot['caption'] }}</figcaption>
                        @endif
                    </figure>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    {{-- -------------------------------------------------------- barbershop --}}
    {{-- Its own business, not a corner of the shop: own name, own trade, own
         way in. Hidden until it has photographs, like the cafe. --}}
    @php $barber = $this->barber(); @endphp
    @if (count($barber) > 0)
    <section class="section section--wash">
        <div class="careers__wrap">
            <div class="explore__head">
                <div class="section__head" style="margin-bottom: 0">
                    <span class="careers__eyebrow">21 &amp; Co Barbershop</span>
                    <h2>And a chair next door.</h2>
                </div>
                <p class="explore__note">
                    A barbershop under the same roof, with its own trade and its
                    own shifts.
                </p>
            </div>

            <div class="faces">
                @foreach ($barber as $shot)
                    <figure class="face">
                        <img src="{{ $shot['src'] }}" alt="{{ $shot['caption'] }}" loading="lazy" decoding="async">
                        @if ($shot['caption'] !== '')
                            <figcaption>{{ $shot['caption'] }}</figcaption>
                        @endif
                    </figure>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    {{-- ------------------------------------------------------------ cafe --}}
    {{-- Hidden until the cafe has photographs of its own, so it can wait for a
         shoot rather than sitting on the page half-finished. --}}
    @php $cafe = $this->cafe(); @endphp
    @if (count($cafe) > 0)
    <section class="section">
        <div class="careers__wrap">
            <div class="explore__head">
                <div class="section__head" style="margin-bottom: 0">
                    <span class="careers__eyebrow">Imprint Caf&eacute;</span>
                    <h2>And somewhere to sit.</h2>
                </div>
                <p class="explore__note">
                    The caf&eacute; beside the shop &mdash; its own counter, its own
                    shifts, and its own way in.
                </p>
            </div>

            <div class="faces">
                @foreach ($cafe as $shot)
                    <figure class="face">
                        <img src="{{ $shot['src'] }}" alt="{{ $shot['caption'] }}" loading="lazy" decoding="async">
                        @if ($shot['caption'] !== '')
                            <figcaption>{{ $shot['caption'] }}</figcaption>
                        @endif
                    </figure>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    @include('partials.careers-cta')
</div>
