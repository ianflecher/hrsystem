<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * What the company is and what happens in the building.
 *
 * Shared by the public "Who we are" page and the applicant company profile, so
 * the two accounts of the same company cannot drift apart.
 */
class CareersStory
{
    /**
     * The areas of the business, production first and in the order work moves
     * through the floor, then the rest of the site.
     *
     * Not only the floor: the shop, the cafe, the barbershop, the stockroom and
     * the guard post are jobs somebody is hired into too, and a list that named
     * only the presses told a school leaver there was nothing here for them.
     */
    public static function areas(): array
    {
        return [
            ['pic' => 'gallery-1', 'name' => 'Screen printing',
             'body' => 'Artwork burned to screens and pulled by hand, colour by colour. The oldest craft in the building and still the busiest.'],
            ['pic' => 'gallery-2', 'name' => 'Embroidery',
             'body' => 'A row of multi-head machines stitching logos and names straight into the fabric.'],
            ['pic' => 'gallery-3', 'name' => 'Cutting',
             'body' => 'Patterns cut to the millimetre so the panels meet cleanly when they reach the sewers.'],
            ['pic' => 'gallery-4', 'name' => 'Sewing',
             'body' => 'Where the pieces become a garment. The largest team on the floor, and the one that sets the pace.'],
            ['pic' => 'gallery-5', 'name' => 'Press and finishing',
             'body' => 'Heat transfers, final press, inspection and packing. Nothing leaves without somebody looking at it.'],
            ['pic' => 'gallery-6', 'name' => 'The store',
             'body' => 'Our own shop out front, where walk-in customers see the same work you helped make.'],
            ['pic' => 'inventory', 'name' => 'Inventory and stock',
             'body' => 'Materials in, finished orders out, and a count that has to be right before anything ships.'],
            ['pic' => 'cafe-1', 'name' => 'Imprint Cafe',
             'body' => 'The counter beside the shop - drinks, orders and the people who keep both moving.'],
            ['pic' => 'barber-1', 'name' => '21 & Co Barbershop',
             'body' => 'A barbershop under the same roof, with its own trade and its own chair.'],
            ['pic' => 'guard', 'name' => 'Security',
             'body' => 'The guard on the gate - who comes in, what goes out, and the first person anybody meets.'],
        ];
    }

    /**
     * How many areas there are, spelled out for a headline.
     *
     * The headlines used to say "Six trades" in words. The list then went from
     * six to ten in one sitting, and a number typed into a sentence does not
     * notice. This counts.
     */
    public static function areaCountWord(): string
    {
        $words = [
            6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine', 10 => 'Ten',
            11 => 'Eleven', 12 => 'Twelve',
        ];

        $n = count(self::areas());

        return $words[$n] ?? (string) $n;
    }

    /**
     * Counted live rather than typed in. A careers page still claiming a
     * headcount from two years ago is the sort of small untruth that costs more
     * trust than it buys.
     */
    public static function figures(): array
    {
        $figures = [
            ['value' => (int) DB::table('employees')->where('status', 'active')->count(),
             'label' => 'people on the team'],
            ['value' => (int) DB::table('departments')->count(),
             'label' => 'departments'],
            ['value' => (int) DB::table('job_positions')->where('is_open', true)->count(),
             'label' => 'roles open right now'],
        ];

        // A zero is never worth printing. "0 people on the team" on a careers
        // page reads as a dead company rather than as an empty table, so a
        // figure that cannot be counted is simply not claimed.
        return array_values(array_filter($figures, fn ($f) => $f['value'] > 0));
    }
}
