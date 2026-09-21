{{-- The careers bar.

     These are real pages, not anchors on one long page. They started as
     "#openings" style bookmarks, which meant the bar could never tell you where
     you were, a link could not be sent to somebody on its own, and the whole
     site was one enormous scroll. Uniqlo's are separate addresses and so are
     these - the active state comes from the route, not from scroll position. --}}
@php
    $careersNav = [
        // "/" and /careers are the same page under two route names, so Home
        // has to answer to both or it is never marked on the front page.
        ['route' => 'careers', 'label' => 'Home', 'also' => 'landing'],
        ['route' => 'careers.story',     'label' => 'Who we are'],
        // Third, straight after the two "about us" tabs: who the company is,
        // then who it takes on. The tours of the building come after the
        // answer to "is this place even for me?".
        ['route' => 'careers.who',       'label' => 'Who we hire'],
        ['route' => 'careers.into',      'label' => 'Into Imprint'],
        // The store and the cafe, the customer-facing half of the business.
        ['route' => 'careers.front',     'label' => 'Front'],
        // Benefits has no tab: the home page carries them in full, and a second
        // copy behind its own tab is two things to keep in step.
        ['route' => 'careers.jobs',      'label' => 'Jobs'],
        // Our people has no tab: it is reached from the home page, which
        // already shows the same three faces.
    ];
@endphp

<nav class="cnav {{ ($onDarkHero ?? false) ? 'is-over-hero' : '' }}" id="cnav" aria-label="Careers">
    <div class="careers__wrap cnav__inner">
        <a class="cnav__brand" href="{{ route('careers') }}">
            @if (file_exists(public_path('imprint-customs.jpg')))
                <img src="{{ asset('imprint-customs.jpg') }}" alt="">
            @endif
            <span>IC <b>Careers</b></span>
        </a>

        {{-- Below 860px the links move into a drawer behind this button. They
             used to stay in the bar as a sideways-scrolling strip, which put
             "Ins..." and a fade where the sections should be: a menu you have
             to discover by swiping is a menu most people never see. --}}
        <button type="button" class="cnav__burger" id="cnav-burger"
                aria-controls="cnav-menu" aria-expanded="false" aria-label="Menu">
            <span></span><span></span><span></span>
        </button>

        {{-- display:contents on a wide screen, so this is the bar as it was;
             a drawer off the right edge on a narrow one. --}}
        <div class="cnav__menu" id="cnav-menu">
        {{-- The drawer's own head. It is in the document at every width and
             simply not drawn on a wide one, which keeps one copy of the links
             rather than a desktop set and a phone set that drift apart. --}}
        <div class="cnav__menu-head">
            <div>
                <span class="cnav__menu-eyebrow">Menu</span>
                <b class="cnav__menu-title">IC Careers</b>
            </div>
            <button type="button" class="cnav__close" id="cnav-close" aria-label="Close menu">
                <i class="fas fa-xmark"></i>
            </button>
        </div>

        <div class="cnav__links">
            @foreach ($careersNav as $item)
                <a href="{{ route($item['route']) }}"
                   @class(['cnav__link', 'is-active' => request()->routeIs($item['route']) || (isset($item['also']) && request()->routeIs($item['also']))])
                   @if (request()->routeIs($item['route']) || (isset($item['also']) && request()->routeIs($item['also']))) aria-current="page" @endif>
                    {{ $item['label'] }}
                </a>
            @endforeach
        </div>

        <div class="cnav__right">
            <a href="{{ route('portals') }}" class="cnav__staff">Staff sign-in</a>
            <a href="{{ route('careers.jobs') }}" class="cnav__apply">Apply now</a>
        </div>
        </div>
    </div>
</nav>

{{-- The page behind the drawer, dimmed. Tapping it closes - which is how
     everybody expects a drawer to behave, and it is a much larger target than
     the close button. --}}
<div class="cnav__scrim" id="cnav-scrim" hidden></div>

<script>
(function () {
    var bar = document.getElementById('cnav');
    var burger = document.getElementById('cnav-burger');
    var scrim = document.getElementById('cnav-scrim');
    if (!bar || !burger) return;

    function set(open) {
        bar.classList.toggle('is-open', open);
        burger.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (scrim) scrim.hidden = !open;
        // The page must not scroll under the drawer: on a phone that reads as
        // the menu itself having failed to move.
        document.body.style.overflow = open ? 'hidden' : '';
    }

    burger.addEventListener('click', function () { set(!bar.classList.contains('is-open')); });
    document.getElementById('cnav-close').addEventListener('click', function () { set(false); });
    if (scrim) scrim.addEventListener('click', function () { set(false); });

    // A tap on a link, the escape key, or growing past the breakpoint all put
    // it away again - a drawer left open across a resize covers the page.
    document.getElementById('cnav-menu').addEventListener('click', function (e) {
        if (e.target.closest('a')) set(false);
    });

    addEventListener('keydown', function (e) { if (e.key === 'Escape') set(false); });
    addEventListener('resize', function () { if (innerWidth > 860) set(false); });
})();
</script>
