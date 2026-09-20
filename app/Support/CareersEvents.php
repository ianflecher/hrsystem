<?php

namespace App\Support;

/**
 * Company events - what happens here when nobody is working.
 *
 * ADDING AN EVENT
 *
 *   1. Cut the photographs into public/img/careers/ as
 *      event-<slug>-1.jpg, event-<slug>-2.jpg and so on. The first one is
 *      the wide frame across the top, so pick a 16:9 crop for it; the rest
 *      are 3:2 tiles.
 *   2. Add an entry below with that slug.
 *
 * An event with no photographs is not drawn at all, and the whole section
 * disappears when there are none - so an entry can be written before the shoot
 * and will appear by itself when the files land.
 *
 * ON PUBLISHING THESE AT ALL
 *
 * These are identifiable employees at a company party on a public website,
 * which is a different thing from the same photographs on a noticeboard
 * inside the building. Anybody clearly recognisable should have said yes, and
 * somebody should look at what else is in frame - drinks, a child, a
 * whiteboard with a client's name on it - before a photograph goes up.
 */
class CareersEvents
{
    private const SLOTS = 12;

    private const EVENTS = [
        [
            'slug'  => 'sportsfest',
            'title' => 'Sportsfest 2026',
            // The date you gave for the event itself. The folders on the
            // share are dated later because that is when the photos came out.
            'when'  => 'June 30, 2026',
            'body'  => 'Weeks of basketball, billiards and Mobile Legends, run '
                      .'between shifts and finished with a court full of the '
                      .'whole company. Teams are drawn from across the floor, so '
                      .'the sewer and the sales agent end up on the same side.',
            'captions' => [
                1 => 'The whole company, finals day',
                2 => 'Basketball, and nobody going up softly',
                3 => 'Billiards, third week',
                4 => 'The Mobile Legends bracket',
                5 => 'Cheerdance',
                6 => 'Awarding',
                7 => 'Out front before the opening',
            ],
        ],
    ];

    /**
     * Events that actually have photographs, newest first.
     *
     * @return list<array{slug: string, title: string, when: string, body: string, hero: ?string, heroCaption: string, shots: list<array{src: string, caption: string}>}>
     */
    public static function all(): array
    {
        $events = [];

        foreach (self::EVENTS as $event) {
            $shots = [];

            for ($i = 1; $i <= self::SLOTS; $i++) {
                if ($src = CareersMedia::pic('event-'.$event['slug'].'-'.$i)) {
                    $shots[] = ['src' => $src, 'caption' => $event['captions'][$i] ?? ''];
                }
            }

            if ($shots === []) {
                continue;
            }

            // The first frame leads, so it is pulled out of the grid rather
            // than sitting in it as one tile among several.
            $hero = array_shift($shots);

            $events[] = [
                'slug'        => $event['slug'],
                'title'       => $event['title'],
                'when'        => $event['when'],
                'body'        => $event['body'],
                'hero'        => $hero['src'],
                'heroCaption' => $hero['caption'],
                'shots'       => $shots,
            ];
        }

        return $events;
    }

    public static function any(): bool
    {
        return self::all() !== [];
    }
}
