{{-- Uniqlo's "voices" cards: a portrait, a red role badge across the top left,
     and the quote set over the foot of the photograph with the name beneath.

     Each piece is drawn only when there is something to draw, so the same
     markup is a plain portrait today and a full card once App\Support\
     CareersVoices has the names, roles and quotes in it. --}}
@php $voices = \App\Support\CareersVoices::all(); @endphp

@if (count($voices) > 0)
<section class="section" id="our-people">
    <div class="careers__wrap">
        <div class="explore__head">
            <div class="section__head" style="margin-bottom: 0">
                <span class="careers__eyebrow">Our people</span>
                <h2>{{ \App\Support\CareersVoices::anyQuoted() ? 'Why our people stay.' : 'The people who make it.' }}</h2>
            </div>
            {{-- Says something about these three rather than about the
                 photography. It also described the wrong teams once they were
                 named: they are sales, social and marketing, not the floor. --}}
            <p class="explore__note">
                Sales, social and marketing &mdash; one of them started here as an
                OJT trainee and one straight out of school.
            </p>
        </div>

        <div class="voices">
            @foreach ($voices as $voice)
                <figure class="voice" style="background-image: url('{{ $voice['src'] }}')">
                    @if ($voice['role'] !== '')
                        <span class="voice__badge">{{ $voice['role'] }}</span>
                    @endif

                    @if ($voice['hasWords'])
                        <figcaption class="voice__words">
                            @if ($voice['quote'] !== '')
                                <blockquote>&ldquo;{{ $voice['quote'] }}&rdquo;</blockquote>
                            @endif
                            @if ($voice['name'] !== '')
                                <span class="voice__name">&mdash; {{ $voice['name'] }}</span>
                            @endif
                        </figcaption>
                    @endif
                </figure>
            @endforeach
        </div>

        <p style="margin: 26px 0 0">
            <a href="{{ route('careers.people') }}" class="path__go">
                See the whole team <i class="fas fa-arrow-right"></i>
            </a>
        </p>
    </div>
</section>
@endif
