<?php

namespace Bladewright\Console;

use Illuminate\Console\Command;
use InvalidArgumentException;
use Bladewright\Blocks\LayoutManager;
use Bladewright\Models\Layout;

/**
 * Layouts — where the parts of a page sit (the owner's table, 2026-09-02).
 *
 * One noun, the verbs as options, everything addressed by name. A layout is
 * born from a recipe — **the site's framework × a type** — and is the site's
 * own from then on; the frame itself is rewritten on the screens.
 */
class LayoutsCommand extends Command
{
    protected $signature = 'bladewright:layouts
        {--search= : Show only names containing this}
        {--create= : Name of the new layout}
        {--type=header : Where the navigation stands: header, or sidebar}
        {--copy= : The layout to copy}
        {--rename= : The layout to rename}
        {--to= : The new name (--copy, --rename)}
        {--delete= : The layout to delete (asks first)}';

    protected $description = 'The layouts: list, create, copy, rename, delete';

    public function handle(LayoutManager $layouts): int
    {
        if (($name = $this->option('create')) !== null) {
            return $this->create($layouts, (string) $name);
        }

        if (($name = $this->option('copy')) !== null) {
            return $this->copy($layouts, (string) $name);
        }

        if (($name = $this->option('rename')) !== null) {
            return $this->rename($layouts, (string) $name);
        }

        if (($name = $this->option('delete')) !== null) {
            return $this->delete($layouts, (string) $name);
        }

        return $this->show((string) ($this->option('search') ?? ''));
    }

    private function create(LayoutManager $layouts, string $name): int
    {
        try {
            $layout = $layouts->create($name, (string) $this->option('type'));
        } catch (InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        // **The recipe's job ends at birth.** From here the frame is the
        // site's own, rewritten on the screens.
        $this->components->info("[{$layout->name}] is in: {$layout->type}. The frame is the site's own now — rewrite it on the screens.");

        return self::SUCCESS;
    }

    private function copy(LayoutManager $layouts, string $name): int
    {
        $layout = $layouts->find($name);

        if ($layout === null) {
            $this->components->error("[{$name}] is not a layout.");

            return self::FAILURE;
        }

        $to = (string) ($this->option('to') ?? '');

        if ($to === '') {
            $this->components->error('Say what the copy is called with --to="new name".');

            return self::FAILURE;
        }

        try {
            $copy = $layouts->copy($layout, $to);
        } catch (InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info("[{$copy->name}] is a copy of [{$layout->name}].");

        return self::SUCCESS;
    }

    private function rename(LayoutManager $layouts, string $name): int
    {
        $layout = $layouts->find($name);

        if ($layout === null) {
            $this->components->error("[{$name}] is not a layout.");

            return self::FAILURE;
        }

        $to = (string) ($this->option('to') ?? '');

        if ($to === '') {
            $this->components->error('Say the new name with --to="new name".');

            return self::FAILURE;
        }

        try {
            $layouts->rename($layout, $to);
        } catch (InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info("[{$name}] is called [{$layout->name}] now. Everything that uses it follows along.");

        return self::SUCCESS;
    }

    private function delete(LayoutManager $layouts, string $name): int
    {
        $layout = $layouts->find($name);

        if ($layout === null) {
            $this->components->error("[{$name}] is not a layout.");

            return self::FAILURE;
        }

        $this->components->warn('The frame goes with it, and it cannot be undone.');

        if (! $this->components->confirm("Delete [{$layout->name}]?")) {
            $this->components->info('Nothing was deleted.');

            return self::SUCCESS;
        }

        $layouts->delete($layout);

        $this->components->info("Deleted [{$name}].");

        return self::SUCCESS;
    }

    private function show(string $query): int
    {
        $layouts = Layout::query()
            ->when($query !== '', fn ($q) => $q->where('name', 'like', '%'.$query.'%'))
            ->orderBy('name')
            ->get();

        if ($layouts->isEmpty()) {
            $this->components->info($query === ''
                ? 'No layouts yet. --create="name" makes one (header, unless told otherwise).'
                : 'Nothing matches.');

            return self::SUCCESS;
        }

        $this->table(
            ['name', 'type', 'updated'],
            $layouts->map(fn (Layout $layout) => [
                $layout->name,
                $layout->type,
                optional($layout->updated_at)->format('Y-m-d H:i') ?? '—',
            ])->all(),
        );

        return self::SUCCESS;
    }
}
