{{-- ------------------------------------------------------------ hero --}}
    <section class="hero" id="top">
        @php $heroPic = \App\Support\CareersMedia::pic('hero'); @endphp
        <div class="hero__img {{ $heroPic ? '' : 'hero__img--empty' }}"
             @if ($heroPic) style="background-image: url('{{ $heroPic }}')" @endif></div>

        <div class="careers__wrap">
            <span class="careers__eyebrow hero__eyebrow">Imprint Customs PH &mdash; Careers</span>
            <h1 class="careers__display">Print your next chapter.</h1>
            <p class="hero__lede">
                Join the team behind custom apparel that ships across the Philippines
                &mdash; on the production floor, in the studio, and out front with customers.
            </p>

            <form class="search" method="GET" action="{{ route('careers.jobs') }}">
                <div class="search__field">
                    <i class="fas fa-magnifying-glass"></i>
                    <input type="search" name="q"
                           placeholder="Search jobs by title, team or keyword"
                           aria-label="Search jobs by title, team or keyword">
                </div>
                <button type="submit" class="search__go">Search</button>
            </form>
        </div>
    </section>
