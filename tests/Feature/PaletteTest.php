<?php

namespace Bladewright\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Bladewright\Blocks\BlockManager;
use Bladewright\Site\PublicSite;
use Bladewright\Support\Palette;
use Bladewright\Tests\TestCase;

/**
 * The site's colours, kept once and used by name.
 *
 * **A block carries the name, not the value** — which is the same rule as
 * everything else in this world: change it once, every page follows.
 */
class PaletteTest extends TestCase
{
    use RefreshDatabase;

    /** A site starts with colours, so the screens have something to offer. */
    public function test_a_site_starts_with_a_palette(): void
    {
        $this->assertSame(Palette::DEFAULTS, app(Palette::class)->all());
    }

    /** **Change the colour, and every page wearing the name changes.** */
    public function test_the_name_is_resolved_when_the_page_is_rendered(): void
    {
        $this->actingAsRole();
        $block = app(BlockManager::class)->create('cta', 'button');

        app(BlockManager::class)->saveContent($block, [
            'label' => '買う',
            'type' => 'button',
            'style' => ['background' => 'accent'],
        ]);

        // The block stored the name.
        $this->assertSame('accent', $block->refresh()->data['style']['background']);
        $this->assertStringContainsString('background:#3538cd', app(PublicSite::class)->block($block));

        app(Palette::class)->save(['accent' => '#c81e5b']);

        $this->assertStringContainsString('background:#c81e5b', app(PublicSite::class)->block($block->refresh()));
    }

    /** A colour written in by hand belongs to that block alone. */
    public function test_a_colour_written_in_is_kept_as_it_is(): void
    {
        $this->actingAsRole();
        $block = app(BlockManager::class)->create('cta', 'button');

        app(BlockManager::class)->saveContent($block, [
            'label' => '買う',
            'style' => ['background' => '#123456'],
        ]);

        $this->assertStringContainsString('background:#123456', app(PublicSite::class)->block($block->refresh()));
    }

    /** **A gradient is a background and nothing else.** */
    public function test_a_gradient_is_a_background_only(): void
    {
        app(Palette::class)->save(['sunrise' => 'linear-gradient(90deg, #f97316, #db2777)']);

        $this->actingAsRole();
        $block = app(BlockManager::class)->create('cta', 'button');

        app(BlockManager::class)->saveContent($block, [
            'label' => '買う',
            'style' => ['background' => 'sunrise', 'color' => 'sunrise'],
        ]);

        $stored = $block->refresh()->data['style'];

        $this->assertSame('sunrise', $stored['background']);
        $this->assertArrayNotHasKey('color', $stored);
        $this->assertStringContainsString('background:linear-gradient(90deg, #f97316, #db2777)', app(PublicSite::class)->block($block));
    }

    /** **One entry is one value**, never a way to write a second property. */
    public function test_a_value_that_is_not_a_colour_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(Palette::class)->save(['bad' => 'red; position:fixed']);
    }

    /** A name reads like a word, or it is refused where it was typed. */
    public function test_the_settings_screen_keeps_the_palette(): void
    {
        $this->actingAsRole();

        // The last row is the empty one a new colour is written into.
        $panel = Livewire::test('bladewright::palette-panel');
        $last = count($panel->get('rows')) - 1;

        $panel->set("rows.{$last}.name", 'brand')
            ->set("rows.{$last}.value", 'linear-gradient(90deg, #f97316, #db2777)')
            ->call('save')
            ->assertToast('Every page using these names changes');

        $this->assertSame('linear-gradient(90deg, #f97316, #db2777)', app(Palette::class)->value('brand'));

        // **Two colours cannot share a name.**
        $panel = Livewire::test('bladewright::palette-panel');

        $panel->set('rows.1.name', 'accent')
            ->call('save')
            ->assertToast('is in the list twice');

        Livewire::test('bladewright::palette-panel')
            ->set('rows.0.name', 'not a name')
            ->call('save')
            ->assertToast('is not a name a colour can be called');
    }
}
