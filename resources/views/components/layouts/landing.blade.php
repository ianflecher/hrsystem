{{-- The landing page is a full-bleed hero, so the slot goes in bare rather
     than inside <flux:main>. That wrapper adds p-6/lg:p-8, which boxed the
     photograph inside a 32px white frame.

     No padding of our own here: the landingpage layout already wraps the slot
     in <main class="pt-16">, which is what clears the fixed 60px header. --}}
<x-layouts.app.landingpage :title="$title ?? null">
    {{ $slot }}
</x-layouts.app.landingpage>
