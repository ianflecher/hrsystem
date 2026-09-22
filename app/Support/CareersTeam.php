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

        // Sixteen slots rather than twelve, and twelve rather than six before
        // that: each shoot - the shop, the barbershop, the gate, and now the
        // cafe - has arrived with people who had nowhere to stand. Any slot
        // without a file is simply skipped, so the ceiling costs nothing.
        for ($i = 1; $i <= 16; $i++) {
            if ($src = CareersMedia::pic('team-'.$i)) {
                $shots[] = ['src' => $src, 'wide' => false];
            }
        }

        for ($i = 1; $i <= 16; $i++) {
            if ($src = CareersMedia::pic('team-group-'.$i)) {
                $shots[] = ['src' => $src, 'wide' => true];
            }
        }

        return $shots;
    }
}
