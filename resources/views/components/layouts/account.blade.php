{{-- The account page is the one screen every signed-in person can reach, so it
     wears whichever chrome that person already knows: the HR sidebar for the
     back office, the staff header for everyone else.

     Without this, an employee opening their account would land in the HR
     sidebar, which lists modules they cannot use. --}}
@php
    $backOffice = in_array(auth()->user()->role ?? '', ['admin', 'hr'], true);
@endphp

@if ($backOffice)
    <x-layouts.app.humanresource :title="$title ?? 'My account'">
        {{ $slot }}
    </x-layouts.app.humanresource>
@else
    <x-layouts.app.employeeland :title="$title ?? 'My account'">
        {{ $slot }}
    </x-layouts.app.employeeland>
@endif
