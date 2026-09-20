<?php

namespace App\Support;

/**
 * The workstation photographs, in the order work passes through the building.
 *
 * Two pages show these - the public careers page and the applicant company
 * profile - so the list lives here rather than being typed into both. A
 * caption corrected in one place is corrected in both.
 *
 * The captions are a plain reading of each photograph and some are informed
 * guesses: the machines are distinguishable but the house names for them are
 * not. Correct any that are wrong; the order matches station-1 .. station-N.
 */
class Workstations
{
    public const CAPTIONS = [
        'Screen printing press',
        'Large-format printing',
        'Sublimation printing',
        'Transfer preparation',
        'Peeling and weeding',
        'Garment preparation',
        'Layout tables',
        'Checking and sorting',
        'Roll feed',
        'Print roll handling',
        'Loading the printer',
        'Embroidery line',
        'Laser cutting',
        'Cutting control station',
        'Finishing and inspection',
        'Sewing station',
        'Materials store',
        'Stock and supplies',
        // The back office, at the end because the job ends there: the sales
        // agent who took the order and the HR desk behind them.
        'Sales desk',
        'The HR desk',
    ];

    /**
     * Those whose image file is actually present.
     *
     * Removing a station-N.jpg drops that tile and the rest close up, so the
     * set can be trimmed without touching any code.
     *
     * @param  int|null  $limit  show at most this many
     * @return list<array{src: string, caption: string}>
     */
    public static function all(?int $limit = null): array
    {
        $found = [];

        foreach (self::CAPTIONS as $i => $caption) {
            $src = self::pic('station-'.($i + 1));

            if ($src === null) {
                continue;
            }

            $found[] = ['src' => $src, 'caption' => $caption];

            if ($limit !== null && count($found) >= $limit) {
                break;
            }
        }

        return $found;
    }

    /** How many exist, whatever is being shown. */
    public static function count(): int
    {
        return count(self::all());
    }

    /** An image slot under public/img/careers/, or null when absent. */
    public static function pic(string $name): ?string
    {
        foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
            if (file_exists(public_path("img/careers/{$name}.{$ext}"))) {
                return asset("img/careers/{$name}.{$ext}");
            }
        }

        return null;
    }
}
