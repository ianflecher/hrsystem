{{-- The pages on this layout are full-bleed - the careers hero runs its
     photograph edge to edge - so the slot goes in bare rather than inside
     <flux:main>, whose p-6/lg:p-8 drew a white frame around them.

     No padding of our own: app/employee wraps the slot in <main class="pt-16">,
     which is what clears the fixed header, and each page brings its own
     horizontal padding. --}}
<x-layouts.app.employee :title="$title ?? null">
    {{ $slot }}
</x-layouts.app.employee>
