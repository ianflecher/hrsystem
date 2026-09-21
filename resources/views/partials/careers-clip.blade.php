{{-- The player on its own. The home page and the Inside page both show it,
     each under its own heading, so only the player is shared. --}}
@php
    $slot = $clipSlot ?? 1;
    $clip = \App\Support\CareersMedia::clip($slot);
    $poster = \App\Support\CareersMedia::poster($slot);
@endphp
            <div class="clip" @if ($stationsAbove ?? false) style="margin-top: 34px" @endif>
                @if ($clip)
                    {{-- Muted and inline, so a phone plays it where it sits
                         instead of hijacking the screen. Controls stay on: an
                         autoplaying clip somebody cannot stop is a nuisance. --}}
                    <video class="clip__video"
                           controls
                           muted
                           loop
                           playsinline
                           preload="metadata"
                           @if ($poster) poster="{{ $poster }}" @endif>
                        <source src="{{ $clip }}" type="video/mp4">
                        Your browser cannot play this video.
                    </video>
                @else
                    <div class="clip__empty">
                        <i class="fas fa-circle-play"></i>
                        <p>Footage from the floor is on its way.</p>
                    </div>
                @endif
            </div>
