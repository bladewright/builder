<?php

namespace Bladewright\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Bladewright\Blocks\BlockManager;
use Bladewright\Blocks\StructureManager;
use Bladewright\Site\PublicSite;
use Bladewright\Tests\TestCase;

/**
 * How the contents stand: **stacked, in a grid, or in a row** — and the
 * words each layout then speaks. The renderer is a second gate: nothing an
 * unsaved ghost carries reaches a style attribute unread.
 */
class ArrangementTest extends TestCase
{
    use RefreshDatabase;

    private function aSection(string $layout = 'stack'): \Bladewright\Models\Structure
    {
        $manager = $this->app->make(StructureManager::class);
        $section = $manager->create('hero', 'section', $layout);

        $block = $this->app->make(BlockManager::class)->create('intro', 'markdown');
        $block->forceFill(['data' => ['body' => 'words']])->save();
        $manager->insertBlock($section, $block);

        return $section;
    }

    /** `auto` breathes with the screen — the grid it always was. */
    public function test_a_grid_with_auto_columns_fits_as_many_as_fit(): void
    {
        $section = $this->aSection('grid');

        $this->assertStringContainsString(
            'display:grid;grid-template-columns:repeat(auto-fit,minmax(16rem,1fr))',
            app(PublicSite::class)->assembled($section),
        );
    }

    /** A count divides the width evenly. */
    public function test_a_counted_grid_divides_evenly(): void
    {
        $section = $this->aSection('grid');
        app(StructureManager::class)->saveArrangement($section, ['columns' => '3', 'gap' => '2rem'], 'grid');

        $this->assertStringContainsString(
            'display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:2rem',
            app(PublicSite::class)->assembled($section->refresh()),
        );
    }

    /** A template says the ratio outright — the asymmetric hero at last. */
    public function test_a_template_grid_keeps_its_ratio(): void
    {
        $section = $this->aSection('grid');
        app(StructureManager::class)->saveArrangement($section, ['columns' => '1fr 2fr'], 'grid');

        $this->assertStringContainsString(
            'grid-template-columns:1fr 2fr',
            app(PublicSite::class)->assembled($section->refresh()),
        );
    }

    /** A row is flex, wrapping by default, with its alignment words. */
    public function test_a_row_lines_its_contents_up(): void
    {
        $section = $this->aSection('row');
        app(StructureManager::class)->saveArrangement($section, [
            'gap' => '1rem',
            'justify' => 'space-between',
            'align' => 'center',
        ], 'row');

        $this->assertStringContainsString(
            'display:flex;flex-wrap:wrap;gap:1rem;justify-content:space-between;align-items:center',
            app(PublicSite::class)->assembled($section->refresh()),
        );
    }

    /** Wrap turned off is said as nowrap. */
    public function test_a_row_can_refuse_to_wrap(): void
    {
        $section = $this->aSection('row');
        app(StructureManager::class)->saveArrangement($section, ['wrap' => false], 'row');

        $this->assertStringContainsString('flex-wrap:nowrap', app(PublicSite::class)->assembled($section->refresh()));
    }

    /** Stacked is the block-level default: no wrapper at all. */
    public function test_a_stack_needs_no_wrapper(): void
    {
        $assembled = app(PublicSite::class)->assembled($this->aSection());

        $this->assertStringNotContainsString('display:grid', $assembled);
        $this->assertStringNotContainsString('display:flex', $assembled);
    }

    /** The short lists are short: made-up words are refused, not written. */
    public function test_made_up_arrangement_words_are_refused(): void
    {
        $section = $this->aSection('row');

        $this->expectException(InvalidArgumentException::class);
        app(StructureManager::class)->saveArrangement($section, ['justify' => 'sideways'], 'row');
    }

    /** A layout off the list is refused too. */
    public function test_a_made_up_layout_is_refused(): void
    {
        $section = $this->aSection();

        $this->expectException(InvalidArgumentException::class);
        app(StructureManager::class)->saveArrangement($section, [], 'circle');
    }

    /** Columns are held to the letters a size is written with. */
    public function test_columns_that_do_not_read_as_css_are_refused(): void
    {
        $section = $this->aSection('grid');

        $this->expectException(InvalidArgumentException::class);
        app(StructureManager::class)->saveArrangement($section, ['columns' => '1fr;color:red'], 'grid');
    }

    /**
     * **The renderer is its own gate.** A ghost's data arrives unsaved, so
     * even words that never passed the manager are read again before they
     * reach a style attribute.
     */
    public function test_the_renderer_reads_ghost_columns_again(): void
    {
        $section = $this->aSection('grid');
        $section->data = array_merge($section->data, ['columns' => 'url(evil)', 'justify' => 'up']);

        $this->assertStringContainsString(
            'grid-template-columns:repeat(auto-fit,minmax(16rem,1fr))',
            app(PublicSite::class)->assembled($section),
        );
    }

    /**
     * The Arrangement card reaches the Code pill before anything is saved —
     * and the database only on Save, like every card.
     */
    public function test_the_card_reaches_the_code_before_the_save(): void
    {
        $this->actingAsRole();
        $section = $this->aSection();

        $editor = Livewire::test('bladewright::component-editor', ['component' => $section])
            ->set('layout', 'row')
            ->set('justify', 'center');

        $this->assertStringContainsString('display:flex', $editor->get('markup'));
        $this->assertStringContainsString('justify-content:center', $editor->get('markup'));
        $this->assertSame('stack', $section->refresh()->layout);

        $editor->call('saveArrangement');

        $this->assertSame('row', $section->refresh()->layout);
        $this->assertSame('center', $section->data['justify']);
    }

}
