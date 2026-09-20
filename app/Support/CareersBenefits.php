<?php

namespace App\Support;

/**
 * The pay and benefits cards.
 *
 * Shared between the benefits page and the home page, which shows the first few
 * as a taster. One list, so the two can never disagree about what the discount
 * is.
 */
class CareersBenefits
{
    /** @return list<array{eyebrow: string, title: string, span: int, body: string}> */
    public static function cards(?int $limit = null): array
    {
        $cards = [
            ['eyebrow' => 'Compensation', 'title' => 'Competitive pay', 'span' => 4,
             'body' => 'Set against the going rate for the role and your experience, and reviewed as you take on more.'],
            ['eyebrow' => 'Employee discount', 'title' => '50% off our own merchandise', 'span' => 4,
             'body' => 'Half price on Imprint Customs apparel for regular employees - the same work you helped make.'],
            ['eyebrow' => 'Long-term growth', 'title' => 'Room to move up', 'span' => 4,
             'body' => 'Clear paths from the floor to team lead and beyond, reviewed every cycle rather than whenever somebody remembers.'],
            ['eyebrow' => 'The work', 'title' => 'Real products, real customers', 'span' => 6,
             'body' => 'Custom printed apparel that ships to actual people - you see the finished thing, not a ticket closing.'],
            ['eyebrow' => 'The team', 'title' => 'A small floor, heard easily', 'span' => 6,
             'body' => 'Small enough that a good idea reaches the people who can approve it the same week.'],
        ];

        return $limit === null ? $cards : array_slice($cards, 0, $limit);
    }
}
