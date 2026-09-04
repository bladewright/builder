<?php

namespace Bladewright\Site;

use Bladewright\Models\Page;

/**
 * Which page answers a path, and what the path told us.
 *
 * Most pages are a plain URL and match one path. **A page may also carry
 * openings** — `news/{slug}` — and then it answers every path of that shape,
 * with what stood in the opening handed to it as a word it can use.
 *
 * **A plain URL always wins.** A site with `news/{slug}` and a written page
 * at `news/about-us` serves the written one; the pattern takes the rest.
 * Between two patterns, the one with fewer openings answers first, so
 * `news/{slug}` beats `{a}/{b}` — the more a page says, the better its
 * claim.
 */
class PageRoutes
{
    /** What a name inside an opening may be. */
    private const NAME = '/^\{[a-z_][a-z0-9_]*\}$/i';

    /**
     * The page for this path, and the words the path gave up.
     *
     * @return array{0: \Bladewright\Models\Page, 1: array<string, string>}|null
     */
    public function match(string $url): ?array
    {
        $url = trim($url, '/');

        // **The plain one first, in one question.** Nothing is scanned while
        // a page simply is where it says it is.
        $exact = Page::query()->where('url', $url)->first();

        if ($exact !== null) {
            return [$exact, []];
        }

        $wanted = $url === '' ? [] : explode('/', $url);

        foreach ($this->patterns($wanted) as $page) {
            $given = $this->fill($page->url, $wanted);

            if ($given !== null) {
                return [$page, $given];
            }
        }

        return null;
    }

    /**
     * The pages with openings, the most particular first.
     *
     * Only ones of the same depth are asked at all: a path of two segments
     * can never be answered by a pattern of three.
     */
    private function patterns(array $wanted)
    {
        $pages = Page::query()
            ->where('url', 'like', '%{%')
            ->get()
            ->filter(fn (Page $page) => count(explode('/', $page->url)) === count($wanted));

        return $pages->sortBy([
            fn (Page $a, Page $b) => $this->openings($a->url) <=> $this->openings($b->url),
            fn (Page $a, Page $b) => strcmp($a->url, $b->url),
        ]);
    }

    private function openings(string $url): int
    {
        return substr_count($url, '{');
    }

    /**
     * What this pattern makes of these segments, or null where it makes
     * nothing of them.
     *
     * **An opening never swallows a slash** — the segments were split before
     * they got here — and **never matches nothing**, so `/news/` is not a
     * news item with an empty name.
     *
     * @return array<string, string>|null
     */
    private function fill(string $pattern, array $wanted): ?array
    {
        $parts = $pattern === '' ? [] : explode('/', $pattern);

        if (count($parts) !== count($wanted)) {
            return null;
        }

        $given = [];

        foreach ($parts as $at => $part) {
            $said = $wanted[$at];

            if (preg_match(self::NAME, $part) !== 1) {
                if ($part !== $said) {
                    return null;
                }

                continue;
            }

            if ($said === '') {
                return null;
            }

            $given[trim($part, '{}')] = $said;
        }

        return $given;
    }

    /** Does this URL carry any openings at all? */
    public function isPattern(string $url): bool
    {
        return str_contains($url, '{');
    }

    /**
     * What is wrong with the openings in this URL, if anything.
     *
     * Said as a person would need to hear it: the shape of a name, and that
     * one opening is a whole segment rather than a part of one.
     */
    public function problem(string $url): ?string
    {
        $url = trim(trim($url), '/');

        if (! $this->isPattern($url)) {
            return null;
        }

        foreach (explode('/', $url) as $part) {
            if (! str_contains($part, '{') && ! str_contains($part, '}')) {
                continue;
            }

            if (preg_match(self::NAME, $part) !== 1) {
                return __('[:part] is not an opening. One whole piece of the path, named in braces: news/{slug}.', ['part' => $part]);
            }
        }

        $names = [];

        foreach (explode('/', $url) as $part) {
            if (preg_match(self::NAME, $part) === 1) {
                $name = trim($part, '{}');

                if (in_array($name, $names, true)) {
                    return __('[:name] is used twice, so there is no saying which is which.', ['name' => $name]);
                }

                $names[] = $name;
            }
        }

        return null;
    }
}
