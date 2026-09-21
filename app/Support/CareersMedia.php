<?php

namespace App\Support;

/**
 * The optional picture and video slots the careers pages share.
 *
 * Static because the careers site is several pages now and each one would
 * otherwise carry its own copy of the same lookup.
 *
 * Every slot is a filename under public/img/careers/. Drop the file in and it
 * appears; leave it out and the caller renders its own fallback, so the pages
 * are finished before any photograph exists.
 *
 * Deliberately not public/careers/: a directory sitting at the same path as a
 * route is served by the web server before Laravel sees the request, and
 * /careers then 404s.
 */
class CareersMedia
{
    /** A picture slot, or null when the file is not there. */
    public static function pic(string $name): ?string
    {
        foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
            if (file_exists(public_path("img/careers/{$name}.{$ext}"))) {
                return asset("img/careers/{$name}.{$ext}");
            }
        }

        return null;
    }

    /**
     * A video slot, or null.
     *
     * Two of them, because the home page and the Inside page each show a clip
     * and showing the same one twice made the site look like it had one minute
     * of footage. Slot 1 is inside.mp4, slot 2 inside-2.mp4, and a slot with no
     * file shows the "on its way" panel rather than borrowing the other's.
     */
    public static function clip(int $slot = 1): ?string
    {
        $name = $slot === 1 ? 'inside' : 'inside-'.$slot;

        foreach (['mp4', 'webm'] as $ext) {
            if (file_exists(public_path("img/careers/{$name}.{$ext}"))) {
                return asset("img/careers/{$name}.{$ext}");
            }
        }

        return null;
    }

    /** The still shown before a slot plays. */
    public static function poster(int $slot = 1): ?string
    {
        return self::pic($slot === 1 ? 'video-poster' : 'video-poster-'.$slot);
    }
}
