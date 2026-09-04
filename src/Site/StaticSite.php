<?php

namespace Bladewright\Site;

use RuntimeException;
use Throwable;
use ZipArchive;
use Bladewright\Media\MediaLibrary;
use Bladewright\Models\Page;
use Bladewright\Support\SiteCss;

/**
 * The whole site, written out as files somebody can put anywhere.
 *
 * **What the application was serving becomes a file.** The stylesheet was a
 * route and becomes `site.css`; a picture was a route and becomes a file
 * under `media/`; a page was resolved from the database and becomes
 * `about/index.html`, so a plain static host serves `/about` with no rules
 * of its own.
 *
 * What is fetched from somewhere else stays fetched from somewhere else — a
 * framework on a CDN is a link either way, and rewriting it would only make
 * the copy worse.
 *
 * **Nothing is kept here.** The zip is made on the press and handed to the
 * browser; it is a reading of the site, and can always be taken again.
 */
class StaticSite
{
    public function __construct(
        private readonly PublicSite $site,
        private readonly SiteCss $css,
        private readonly MediaLibrary $media,
    ) {}

    /**
     * The pages a visitor could reach.
     *
     * **A page that is a shape is left out** — `news/{slug}` answers a
     * thousand paths or none, and which of them exist is not something a
     * copy of the site can know.
     *
     * @return \Illuminate\Support\Collection<int, Page>
     */
    public function pages()
    {
        return Page::query()
            ->where('is_published', true)
            ->where(fn ($q) => $q->whereNull('published_from')->orWhere('published_from', '<=', now()))
            ->where(fn ($q) => $q->whereNull('published_until')->orWhere('published_until', '>', now()))
            ->orderBy('url')
            ->get()
            ->reject(fn (Page $page) => str_contains($page->url, '{'))
            ->values();
    }

    /** The ones left out, so the screen can say so rather than hide it. */
    public function shaped()
    {
        return Page::query()
            ->where('is_published', true)
            ->where('url', 'like', '%{%')
            ->orderBy('url')
            ->get();
    }

    /** Where a page's file goes: `/` is `index.html`, `/about` is `about/index.html`. */
    public function fileFor(Page $page): string
    {
        $url = trim((string) $page->url, '/');

        return $url === '' ? 'index.html' : $url.'/index.html';
    }

    /**
     * Write the site into a zip at this path.
     *
     * @return array{pages: int, files: int}
     */
    public function writeTo(string $path): array
    {
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException(__('The file could not be made.'));
        }

        $pages = $this->pages();
        $files = 0;

        // The stylesheet, as a file rather than a route.
        $zip->addFromString('site.css', $this->css->get());

        // **Everything the library holds**, folders and all — a page that
        // shows a picture must find it in the copy.
        foreach ($this->media->everything() as $file) {
            $contents = $this->contentsOf($file);

            if ($contents !== null) {
                $zip->addFromString('media/'.$file->path, $contents);
                $files++;
            }
        }

        foreach ($pages as $page) {
            $zip->addFromString($this->fileFor($page), $this->html($page));
        }

        $zip->close();

        return ['pages' => $pages->count(), 'files' => $files];
    }

    /**
     * One page, with what the application was serving turned into files.
     *
     * The depth of the page decides how far back the files are: a page at
     * `/about` reaches its stylesheet at `../site.css`, so the copy works
     * from a folder as readily as from the root of a domain.
     */
    public function html(Page $page): string
    {
        $html = $this->site->page($page);
        $back = str_repeat('../', substr_count(trim((string) $page->url, '/'), '/') + (trim((string) $page->url, '/') === '' ? 0 : 1));

        return $this->rewrite($html, $back);
    }

    /**
     * Point what was a route at what is now a file.
     *
     * **Only our own doors are touched.** A link to somebody else's site, a
     * framework on a CDN, an anchor of the page's own — all left as they
     * were, because a copy that rewrote them would be a worse copy.
     */
    private function rewrite(string $html, string $back): string
    {
        $css = route('bladewright.site.css', [], absolute: false);
        $media = route('bladewright.media', ['path' => '__P__'], absolute: false);
        [$mediaBefore] = explode('__P__', $media);

        // The stylesheet, with or without its version stamp.
        $html = (string) preg_replace(
            '#(?:https?://[^/"\']*)?'.preg_quote($css, '#').'(\?[^"\']*)?#',
            $back.'site.css',
            $html,
        );

        // Every picture, video and file the blocks point at.
        $html = (string) preg_replace(
            '#(?:https?://[^/"\']*)?'.preg_quote($mediaBefore, '#').'#',
            $back.'media/',
            $html,
        );

        return $html;
    }

    /** A media file's bytes, or null where the file has gone missing. */
    private function contentsOf(\Bladewright\Media\MediaFile $file): ?string
    {
        try {
            $disk = $this->media->disk();

            return $disk->exists($file->path) ? $disk->get($file->path) : null;
        } catch (Throwable) {
            return null;
        }
    }
}
