<?php

namespace App\Support;

/**
 * The founder's message on the "Who we are" page.
 *
 * FILL THIS IN. Uniqlo's version of this page carries one, and it is the piece
 * that makes a careers page sound like a company rather than a brochure.
 *
 *   name     as it should be printed
 *   role     'Founder', 'Owner', 'Managing Director' - whatever is right
 *   message  their words, as long or short as they like
 *
 * The whole section stays hidden until there is a message, so the page is
 * finished without one. The photograph is optional on top of that: drop
 * public/img/careers/founder.jpg in and it appears, leave it out and the
 * message runs beside a brand panel instead.
 *
 * THE MESSAGE BELOW IS A DRAFT, WRITTEN TO BE REPLACED.
 *
 * It is built only from what the rest of this site already says: everything
 * made under one roof, a small floor, people taught the trade here, a lot of
 * them straight out of school. Nothing in it claims anything the company has
 * not claimed elsewhere.
 *
 * The name is now on it, which means these words read as Gian Karlo Lasam's.
 * They are not his yet - they were drafted here. He should read them and
 * either say yes or give you his own, and his own will be better.
 */
class CareersFounder
{
    private const NAME    = 'Gian Karlo Lasam';
    private const ROLE    = 'Founder';

    /**
     * The line set large above the message, in quotation marks - the one thing
     * to take away if somebody reads nothing else. Uniqlo's is "Becoming a
     * truly global brand that customers across the globe love best".
     *
     * A draft like the message, and the same applies: his words are better.
     */
    private const HEADLINE = 'Everything we sell, we made ourselves.';
    private const MESSAGE = "I started Imprint Customs because I wanted the whole thing under one roof - the design, the printing, the embroidery, the cutting and the sewing. If something goes wrong we fix it ourselves that afternoon instead of ringing a supplier and waiting.

That is still how we work. Most of the people here learned the trade on this floor, and a good number of them came to us straight out of school with no experience at all. If you are willing to learn it properly, we will teach you - and you will watch the finished thing go out of the door with your work on it.";

    public static function has(): bool
    {
        return trim(self::MESSAGE) !== '';
    }

    /** @return array{name: string, role: string, headline: string, message: string, pic: ?string} */
    public static function get(): array
    {
        return [
            'name'     => self::NAME,
            'role'     => self::ROLE,
            'headline' => self::HEADLINE,
            'message'  => self::MESSAGE,
            'pic'      => CareersMedia::pic('founder'),
        ];
    }
}
