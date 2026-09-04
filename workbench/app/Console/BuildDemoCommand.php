<?php

namespace Workbench\App\Console;

use Illuminate\Console\Command;
use Bladewright\Blocks\BlockDocument;
use Bladewright\Blocks\BlockRenderer;
use Bladewright\Models\Component;
use Bladewright\Support\ComponentContent;

/** For checking locally. Builds a three-column section. Not in the package. */
class BuildDemoCommand extends Command
{
    protected $signature = 'workbench:build-demo {key}';

    protected $description = 'Build a three-column demo';

    public function handle(BlockRenderer $renderer, ComponentContent $content): int
    {
        $page = Component::query()->ofKind(Component::KIND_PAGE)->firstWhere('key', $this->argument('key'));

        $columns = '';

        foreach ([['Free', 0], ['Starter', 3000], ['Pro', 10000]] as [$name, $price]) {
            $card = $renderer->expand('card', ['name' => $name, 'price' => $price]);
            $column = $renderer->expand('column', ['width' => '4'])
                ->withSlotContent("\n".$card->toBlade()."\n");

            $columns .= "\n".$column->toBlade()."\n";
        }

        $section = $renderer->expand('section', ['heading' => 'Pricing', 'tone' => 'muted'])
            ->withSlotContent($columns);

        $head = "<!DOCTYPE html>\n<html lang=\"en\">\n<head><meta charset=\"utf-8\"><title>Pricing</title>\n"
            ."<link rel=\"stylesheet\" href=\"/bladewright/assets/bladewright-site.css\"></head>\n<body>\n<main>\n"
            .\Bladewright\Blocks\BlockInstance::SLOT_OPEN."\n"
            .\Bladewright\Blocks\BlockInstance::SLOT_CLOSE."\n</main>\n</body>\n</html>\n";

        $content->saveDraft($page, BlockDocument::parse($head)->append($section)->toBlade(), 'A three-column demo');
        $content->publish($page->refresh());

        $this->components->info('done');

        return self::SUCCESS;
    }
}
