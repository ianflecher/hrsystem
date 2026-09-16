<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>{{ $title ?? config('app.name') }}</title>

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance

{{-- Pinned light, deliberately.

     Flux's appearance script follows the computer's dark mode and puts
     class="dark" on <html>, which switches every Tailwind dark: variant in the
     employee screens. The portal's own CSS - header, sidebar, page, cards - is
     light only, so anybody whose laptop was set to dark got dark cards on a
     light page and headings they could barely read. The HR side never had this
     because it does not include this partial.

     One appearance for the whole product until there is a real dark theme to
     switch to. --}}
<script>window.Flux?.applyAppearance('light')</script>
