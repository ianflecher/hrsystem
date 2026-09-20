<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Support\CareersMedia;

/*
 * The people. No quotations: the three that used to sit here were invented, and
 * they sat beneath photographs of real, identifiable staff, which reads as
 * words put in their mouths. Photographs until somebody has agreed to be quoted.
 *
 * Opens on a photograph like every other page here - it was the one page that
 * started cold on white, which made it look like a page somebody had forgotten.
 */
new #[Layout('components.layouts.careers', ['onDarkHero' => true])] class extends Component
{
    public function pic(string $name): ?string
    {
        return CareersMedia::pic($name);
    }
}; ?>

<div>
    {{-- ------------------------------------------------------------ hero --}}
    @php $peopleHero = $this->pic('people-hero'); @endphp
    <section class="hero hero--story" id="top">
        <div class="hero__img {{ $peopleHero ? '' : 'hero__img--empty' }}"
             @if ($peopleHero) style="background-image: url('{{ $peopleHero }}')" @endif></div>

        <div class="careers__wrap">
            <span class="careers__eyebrow hero__eyebrow">Our people &middot; Philippines</span>
            <h1 class="careers__display">The team behind it.</h1>
            <p class="hero__lede">
                Printing, sewing, sales, social, the store and the floor &mdash;
                the people you would be working beside.
            </p>
            <a class="cta__btn" href="{{ route('careers.jobs') }}" style="margin-top: 28px">
                Explore openings <i class="fas fa-arrow-right"></i>
            </a>
        </div>
    </section>

    @include('partials.careers-team-strip')

    @include('partials.careers-cta')
</div>
