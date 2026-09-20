<?php

namespace App\Support;

/**
 * "Faces of the floor" - the portrait grid on the Inside page.
 *
 * DROP FILES IN AND THEY APPEAR. public/img/careers/face-1.jpg, face-2.jpg
 * and face-3.jpg, any of jpg / jpeg / png / webp. Nothing else to edit.
 *
 * A caption is optional: add the person's name or their station to the list
 * below against the same number and it is printed under the photograph. Leave
 * it out and the tile is just the portrait.
 *
 * The whole section is hidden until at least one file exists, so the page is
 * finished without them.
 */
class CareersFaces
{
    /** Optional captions, by slot number. */
    private const CAPTIONS = [
        // 1 => 'Joey, marketing',
        // 2 => 'Pau, VIP team',
        // 3 => 'Ysa, Meta team',
    ];

    /*
     * Three, across one row. Uniqlo runs eight in two rows of four; with three
     * photographs a four-column grid would leave a hole on the end of the row,
     * so the grid matches the count.
     */
    private const SLOTS = 3;

    /** @return list<array{src: string, caption: string}> */
    public static function all(): array
    {
        $found = [];

        for ($i = 1; $i <= self::SLOTS; $i++) {
            if ($src = CareersMedia::pic('face-'.$i)) {
                $found[] = ['src' => $src, 'caption' => self::CAPTIONS[$i] ?? ''];
            }
        }

        return $found;
    }

    public static function any(): bool
    {
        return self::all() !== [];
    }
}
