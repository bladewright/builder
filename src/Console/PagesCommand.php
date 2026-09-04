<?php

namespace Bladewright\Console;

use Illuminate\Console\Command;
use InvalidArgumentException;
use Bladewright\Blocks\SitePages;
use Bladewright\Models\Page;

/**
 * Pages — the top of the four-layer model (the owner's table, 2026-09-02).
 *
 * A page answers for its URL, its publishing, its SEO and its settings; what
 * it shows is components, put on with `bladewright:components --insert=`.
 * Error pages are set aside for a better design, on the owner's word.
 *
 * **These pages are not served yet** — the old world still answers the
 * site's requests until the integration step.
 */
class PagesCommand extends Command
{
    protected $signature = 'bladewright:pages
        {--search= : Show only names containing this}
        {--create= : Name of the new page}
        {--url= : Its URL ("" is the front page) — with --create}
        {--layout= : The layout it wears (it stands bare without one, and is told so)}
        {--copy= : The page to copy}
        {--rename= : The page to rename}
        {--to= : The new name (--copy, --rename)}
        {--publish= : The page to publish}
        {--from= : When it goes up (at once when omitted)}
        {--until= : When it comes down (never when omitted)}
        {--delete= : The page to delete (asks first)}';

    protected $description = 'The pages: list, create, copy, rename, publish, delete';

    public function handle(SitePages $pages): int
    {
        if (($name = $this->option('create')) !== null) {
            return $this->create($pages, (string) $name);
        }

        if (($name = $this->option('copy')) !== null) {
            return $this->copy($pages, (string) $name);
        }

        if (($name = $this->option('rename')) !== null) {
            return $this->rename($pages, (string) $name);
        }

        if (($name = $this->option('publish')) !== null) {
            return $this->publish($pages, (string) $name);
        }

        if (($name = $this->option('delete')) !== null) {
            return $this->delete($pages, (string) $name);
        }

        return $this->show((string) ($this->option('search') ?? ''));
    }

    private function create(SitePages $pages, string $name): int
    {
        $url = $this->option('url');

        if ($url === null) {
            $this->components->error('Say the URL with --url= ("" is the front page).');

            return self::FAILURE;
        }

        try {
            $page = $pages->create($name, (string) $url, $this->option('layout'));
        } catch (InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info("[{$page->name}] is at /{$page->url}. bladewright:components --insert= puts components onto it.");

        if ($page->layout_uuid === null) {
            // The owner's table: the layout is optional, **and its absence is
            // said out loud** — a page with no frame comes out bare.
            $this->components->warn('It wears no layout, so it will come out bare. The page settings change that.');
        }

        return self::SUCCESS;
    }

    private function copy(SitePages $pages, string $name): int
    {
        $page = $pages->find($name);

        if ($page === null) {
            $this->components->error("[{$name}] is not a page.");

            return self::FAILURE;
        }

        $to = (string) ($this->option('to') ?? '');

        if ($to === '') {
            $this->components->error('Say what the copy is called with --to="new name".');

            return self::FAILURE;
        }

        try {
            $copy = $pages->copy($page, $to);
        } catch (InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        // **References travel; contents do not.** And never published.
        $this->components->info("[{$copy->name}] is at /{$copy->url}, not published. It shows the same components as [{$page->name}].");

        return self::SUCCESS;
    }

    private function rename(SitePages $pages, string $name): int
    {
        $page = $pages->find($name);

        if ($page === null) {
            $this->components->error("[{$name}] is not a page.");

            return self::FAILURE;
        }

        $to = (string) ($this->option('to') ?? '');

        if ($to === '') {
            $this->components->error('Say the new name with --to="new name".');

            return self::FAILURE;
        }

        try {
            $pages->rename($page, $to);
        } catch (InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info("[{$name}] is called [{$page->name}] now. The URL did not move.");

        return self::SUCCESS;
    }

    private function publish(SitePages $pages, string $name): int
    {
        $page = $pages->find($name);

        if ($page === null) {
            $this->components->error("[{$name}] is not a page.");

            return self::FAILURE;
        }

        try {
            $pages->publish($page, $this->option('from'), $this->option('until'));
        } catch (InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $window = match (true) {
            $page->published_from && $page->published_until => " from {$page->published_from} until {$page->published_until}",
            (bool) $page->published_from => " from {$page->published_from}",
            (bool) $page->published_until => " until {$page->published_until}",
            default => '',
        };

        $this->components->info("[{$page->name}] is published{$window}.");

        return self::SUCCESS;
    }

    private function delete(SitePages $pages, string $name): int
    {
        $page = $pages->find($name);

        if ($page === null) {
            $this->components->error("[{$name}] is not a page.");

            return self::FAILURE;
        }

        // The components it showed stay — what goes is the page.
        $this->components->warn('The page and its settings go; the components it showed stay. It cannot be undone.');

        if (! $this->components->confirm("Delete [{$page->name}]?")) {
            $this->components->info('Nothing was deleted.');

            return self::SUCCESS;
        }

        $pages->delete($page);

        $this->components->info("Deleted [{$name}].");

        return self::SUCCESS;
    }

    private function show(string $query): int
    {
        $pages = Page::query()
            ->withCount('children')
            ->when($query !== '', fn ($q) => $q->where('name', 'like', '%'.$query.'%'))
            ->orderBy('name')
            ->get();

        if ($pages->isEmpty()) {
            $this->components->info($query === ''
                ? 'No pages yet. --create="name" --url=about makes one.'
                : 'Nothing matches.');

            return self::SUCCESS;
        }

        $this->table(
            ['name', 'url', 'state', 'components', 'updated'],
            $pages->map(fn (Page $page) => [
                $page->name,
                '/'.$page->url,
                $page->is_published ? 'published' : 'draft',
                (string) $page->children_count,
                optional($page->updated_at)->format('Y-m-d H:i') ?? '—',
            ])->all(),
        );

        return self::SUCCESS;
    }
}
