<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * What a careers page tells a search engine and a chat window about itself.
 *
 * A job advert travels by being pasted into Messenger, so the card that
 * appears there is not decoration - it is most of the first impression. All
 * seven pages once shared one title and had no share tags at all.
 */
class CareersMetaTest extends TestCase
{
    public static function pages(): array
    {
        return [
            'home'        => ['/'],
            'who we are'  => ['/careers/who-we-are'],
            'who we hire' => ['/careers/who-we-hire'],
            'inside'      => ['/careers/into-imprint'],
            'front'       => ['/careers/front'],
            'jobs'        => ['/careers/jobs'],
            'our people'  => ['/careers/our-people'],
        ];
    }

    #[DataProvider('pages')]
    public function test_each_page_says_who_it_is(string $uri): void
    {
        $html = $this->get($uri)->assertOk()->getContent();

        $title = $this->tag($html, '~<title>(.*?)</title>~s');
        $description = $this->tag($html, '~name="description" content="([^"]+)"~');

        $this->assertNotSame('', $title, "{$uri} has no title");
        $this->assertNotSame('', $description, "{$uri} has no description");

        // Roughly where Google stops showing them.
        $this->assertLessThanOrEqual(60, strlen($title), "the title on {$uri} is too long to be shown in full");
        $this->assertLessThanOrEqual(160, strlen($description), "the description on {$uri} is too long to be shown in full");
    }

    #[DataProvider('pages')]
    public function test_each_page_carries_a_card_for_a_pasted_link(string $uri): void
    {
        $html = $this->get($uri)->getContent();

        foreach (['og:title', 'og:description', 'og:url', 'og:image', 'og:type'] as $property) {
            $this->assertStringContainsString(
                'property="'.$property.'"', $html,
                "{$uri} is missing {$property}, so a pasted link shows a bare address"
            );
        }

        $this->assertStringContainsString('name="twitter:card"', $html);

        // The picture has to be reachable, and it has to be the shape the
        // chat apps read - they crop anything else themselves, usually
        // through somebody's face.
        $image = $this->tag($html, '~property="og:image" content="([^"]+)"~');
        $this->assertNotSame('', $image, "{$uri} names no share picture");

        $path = public_path(ltrim((string) parse_url($image, PHP_URL_PATH), '/'));
        $this->assertFileExists($path, "the share picture for {$uri} is not on disk");

        [$width, $height] = getimagesize($path);
        $this->assertSame(1200, $width);
        $this->assertSame(630, $height);
    }

    public function test_no_two_pages_answer_to_the_same_title(): void
    {
        $titles = [];

        foreach (self::pages() as $label => [$uri]) {
            // "/" and /careers are one page under two names, so the home page
            // is counted once.
            if ($label === 'home') {
                continue;
            }

            $titles[$uri] = $this->tag($this->get($uri)->getContent(), '~<title>(.*?)</title>~s');
        }

        $this->assertSame(
            count($titles), count(array_unique($titles)),
            'two pages share a title: '.implode(' | ', $titles)
        );
    }

    private function tag(string $html, string $pattern): string
    {
        preg_match($pattern, $html, $m);

        return trim($m[1] ?? '');
    }
}
