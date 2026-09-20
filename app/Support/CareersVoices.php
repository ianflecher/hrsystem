<?php

namespace App\Support;

/**
 * The people on the "our people" cards.
 *
 * FILL THESE IN. Three things per person, and nothing is invented for them:
 *
 *   role   the red badge across the top of the card, e.g. 'Production lead'
 *   name   as it should appear under the quote, e.g. 'Meaghan M.'
 *   quote  one sentence they have actually agreed to have published
 *
 * Any field left blank is simply not drawn, so a card with only a photograph
 * is a photograph rather than an empty caption bar, and the section works today
 * while the quotes are still being collected.
 *
 * THE QUOTES BELOW ARE DRAFTS, NOT YET APPROVED.
 *
 * They were written to be shown to Pau, Ysa and Joey - not by them. The
 * photographs are real, identifiable employees, so a sentence under a face
 * reads as that person's words. Before this site is public, each of the three
 * has to read their own line and either confirm it or give you a better one,
 * and their reply is what belongs here.
 *
 * Nothing in them is invented about the company: Joey did their OJT here while
 * still in college and was taken on afterwards, Ysa joined straight out of
 * school, and Pau is regular staff on the VIP team. They are written to
 * be easy to say yes or no to.
 */
class CareersVoices
{
    private const PEOPLE = [
        ['pic' => 'voice-1', 'role' => 'Sales agent, VIP team', 'name' => 'Pau',
         'quote' => 'Most of my clients come back, and that is the part I like - you end up building something with them, not just closing an order.'],

        ['pic' => 'voice-2', 'role' => 'Meta team', 'name' => 'Ysa',
         'quote' => 'I came in straight out of school with no work experience at all. They taught me the system and then let me run with it.'],

        ['pic' => 'voice-3', 'role' => 'Marketing team', 'name' => 'Joey',
         'quote' => 'I did my OJT here in college and they took me on afterwards. It was my first job and nobody expected me to know everything already.'],
    ];

    /** @return list<array{src: string, role: string, name: string, quote: string, hasWords: bool}> */
    public static function all(): array
    {
        $cards = [];

        foreach (self::PEOPLE as $person) {
            $src = CareersMedia::pic($person['pic']);

            if ($src === null) {
                continue;
            }

            $cards[] = [
                'src'      => $src,
                'role'     => $person['role'],
                'name'     => $person['name'],
                'quote'    => $person['quote'],
                'hasWords' => $person['role'] !== '' || $person['name'] !== '' || $person['quote'] !== '',
            ];
        }

        return $cards;
    }

    /** Whether anybody has actually been quoted yet. */
    public static function anyQuoted(): bool
    {
        foreach (self::all() as $card) {
            if ($card['quote'] !== '') {
                return true;
            }
        }

        return false;
    }
}
