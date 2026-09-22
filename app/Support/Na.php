<?php

namespace App\Support;

/**
 * "N/A" is an answer when it stands on its own and a nuisance when it does not.
 *
 * The application form stores it deliberately: a blank optional box means the
 * thing does not apply, and recording that plainly beats leaving a gap that
 * reads as unanswered. But the moment those columns are joined into a sentence
 * the word starts turning up inside it - "Lasam, Gian N/A", a spouse section
 * headed "N/A N/A N/A", an address reading "N/A, Naga City, N/A".
 *
 * So: shown on its own, dropped from anything assembled. This is display only.
 * Nothing here changes what is stored.
 */
class Na
{
    /** The ways people write "nothing here". Matches the form's own list. */
    private const WORDS = [
        'n/a', 'na', 'n.a.', 'n.a', 'n\\a', 'none', 'nil', 'wala', 'not applicable',
        '-', '--', '.', 'x',
    ];

    /** Is this value empty, or one of the words that means empty? */
    public static function blank(mixed $value): bool
    {
        $value = trim((string) $value);

        return $value === '' || in_array(mb_strtolower($value), self::WORDS, true);
    }

    /** The value, or a fallback when it says nothing. */
    public static function show(mixed $value, string $fallback = ''): string
    {
        return self::blank($value) ? $fallback : trim((string) $value);
    }

    /**
     * Join the parts that actually say something.
     *
     * Returns the fallback rather than an empty string when every part is
     * blank, so a caller can tell "nobody filled this in" from "here it is".
     */
    public static function join(array $parts, string $glue = ' ', string $fallback = ''): string
    {
        $kept = array_values(array_filter($parts, fn ($p) => ! self::blank($p)));

        return $kept ? implode($glue, array_map('trim', $kept)) : $fallback;
    }

    /** Surname, First Middle - with any part that says nothing left out. */
    public static function name(?string $surname, ?string $first, ?string $middle = null, string $fallback = ''): string
    {
        $given = self::join([$first, $middle]);
        $family = self::show($surname);

        return match (true) {
            $family !== '' && $given !== '' => $family.', '.$given,
            $family !== ''                  => $family,
            $given !== ''                   => $given,
            default                         => $fallback,
        };
    }
}
