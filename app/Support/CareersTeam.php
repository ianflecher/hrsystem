<?php

namespace App\Support;

/**
 * The team photographs for the careers site.
 *
 * One frame per person, then the group frames. Several shots of the same face
 * read as a mistake rather than as a bigger team, so the portraits are one
 * each; the group frames genuinely differ - a line-up, a wave, peace signs -
 * so several of them read as the same people having a laugh.
 *
 * Each entry only appears when its file is present, so the strip shrinks
 * quietly rather than leaving gaps.
 */
class CareersTeam
{
    /** @return list<array{src: string, wide: bool}> */
    public static function all(): array
    {
        $shots = [];

        foreach (['team-1', 'team-2', 'team-3', 'team-4', 'team-5', 'team-6'] as $name) {
            if ($src = CareersMedia::pic($name)) {
                $shots[] = ['src' => $src, 'wide' => false];
            }
        }

        for ($i = 1; $i <= 12; $i++) {
            if ($src = CareersMedia::pic('team-group-'.$i)) {
                $shots[] = ['src' => $src, 'wide' => true];
            }
        }

        return $shots;
    }
}
