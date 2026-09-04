<?php

namespace Bladewright\Content;

/**
 * Turn a video or map URL into something embeddable.
 *
 * **Only places we know are embedded.** Putting whatever URL was typed
 * into an iframe opens the customer's page to anything at all — adverts,
 * or a form that impersonates them. Anywhere we do not recognise comes out
 * as a plain link instead.
 *
 * Adding a provider is one line here. The block never changes.
 */
class Embed
{
    /**
     * The URL to embed, or null when we do not know the place.
     */
    public static function source(string $url): ?string
    {
        $url = trim($url);

        if ($url === '') {
            return null;
        }

        $parts = parse_url($url);

        if (! is_array($parts) || ($parts['scheme'] ?? 'https') !== 'https') {
            return null;
        }

        $host = strtolower($parts['host'] ?? '');
        $path = $parts['path'] ?? '';
        parse_str($parts['query'] ?? '', $query);

        // YouTube. **Use the nocookie host** so watching alone is not tracked.
        if ($host === 'youtu.be') {
            return self::youtube(ltrim($path, '/'));
        }

        if (in_array($host, ['www.youtube.com', 'youtube.com', 'm.youtube.com', 'www.youtube-nocookie.com'], true)) {
            return self::youtube(
                str_starts_with($path, '/embed/')
                    ? substr($path, 7)
                    : (string) ($query['v'] ?? ''),
            );
        }

        if (in_array($host, ['vimeo.com', 'www.vimeo.com', 'player.vimeo.com'], true)) {
            return self::vimeo(basename($path));
        }

        // Google Maps only in the form its "embed a map" gives you.
        // An ordinary maps URL cannot go in an iframe, so it stays a link.
        if (str_ends_with($host, 'google.com') && str_starts_with($path, '/maps/embed')) {
            return $url;
        }

        return null;
    }

    private static function youtube(string $id): ?string
    {
        $id = explode('?', $id)[0];

        return preg_match('/^[A-Za-z0-9_-]{6,20}$/', $id) === 1
            ? 'https://www.youtube-nocookie.com/embed/'.$id
            : null;
    }

    private static function vimeo(string $id): ?string
    {
        return preg_match('/^[0-9]{5,20}$/', $id) === 1
            ? 'https://player.vimeo.com/video/'.$id
            : null;
    }
}
