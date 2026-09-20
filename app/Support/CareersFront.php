<?php

namespace App\Support;

/**
 * Out front: Imprint Store, 21 & Co Barbershop and Imprint Cafe.
 *
 * Three separate businesses sharing one frontage, so each gets its own
 * section rather than being folded into the shop. A section is drawn only
 * when at least one of its pictures exists, so one can wait for its shoot
 * without leaving a hole where a section should be.
 *
 *   Store    public/img/careers/front-1.jpg  .. front-4.jpg
 *   Barber   public/img/careers/barber-1.jpg .. barber-4.jpg
 *   Cafe     public/img/careers/cafe-1.jpg   .. cafe-4.jpg
 *
 * Captions are optional and live below.
 */
class CareersFront
{
    // Three, because the grid is three across: three fills the row exactly,
    // and a fourth sat alone on a second row looking like a mistake. Six
    // would work too if a section ever earns that many.
    private const SLOTS = 3;

    // Nobody looking down the lens in this set - the tiles are people at
    // work. The three standing portraits from the same shoot are on Who we
    // hire instead, where a person looking straight at you is the point.
    private const STORE_CAPTIONS = [
        1 => 'Restocking the racks',
        2 => 'Helmets and gear',
        3 => 'Our own apparel, out front',
    ];

    private const BARBER_CAPTIONS = [
        1 => 'The chair',
        2 => 'Mid-cut',
        3 => 'Barber and supply co.',
    ];

    private const CAFE_CAPTIONS = [
        1 => 'The counter',
    ];

    /** @return list<array{src: string, caption: string}> */
    public static function store(): array
    {
        return self::gather('front-', self::STORE_CAPTIONS);
    }

    /** @return list<array{src: string, caption: string}> */
    public static function barber(): array
    {
        return self::gather('barber-', self::BARBER_CAPTIONS);
    }

    /** @return list<array{src: string, caption: string}> */
    public static function cafe(): array
    {
        return self::gather('cafe-', self::CAFE_CAPTIONS);
    }

    /** @return list<array{src: string, caption: string}> */
    private static function gather(string $prefix, array $captions): array
    {
        $found = [];

        for ($i = 1; $i <= self::SLOTS; $i++) {
            if ($src = CareersMedia::pic($prefix.$i)) {
                $found[] = ['src' => $src, 'caption' => $captions[$i] ?? ''];
            }
        }

        return $found;
    }
}
