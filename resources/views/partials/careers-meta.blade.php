{{-- The tab, the search result, and the card a pasted link turns into.

     All seven pages used to answer to one title and carried no description
     and no share tags, so a link pasted into Messenger - which is how a job
     advert actually travels here - showed a bare address and nothing else.

     Everything comes from App\Support\CareersMeta, keyed by route name, so a
     new page gets its own title by being added there rather than by
     remembering to edit a <head>. --}}
@php
    $meta = \App\Support\CareersMeta::current();
    $canonical = url()->current();
@endphp

<title>{{ $meta['full'] }}</title>
<meta name="description" content="{{ $meta['description'] }}">
<link rel="canonical" href="{{ $canonical }}">

<meta property="og:type" content="website">
<meta property="og:site_name" content="{{ \App\Support\CareersMeta::SITE }} Careers">
<meta property="og:locale" content="en_PH">
<meta property="og:url" content="{{ $canonical }}">
<meta property="og:title" content="{{ $meta['full'] }}">
<meta property="og:description" content="{{ $meta['description'] }}">
@if ($meta['image'])
    {{-- 1200x630, cut to that shape rather than left for Facebook to crop,
         which it does through whoever is standing in the middle. --}}
    <meta property="og:image" content="{{ $meta['image'] }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="{{ $meta['title'] }} at {{ \App\Support\CareersMeta::SITE }}">
@endif

<meta name="twitter:card" content="{{ $meta['image'] ? 'summary_large_image' : 'summary' }}">
<meta name="twitter:title" content="{{ $meta['full'] }}">
<meta name="twitter:description" content="{{ $meta['description'] }}">
@if ($meta['image'])
    <meta name="twitter:image" content="{{ $meta['image'] }}">
@endif

<meta name="theme-color" content="#0C1626">
@if (file_exists(public_path('imprint-customs.jpg')))
    <link rel="icon" href="{{ asset('imprint-customs.jpg') }}">
    <link rel="apple-touch-icon" href="{{ asset('imprint-customs.jpg') }}">
@endif
