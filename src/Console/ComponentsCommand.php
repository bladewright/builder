<?php

namespace Bladewright\Console;

use Illuminate\Console\Command;
use InvalidArgumentException;
use Bladewright\Blocks\StructureManager;
use Bladewright\Models\Structure;

/**
 * Components — a structure that means something, a collection of blocks
 * (the owner's table, 2026-09-02).
 *
 * One noun, the verbs as options, everything addressed by name. The
 * arrangement (direction, alignment, width, padding, gap) is edited on the
 * screens; the terminal builds the skeleton — and puts components onto
 * pages, where the name is resolved to the uuid once and held forever.
 */
class ComponentsCommand extends Command
{
    protected $signature = 'bladewright:components
        {--search= : Show only names containing this}
        {--create= : Name of the new component}
        {--type= : Its type: section / article / nav / table / figure / form / field}
        {--layout= : How the contents stand: stack / grid / row (stack when omitted)}
        {--copy= : The component to copy}
        {--rename= : The component to rename}
        {--to= : The new name (--copy, --rename)}
        {--delete= : The component to delete (asks first)}
        {--insert= : The component to put onto a page}
        {--order= : Where it lands: 1, 2, 3 … from the top (the end when omitted)}';

    protected $description = 'The components: list, create, copy, rename, delete';

    public function handle(StructureManager $components): int
    {
        if (($name = $this->option('create')) !== null) {
            return $this->create($components, (string) $name);
        }

        if (($name = $this->option('copy')) !== null) {
            return $this->copy($components, (string) $name);
        }

        if (($name = $this->option('rename')) !== null) {
            return $this->rename($components, (string) $name);
        }

        if (($name = $this->option('delete')) !== null) {
            return $this->delete($components, (string) $name);
        }

        if (($name = $this->option('insert')) !== null) {
            return $this->insert($components, (string) $name);
        }

        return $this->show((string) ($this->option('search') ?? ''));
    }

    private function create(StructureManager $components, string $name): int
    {
        $type = (string) ($this->option('type') ?? '');

        if ($type === '') {
            $this->components->error('Say what kind with --type= (section / article / nav / table / figure / form / field).');

            return self::FAILURE;
        }

        try {
            $made = $components->create($name, $type, (string) ($this->option('layout') ?? '') ?: 'stack');
        } catch (InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info(
            "[{$made->name}] is a {$made->type} component now"
            .($made->layout !== 'stack' ? ", laid out in a {$made->layout}" : '')
            .'. bladewright:blocks --insert= puts blocks into it.',
        );

        return self::SUCCESS;
    }

    private function copy(StructureManager $components, string $name): int
    {
        $structure = $components->find($name);

        if ($structure === null) {
            $this->components->error("[{$name}] is not a component.");

            return self::FAILURE;
        }

        $to = (string) ($this->option('to') ?? '');

        if ($to === '') {
            $this->components->error('Say what the copy is called with --to="new name".');

            return self::FAILURE;
        }

        try {
            $copy = $components->copy($structure, $to);
        } catch (InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        // **The arrangement is copied; the blocks are shared.** Editing a
        // block still reaches both — copying the block is what diverges it.
        $this->components->info("[{$copy->name}] arranges the same blocks as [{$structure->name}].");

        return self::SUCCESS;
    }

    private function rename(StructureManager $components, string $name): int
    {
        $structure = $components->find($name);

        if ($structure === null) {
            $this->components->error("[{$name}] is not a component.");

            return self::FAILURE;
        }

        $to = (string) ($this->option('to') ?? '');

        if ($to === '') {
            $this->components->error('Say the new name with --to="new name".');

            return self::FAILURE;
        }

        try {
            $components->rename($structure, $to);
        } catch (InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info("[{$name}] is called [{$structure->name}] now. Everything that uses it follows along.");

        return self::SUCCESS;
    }

    private function delete(StructureManager $components, string $name): int
    {
        $structure = $components->find($name);

        if ($structure === null) {
            $this->components->error("[{$name}] is not a component.");

            return self::FAILURE;
        }

        $showing = app(\Bladewright\Blocks\SitePages::class)->pagesShowing($structure);

        // **The blocks stay.** What goes is the arrangement.
        $this->components->warn($showing === 0
            ? 'The blocks in it stay; this arrangement goes, and it cannot be undone.'
            : "Shown on {$showing} page(s). It disappears from all of them; the blocks in it stay. It cannot be undone.");

        if (! $this->components->confirm("Delete [{$structure->name}]?")) {
            $this->components->info('Nothing was deleted.');

            return self::SUCCESS;
        }

        // No page keeps a pointer to something that is gone.
        app(\Bladewright\Blocks\SitePages::class)->forgetComponent($structure);
        app(\Bladewright\Blocks\LayoutManager::class)->forget($structure->uuid);
        $components->delete($structure);

        $this->components->info("Deleted [{$name}].");

        return self::SUCCESS;
    }

    /**
     * Put it onto a page. **The name is resolved to the uuid here, once** —
     * renaming either side afterwards breaks nothing.
     */
    private function insert(StructureManager $components, string $name): int
    {
        $structure = $components->find($name);

        if ($structure === null) {
            $this->components->error("[{$name}] is not a component.");

            return self::FAILURE;
        }

        $pages = app(\Bladewright\Blocks\SitePages::class);
        $to = (string) ($this->option('to') ?? '');
        $target = $to === '' ? null : $pages->find($to);

        if ($target === null) {
            $this->components->error($to === ''
                ? 'Say where with --to="page name".'
                : "[{$to}] is not a page.");

            return self::FAILURE;
        }

        $order = $this->option('order');

        try {
            $child = $pages->insertComponent($target, $structure, $order === null ? null : (int) $order);
        } catch (\InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info("[{$structure->name}] stands on [{$target->name}] at {$child->position}.");

        return self::SUCCESS;
    }

    private function show(string $query): int
    {
        $structures = Structure::query()
            ->withCount('children')
            ->when($query !== '', fn ($q) => $q->where('name', 'like', '%'.$query.'%'))
            ->orderBy('name')
            ->get();

        if ($structures->isEmpty()) {
            $this->components->info($query === ''
                ? 'No components yet. --create="name" --type=section makes one.'
                : 'Nothing matches.');

            return self::SUCCESS;
        }

        $this->table(
            ['name', 'type', 'layout', 'blocks', 'updated'],
            $structures->map(fn (Structure $structure) => [
                $structure->name,
                $structure->type,
                $structure->layout === 'stack' ? '' : $structure->layout,
                (string) $structure->children_count,
                optional($structure->updated_at)->format('Y-m-d H:i') ?? '—',
            ])->all(),
        );

        return self::SUCCESS;
    }
}
