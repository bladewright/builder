<?php

namespace Bladewright\Support;

use Bladewright\Media\MediaLibrary;
use Bladewright\Models\Page;

/**
 * What a page says about itself to the machines.
 *
 * **The words are the page's, the place is the frame's** — search engines
 * index pages, not layouts, so the title and the description live on the
 * page; the layout's head carries `@bwmeta`, and at render time the page
 * being worn fills it in.
 */
class Meta
{
    /**
     * The head lines for this page — or the least a head needs, when there
     * is no page to speak for (a frame previewed on its own).
     */
    public static function tags(?Page $page): string
    {
        $seo = (array) (($page?->data ?? [])['seo'] ?? []);

        // **A page always has a title**: its own words first, its name when
        // none were written, the app's when there is no page at all.
        $title = trim((string) ($seo['title'] ?? '')) ?: trim((string) ($page?->name ?? '')) ?: (string) config('app.name');
        $description = trim((string) ($seo['description'] ?? ''));
        $image = trim((string) ($seo['image'] ?? ''));

        $lines = ['<title>'.e($title).'</title>'];

        if (! empty($seo['noindex'])) {
            $lines[] = '<meta name="robots" content="noindex, nofollow">';
        }

        if ($description !== '') {
            $lines[] = '<meta name="description" content="'.e($description).'">';
        }

        $lines[] = '<meta property="og:title" content="'.e($title).'">';

        if ($description !== '') {
            $lines[] = '<meta property="og:description" content="'.e($description).'">';
        }

        if ($image !== '') {
            $lines[] = '<meta property="og:image" content="'.e(self::picture($image)).'">';
        }

        return implode("\n    ", $lines);
    }

    /** og:image must be a URL a crawler can fetch — absolute, whole. */
    private static function picture(string $value): string
    {
        $library = app(MediaLibrary::class);
        $url = $library->owns($value) ? ($library->find($value)?->url() ?? $value) : $value;

        return url($url);
    }
}
