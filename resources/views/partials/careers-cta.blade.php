{{-- ------------------------------------------------------------- cta --}}
    <section class="cta">
        @php $ctaPic = \App\Support\CareersMedia::pic('cta'); @endphp
        <div class="cta__img {{ $ctaPic ? '' : 'cta__img--empty' }}"
             @if ($ctaPic) style="background-image: url('{{ $ctaPic }}')" @endif></div>

        <div class="careers__wrap">
            <span class="careers__eyebrow hero__eyebrow">Ready when you are</span>
            <h2 class="careers__display">Your future starts here.</h2>
            <p>One application, tracked the whole way &mdash; from submitted to interview to first day.</p>
            <a class="cta__btn" href="{{ route('applicant.login') }}">
                Apply now <i class="fas fa-arrow-right"></i>
            </a>
        </div>
    </section>

    {{-- Marks the section you are actually looking at, the way the Home link
         is marked on arrival. Guarded: if IntersectionObserver is missing the
         nav still works, it just does not follow you. --}}
    {{-- The team carousel. Plain scrolling underneath, so it still works by
         swipe or trackpad if this never runs; the buttons only drive it. --}}
    <script>
    (function () {
        var rail = document.getElementById('teamRail');
        if (!rail) return;

        var arrows = [].slice.call(document.querySelectorAll('.team__arrow'));

        function limit() {
            return rail.scrollWidth - rail.clientWidth;
        }

        function sync() {
            var at = rail.scrollLeft;
            // The rail is padded, so its leftmost resting position is the first
            // tile's offset, not zero - comparing against 0 meant "previous"
            // could never tell it was already at the start.
            var first = rail.firstElementChild;
            var start = first ? first.offsetLeft : 0;

            arrows.forEach(function (button) {
                var forward = button.dataset.dir === '1';
                // A couple of pixels of slack: scrolling lands fractionally
                // short often enough that an exact test leaves an arrow live.
                button.disabled = forward ? at >= limit() - 2 : at <= start + 2;
            });
        }

        arrows.forEach(function (button) {
            button.addEventListener('click', function () {
                // Just under a full pane, so the tile at the edge stays in
                // view and you keep your place rather than jumping blind.
                var step = Math.max(240, rail.clientWidth * 0.8);
                rail.scrollBy({ left: step * Number(button.dataset.dir), behavior: 'smooth' });
            });
        });

        rail.addEventListener('scroll', sync, { passive: true });
        addEventListener('resize', sync);
        window.teamRailSync = sync;
        sync();
    })();
    </script>

    <script>
    (function () {
        var links = [].slice.call(document.querySelectorAll('.cnav__link'));
        if (!links.length) return;

        var sections = links
            .map(function (link) {
                return { link: link, el: document.getElementById(link.dataset.section) };
            })
            .filter(function (pair) { return pair.el; });

        // Plain geometry rather than IntersectionObserver: this is measurable
        // at any moment, which makes it testable, and it behaves the same
        // whether you scrolled here or arrived on a #hash.
        var LINE = 130;   // just below the two stacked bars

        var bar = document.getElementById('cnav');
        var hero = document.getElementById('top');

        function update() {
            // Solid once the hero has gone by: there is no photograph left to
            // sit on, so white type on nothing would be invisible.
            if (bar) {
                var past = hero
                    ? hero.getBoundingClientRect().bottom <= bar.offsetHeight
                    : window.scrollY > 40;
                bar.classList.toggle('is-solid', past);
            }

            var current = sections[0];

            sections.forEach(function (pair) {
                if (pair.el.getBoundingClientRect().top <= LINE) current = pair;
            });

            // The last section can never reach the line on a short page, so
            // the bottom of the document counts as reaching the end.
            if (window.innerHeight + window.scrollY >= document.body.offsetHeight - 2) {
                current = sections[sections.length - 1];
            }

            sections.forEach(function (pair) {
                pair.link.classList.toggle('is-active', pair === current);
            });
        }

        window.careersNavUpdate = update;   // so the state can be checked directly
        addEventListener('scroll', update, { passive: true });
        addEventListener('resize', update);
        update();
    })();
    </script>
