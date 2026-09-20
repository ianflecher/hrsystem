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

    /** The one video slot, or null. */
    public static function clip(): ?string
    {
        foreach (['mp4', 'webm'] as $ext) {
            if (file_exists(public_path("img/careers/inside.{$ext}"))) {
                return asset("img/careers/inside.{$ext}");
            }
        }

        return null;
    }
}
