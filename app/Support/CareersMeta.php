<?php

namespace App\Support;

/**
 * What each careers page calls itself, for the tab, for search results and
 * for the card that appears when somebody pastes the link into Messenger.
 *
 * All seven pages used to share one title - "Careers - Imprint Customs PH" -
 * and carried no description and no share tags at all, so a link pasted into
 * a chat showed a bare URL and Google had nothing to list but the address.
 * A job advert travels by being pasted, so this is not decoration.
 *
 * Titles are kept under about 60 characters and descriptions under about 160,
 * which is roughly where Google stops reading.
 */
class CareersMeta
{
    public const SITE = 'Imprint Customs PH';

    /**
     * Keyed by route name. 'share' names the image in public/img/careers/
     * without its extension.
     *
     * @var array<string, array{title: string, description: string, share: string}>
     */
    private const PAGES = [
        'landing' => [
            'title'       => 'Careers at Imprint Customs PH',
            'description' => 'Custom apparel made start to finish in one building. Printing, embroidery, cutting, sewing, the store and the cafe - see what is open and apply in one go.',
            'share'       => 'share-home',
        ],
        'careers' => [
            'title'       => 'Careers at Imprint Customs PH',
            'description' => 'Custom apparel made start to finish in one building. Printing, embroidery, cutting, sewing, the store and the cafe - see what is open and apply in one go.',
            'share'       => 'share-home',
        ],
        'careers.story' => [
            'title'       => 'Who we are',
            'description' => 'Jerseys, uniforms and merchandise designed, printed, embroidered, cut, sewn and sold in the same building by the same team.',
            'share'       => 'share-story',
        ],
        'careers.who' => [
            'title'       => 'Who we hire',
            'description' => 'No degree required and no experience assumed. Students, working students, OJT trainees and fresh graduates all have a way in - and we teach the craft.',
            'share'       => 'share-who',
        ],
        'careers.inside' => [
            'title'       => 'Inside the production floor',
            'description' => 'Screen printing, embroidery, cutting, sewing, press and finishing - every station an order passes through, and the people at them.',
            'share'       => 'share-inside',
        ],
        'careers.front' => [
            'title'       => 'Store, barbershop and cafe',
            'description' => 'The side of the business a customer walks into: Imprint Store, 21 & Co Barbershop and Imprint Cafe, all under one roof.',
            'share'       => 'share-front',
        ],
        'careers.jobs' => [
            'title'       => 'Open positions',
            'description' => 'Every role open at Imprint Customs PH right now, with what the job pays and what comes with it. Apply once and follow your application the whole way through.',
            'share'       => 'share-jobs',
        ],
        'careers.people' => [
            'title'       => 'Our people',
            'description' => 'Sales, social, marketing, the floor, the store and the gate - the people you would be working beside.',
            'share'       => 'share-people',
        ],
    ];

    /** @return array{title: string, full: string, description: string, image: ?string} */
    public static function current(): array
    {
        $name = (string) (request()->route()?->getName() ?? '');
        $page = self::PAGES[$name] ?? self::PAGES['careers'];

        // The home page says the company once, not twice.
        // "Imprint Customs PH Careers" as a suffix pushed the longer titles
        // past 60 characters, which is where Google stops showing them.
        $full = str_contains($page['title'], 'Imprint Customs')
            ? $page['title']
            : $page['title'].' | Imprint Customs Careers';

        return [
            'title'       => $page['title'],
            'full'        => $full,
            'description' => $page['description'],
            'image'       => CareersMedia::pic($page['share']) ?: CareersMedia::pic('hero'),
        ];
    }
}
