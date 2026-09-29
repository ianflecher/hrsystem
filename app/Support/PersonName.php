<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * A name in parts, and the two things every screen needs from it.
 *
 * Both places that create a person - HR adding an employee, and a candidate
 * registering - used to take one "full name" box. That box could not be sorted
 * by surname, could not fill a government form that asks for a middle name,
 * and produced a different username at each end: the register slugged the
 * whole name into juan.dela.cruz while the import made juan.delacruz.
 *
 * Kept here so the two cannot drift apart again.
 */
class PersonName
{
    /** Suffixes are not names and are not title-cased. */
    private const SUFFIXES = ['II', 'III', 'IV', 'V', 'JR', 'SR'];

    /** "Juan", "Dela Cruz", "Santos" -> "Juan Santos Dela Cruz". */
    public static function full(?string $first, ?string $middle, ?string $last): string
    {
        // A second given name typed into the middle name as well - "Roi
        // Vincent" / "Vincent" - would read "Roi Vincent Vincent". The middle
        // name starts after whatever the first name already ends with.
        $firstWords = preg_split('/\s+/', trim((string) $first), -1, PREG_SPLIT_NO_EMPTY);
        $middleWords = preg_split('/\s+/', trim((string) $middle), -1, PREG_SPLIT_NO_EMPTY);
        while ($middleWords && $firstWords && strcasecmp($middleWords[0], end($firstWords)) === 0) {
            array_shift($middleWords);
        }

        return trim(preg_replace('/\s+/', ' ',
            implode(' ', array_filter([trim((string) $first), implode(' ', $middleWords), trim((string) $last)]))));
    }

    /**
     * Title case that leaves suffixes alone, so somebody does not become
     * "Rebato Iii" on their own payslip.
     */
    public static function tidy(?string $name): string
    {
        $words = preg_split('/\s+/', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return implode(' ', array_map(function (string $word): string {
            return in_array(strtoupper(rtrim($word, '.')), self::SUFFIXES, true)
                ? strtoupper($word)
                : mb_convert_case(mb_strtolower($word), MB_CASE_TITLE, 'UTF-8');
        }, $words));
    }

    /**
     * firstname.lastname, free of accents and punctuation, and not already
     * taken. The middle name is left out: it makes the login longer without
     * making it more distinguishing.
     */
    public static function username(?string $first, ?string $last, ?int $ignoreUserId = null): string
    {
        $base = trim(self::slug($first).'.'.self::slug($last), '.');

        if ($base === '') {
            $base = 'user';
        }

        $username = $base;
        $n = 2;

        while (self::taken($username, $ignoreUserId)) {
            $username = $base.$n;
            $n++;
        }

        return $username;
    }

    private static function taken(string $username, ?int $ignoreUserId): bool
    {
        return DB::table('users')->where('username', $username)
            ->when($ignoreUserId, fn ($q) => $q->where('user_id', '!=', $ignoreUserId))
            ->exists();
    }

    /**
     * Best guess at the parts of a name that was only ever stored whole.
     *
     * The last word is taken as the surname, which is right for most of them
     * and wrong for anybody with a suffix or a two-word surname. It is a guess
     * and it is offered as one: it fills the boxes on the form so the record
     * can be opened and corrected, rather than refusing to save until somebody
     * splits it by hand. Records imported with their parts never come here.
     *
     * @return array{first: string, middle: string, last: string}
     */
    public static function split(?string $full): array
    {
        $words = preg_split('/\s+/', trim((string) $full), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (! $words) {
            return ['first' => '', 'middle' => '', 'last' => ''];
        }

        if (count($words) === 1) {
            return ['first' => $words[0], 'middle' => '', 'last' => ''];
        }

        // A trailing suffix belongs with the surname, not instead of it.
        $last = array_pop($words);

        if (in_array(strtoupper(rtrim($last, '.')), self::SUFFIXES, true) && $words) {
            $last = array_pop($words).' '.$last;
        }

        return [
            'first' => array_shift($words),
            'middle' => implode(' ', $words),
            'last' => $last,
        ];
    }

    /** Ñ becomes n, so a login can be typed on any keyboard. */
    private static function slug(?string $part): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', (string) $part);

        return preg_replace('/[^a-z]/', '', strtolower((string) $ascii)) ?? '';
    }
}
