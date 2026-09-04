<?php

namespace Bladewright\Console;

use Illuminate\Console\Command;
use InvalidArgumentException;
use Bladewright\Blocks\BlockManager;
use Bladewright\Models\Block;

/**
 * Blocks — the smallest unit a page is built from (the owner's table,
 * 2026-09-02).
 *
 * One noun, the verbs as options, everything addressed by name. The content
 * is edited on the screens; what the terminal builds is the skeleton:
 * a block exists, has a type, has a name, and can be copied, renamed,
 * deleted — and put into a component, where the name is resolved to the
 * uuid once and held forever.
 */
class BlocksCommand extends Command
{
    protected $signature = 'bladewright:blocks
        {--search= : Show only names containing this}
        {--create= : Name of the new block}
        {--type= : Its type: markdown / image / video / audio / button / input / select / radio / checkbox / textarea / embed / div}
        {--copy= : The block to copy}
        {--rename= : The block to rename}
        {--to= : The new name (--copy, --rename)}
        {--delete= : The block to delete (asks first)}
        {--insert= : The block to put into a component}
        {--order= : Where it lands: 1, 2, 3 … from the top (the end when omitted)}';

    protected $description = 'The blocks: list, create, copy, rename, delete';

    public function handle(BlockManager $blocks): int
    {
        if (($name = $this->option('create')) !== null) {
            return $this->create($blocks, (string) $name);
        }

        if (($name = $this->option('copy')) !== null) {
            return $this->copy($blocks, (string) $name);
        }

        if (($name = $this->option('rename')) !== null) {
            return $this->rename($blocks, (string) $name);
        }

        if (($name = $this->option('delete')) !== null) {
            return $this->delete($blocks, (string) $name);
        }

        if (($name = $this->option('insert')) !== null) {
            return $this->insert($blocks, (string) $name);
        }

        return $this->show((string) ($this->option('search') ?? ''));
    }

    private function create(BlockManager $blocks, string $name): int
    {
        $type = (string) ($this->option('type') ?? '');

        if ($type === '') {
            $this->components->error('Say what kind with --type= (markdown / image / video / audio / button / input / select / radio / checkbox / textarea / embed / div).');

            return self::FAILURE;
        }

        try {
            $block = $blocks->create($name, $type);
        } catch (InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info("[{$block->name}] is a {$block->type} block now. The content is written on the screens.");

        return self::SUCCESS;
    }

    private function copy(BlockManager $blocks, string $name): int
    {
        $block = $blocks->find($name);

        if ($block === null) {
            $this->components->error("[{$name}] is not a block.");

            return self::FAILURE;
        }

        $to = (string) ($this->option('to') ?? '');

        if ($to === '') {
            $this->components->error('Say what the copy is called with --to="new name".');

            return self::FAILURE;
        }

        try {
            $copy = $blocks->copy($block, $to);
        } catch (InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        // **The copy is its own thing from birth.** Editing the original no
        // longer reaches it — that is what a copy is for.
        $this->components->info("[{$copy->name}] is a copy of [{$block->name}].");

        return self::SUCCESS;
    }

    private function rename(BlockManager $blocks, string $name): int
    {
        $block = $blocks->find($name);

        if ($block === null) {
            $this->components->error("[{$name}] is not a block.");

            return self::FAILURE;
        }

        $to = (string) ($this->option('to') ?? '');

        if ($to === '') {
            $this->components->error('Say the new name with --to="new name".');

            return self::FAILURE;
        }

        try {
            $blocks->rename($block, $to);
        } catch (InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        // Whatever uses it holds the uuid, so nothing notices the new word.
        $this->components->info("[{$name}] is called [{$block->name}] now. Everything that uses it follows along.");

        return self::SUCCESS;
    }

    private function delete(BlockManager $blocks, string $name): int
    {
        $block = $blocks->find($name);

        if ($block === null) {
            $this->components->error("[{$name}] is not a block.");

            return self::FAILURE;
        }

        $showing = app(\Bladewright\Blocks\StructureManager::class)->placesShowing($block);

        $this->components->warn($showing === 0
            ? 'Its content goes with it, and it cannot be undone.'
            : "Shown in {$showing} component(s). It disappears from all of them, and it cannot be undone.");

        if (! $this->components->confirm("Delete [{$block->name}]?")) {
            $this->components->info('Nothing was deleted.');

            return self::SUCCESS;
        }

        // No component keeps a pointer to something that is gone.
        app(\Bladewright\Blocks\StructureManager::class)->forgetBlock($block);
        $blocks->delete($block);

        $this->components->info("Deleted [{$name}].");

        return self::SUCCESS;
    }

    /**
     * Put it into a component. **The name is resolved to the uuid here,
     * once** — renaming either side afterwards breaks nothing.
     */
    private function insert(BlockManager $blocks, string $name): int
    {
        $block = $blocks->find($name);

        if ($block === null) {
            $this->components->error("[{$name}] is not a block.");

            return self::FAILURE;
        }

        $components = app(\Bladewright\Blocks\StructureManager::class);
        $to = (string) ($this->option('to') ?? '');
        $target = $to === '' ? null : $components->find($to);

        if ($target === null) {
            $this->components->error($to === ''
                ? 'Say where with --to="component name".'
                : "[{$to}] is not a component.");

            return self::FAILURE;
        }

        $order = $this->option('order');

        try {
            $child = $components->insertBlock($target, $block, $order === null ? null : (int) $order);
        } catch (\InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info("[{$block->name}] stands in [{$target->name}] at {$child->position}.");

        return self::SUCCESS;
    }

    private function show(string $query): int
    {
        $blocks = Block::query()
            ->when($query !== '', fn ($q) => $q->where('name', 'like', '%'.$query.'%'))
            ->orderBy('name')
            ->get();

        if ($blocks->isEmpty()) {
            $this->components->info($query === ''
                ? 'No blocks yet. --create="name" --type=markdown makes one.'
                : 'Nothing matches.');

            return self::SUCCESS;
        }

        $this->table(
            ['name', 'type', 'updated'],
            $blocks->map(fn (Block $block) => [
                $block->name,
                $block->type,
                optional($block->updated_at)->format('Y-m-d H:i') ?? '—',
            ])->all(),
        );

        return self::SUCCESS;
    }
}
