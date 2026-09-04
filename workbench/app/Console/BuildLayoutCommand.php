<?php

namespace Workbench\App\Console;

use Illuminate\Console\Command;
use Bladewright\Blocks\PackagedBlocks;
use Bladewright\Models\Component;
use Bladewright\Support\ComponentContent;

/** For checking locally. Forks the packaged layout and applies it. */
class BuildLayoutCommand extends Command
{
    protected $signature = 'workbench:apply-layout {page}';

    protected $description = 'Apply a layout to a page';

    public function handle(ComponentContent $content, PackagedBlocks $packaged): int
    {
        $layout = Component::query()->ofKind(Component::KIND_LAYOUT)->firstWhere('key', 'basic');

        if ($layout === null) {
            $layout = Component::create([
                'kind' => Component::KIND_LAYOUT, 'key' => 'basic', 'name' => 'Basic layout',
                'origin_hash' => $packaged->fingerprint('basic', 'layout'),
                'data' => ['siteName' => 'Sample Inc.', 'footer' => '© 2026 Sample'],
            ]);
            $content->initialize($layout, file_get_contents($packaged->all('layout')['basic']));
            $content->publish($layout->refresh());
        }

        $page = Component::query()->ofKind(Component::KIND_PAGE)->firstWhere('key', $this->argument('page'));

        // The layout does the wrapping, so the page is contents alone.
        $body = \Bladewright\Blocks\BlockDocument::parse($content->draftContent($page));
        $inner = collect($body->blocks())->map(fn ($b) => $b->toBlade())->implode("\n");

        $content->saveDraft($page, "{{-- bw:slot --}}\n{$inner}\n{{-- /bw:slot --}}\n", 'Moved onto a layout');
        $content->publish($page->refresh());
        $page->forceFill(['layout_key' => 'basic'])->save();

        $this->components->info('done');

        return self::SUCCESS;
    }
}
