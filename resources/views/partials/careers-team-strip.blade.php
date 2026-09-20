@php $team = \App\Support\CareersTeam::all(); @endphp
    @if (count($team) > 0)
    <section class="section" id="our-people">
        <div class="careers__wrap">
            <div class="section__head">
                <span class="careers__eyebrow">Our people</span>
                <h2>The people who make it.</h2>
                <p>
                    Everybody here, from the production floor to the store out
                    front &mdash; photographed at work on an ordinary day.
                </p>
            </div>
        </div>

        {{-- Breaks the page's own gutter on purpose: a strip that runs off the
             edge reads as "there are more of us" rather than as a boxed list. --}}
        <div class="team">
            <div class="team__rail" id="teamRail">
                @foreach ($team as $shot)
                    <div class="team__shot {{ $shot['wide'] ? 'team__shot--wide' : '' }}"
                         style="background-image: url('{{ $shot['src'] }}')"></div>
                @endforeach
            </div>

            {{-- Controls under the rail, not over it: on a strip this tall an
                 overlaid arrow sits on somebody's face. --}}
            <div class="careers__wrap team__controls">
                <button type="button" class="team__arrow" data-dir="-1" aria-label="Previous people">
                    <i class="fas fa-arrow-left"></i>
                </button>
                <button type="button" class="team__arrow" data-dir="1" aria-label="More people">
                    <i class="fas fa-arrow-right"></i>
                </button>
            </div>
        </div>
    </section>
    @endif
