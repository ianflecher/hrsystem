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

        // Twelve slots rather than six: the strip was only the studio session,
        // so the shop, the barbershop and the gate - the posed portraits from
        // the later shoots - had nowhere to go. Any slot without a file is
        // simply skipped.
        for ($i = 1; $i <= 12; $i++) {
            if ($src = CareersMedia::pic('team-'.$i)) {
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
