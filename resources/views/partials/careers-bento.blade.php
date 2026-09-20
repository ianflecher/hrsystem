<section class="section section--wash" id="benefits">
        <div class="careers__wrap">
            <div class="section__head">
                <span class="careers__eyebrow">Pay and benefits</span>
                <h2>Everything that comes with the job.</h2>
                <p>
                    All of it mandated, all of it actually paid, and every peso of it
                    named on the payslip you get each cutoff.
                </p>
            </div>

            <div class="bento bento--after">
                {{-- A statement tile and a photograph across the top, the way
                     Uniqlo opens its benefits grid: something to look at before
                     the detail, so the section does not begin as three lists. --}}
                <div class="bento__red">
                    <span class="bento__eyebrow">Straight dealing</span>
                    <h3>Paid properly, and paid on time.</h3>
                    <p>
                        Every cutoff, with every peso named on the payslip. No
                        contribution quietly skipped, no deduction you cannot account for.
                    </p>
                </div>

                @php $bentoPic = \App\Support\CareersMedia::pic('bento-photo') ?: \App\Support\CareersMedia::pic('gallery-4'); @endphp
                <div class="bento__photo" @if ($bentoPic) style="background-image: url('{{ $bentoPic }}')" @endif>
                    <div class="bento__photo-cap">
                        <span class="bento__eyebrow">Inside our shop</span>
                        <p>The people behind every order, every day.</p>
                        <i class="fas fa-arrow-right"></i>
                    </div>
                </div>

                @foreach (\App\Support\CareersBenefits::cards($benefitLimit ?? null) as $benefit)
                    <div class="bento__cell bento__cell--span{{ $benefit['span'] }}">
                        <span class="bento__eyebrow bento__eyebrow--red">{{ $benefit['eyebrow'] }}</span>
                        <h3>{{ $benefit['title'] }}</h3>
                        <p>{{ $benefit['body'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
