{{-- The shell every careers page shares: one bar, one stylesheet.

     There is deliberately no site header here. The shared landing layout stacks
     a dark navy one above the page, which meant two bars and 118px of chrome
     before any content; Uniqlo's careers site has exactly one, fixed and
     transparent over a hero, solid everywhere else.

     $onDarkHero is set by the home page, which is the only one with a
     full-bleed photograph behind the bar. Every other page starts on white, so
     the bar has to be solid from the first paint or its white type would be
     invisible until the first scroll. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Careers - Imprint Customs PH' }}</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">

    <style>
        html { scroll-behavior: smooth; }
        body { margin: 0; background: #fff; }
    </style>

    @include('partials.careers-css')
</head>
<body>
    <div class="careers">
        @include('partials.careers-nav', ['onDarkHero' => $onDarkHero ?? false])

        {{-- Pages that do not open on a photograph start below the fixed bar
             rather than underneath it. --}}
        <div class="{{ ($onDarkHero ?? false) ? '' : 'careers__below-bar' }}">
            {{ $slot }}
        </div>

        <div class="careers__wrap">
            <div class="careers__foot">
                <span>&copy; {{ date('Y') }} Imprint Customs PH</span>
                <span><i class="fas fa-envelope" style="margin-right:8px"></i>hr@imprintcustoms.ph</span>
                <a href="{{ route('applicant.login') }}">Track an application</a>
                <a href="{{ route('portals') }}">Already an employee?</a>
            </div>
        </div>
    </div>

    {{-- Transparent only at the very top of a page that opens on a photograph.
         It used to stay transparent for the whole height of the hero, which
         meant the headline and the lede slid underneath a see-through bar on
         the way past and collided with the links - two sets of white words in
         the same place. The moment the page moves, the bar goes solid and the
         content passes behind it cleanly. --}}
    <script>
    (function () {
        var bar = document.getElementById('cnav');
        var hero = document.getElementById('top');
        if (!bar || !hero) return;

        function update() {
            bar.classList.toggle('is-over-hero', (window.scrollY || document.documentElement.scrollTop) < 8);
        }

        addEventListener('scroll', update, { passive: true });
        addEventListener('resize', update);
        update();
    })();
    </script>
</body>
</html>
