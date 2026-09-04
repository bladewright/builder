<?php

namespace Bladewright\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Bladewright\Blocks\BlockManager;
use Bladewright\Blocks\SitePages;
use Bladewright\Blocks\StructureManager;
use Bladewright\Site\PublicSite;
use Bladewright\Tests\TestCase;

/**
 * What cannot be said inline: **a hover, and a screen width.** The parts
 * leave their rules with the collector under a machine class, and the
 * document's head prints exactly what its parts asked for — drafts included,
 * nothing stored, nothing to invalidate.
 */
class StateCssTest extends TestCase
{
    use RefreshDatabase;

    private function aDocumentOf(callable $dress): string
    {
        $blocks = $this->app->make(BlockManager::class);
        $components = $this->app->make(StructureManager::class);
        $pages = $this->app->make(SitePages::class);

        $block = $blocks->create('cta', 'button');
        $block->forceFill(['data' => ['label' => 'はじめる']])->save();

        $section = $components->create('hero', 'section');
        $components->insertBlock($section, $block);

        $dress($block, $section);

        $page = $pages->create('Demo', 'demo');
        $pages->insertComponent($page, $section->refresh());

        return app(PublicSite::class)->assembledDocument($page);
    }

    /** A block's hover: the machine class on the element, the rule in the head. */
    public function test_a_hover_reaches_the_head_and_the_element(): void
    {
        $document = $this->aDocumentOf(function ($block) {
            $block->forceFill(['data' => array_merge($block->data, [
                'style' => ['hover-background' => '#111111', 'hover-opacity' => '0.85'],
            ])])->save();

            $this->state = PublicSite::stateClass($block->uuid);
        });

        $this->assertStringContainsString('class="'.$this->state.'"', $document);
        $this->assertStringContainsString(
            '.'.$this->state.':hover{background:#111111 !important;opacity:0.85 !important}',
            $document,
        );
    }

    /** A palette name in a hover resolves at render time, like everywhere. */
    public function test_a_hover_speaks_the_palette(): void
    {
        app(\Bladewright\Support\Palette::class)->save(['accent' => '#3538cd']);

        $document = $this->aDocumentOf(function ($block) {
            $block->forceFill(['data' => array_merge($block->data, [
                'style' => ['hover-color' => 'accent'],
            ])])->save();
        });

        $this->assertStringContainsString(':hover{color:#3538cd !important}', $document);
    }

    /** A component's hover lands on its own tag. */
    public function test_a_component_hovers_on_its_tag(): void
    {
        $document = $this->aDocumentOf(function ($block, $section) {
            app(StructureManager::class)->saveStyle($section, ['hover-background' => '#222222']);
        });

        $this->assertStringContainsString(':hover{background:#222222 !important}', $document);
    }

    /** A counted grid stacks below 40rem — said in the head, worn on the wrapper. */
    public function test_a_grid_stacks_on_small_screens(): void
    {
        $document = $this->aDocumentOf(function ($block, $section) {
            app(StructureManager::class)->saveArrangement($section, ['columns' => '2'], 'grid');
        });

        $this->assertStringContainsString('-in" style="display:grid', $document);
        $this->assertStringContainsString('@media (max-width:40rem){', $document);
        $this->assertStringContainsString('{grid-template-columns:1fr !important}', $document);
    }

    /** A row stacks by turning its line into a column. */
    public function test_a_row_stacks_on_small_screens(): void
    {
        $document = $this->aDocumentOf(function ($block, $section) {
            app(StructureManager::class)->saveArrangement($section, [], 'row');
        });

        $this->assertStringContainsString('{flex-direction:column !important;align-items:stretch !important}', $document);
    }

    /** Told to hold, it holds: no class, no rule. */
    public function test_an_arrangement_can_hold_at_every_width(): void
    {
        $document = $this->aDocumentOf(function ($block, $section) {
            app(StructureManager::class)->saveArrangement($section, ['columns' => '2', 'collapse' => false], 'grid');
        });

        $this->assertStringNotContainsString('@media', $document);
        $this->assertStringNotContainsString('-in"', $document);
    }

    /** Nothing asked, nothing printed — a plain page carries no machine style. */
    public function test_a_plain_page_collects_nothing(): void
    {
        $document = $this->aDocumentOf(function () {
        });

        $this->assertStringNotContainsString('bw-', $document);
    }

    /**
     * **The machine's classes are the renderer's to give, never stored** —
     * read back from generated code they are stripped, not kept.
     */
    public function test_the_class_card_never_keeps_a_machine_class(): void
    {
        $manager = $this->app->make(BlockManager::class);

        $this->assertSame('btn btn-primary', $manager->sanitizeClass('btn bw-0a1b2c3d btn-primary'));
        $this->assertSame('', $manager->sanitizeClass('bw-0a1b2c3d-in'));
        // A word that merely starts the same is somebody's own, and stays.
        $this->assertSame('bw-accent', $manager->sanitizeClass('bw-accent'));
    }

    /**
     * The CSS pill speaks only for the style attribute: writing it empties
     * the attribute's fields, **and no state with them** — a hover never
     * stood in the attribute to begin with.
     */
    public function test_the_css_pill_does_not_empty_the_hover(): void
    {
        $this->actingAsRole();

        $block = $this->app->make(BlockManager::class)->create('cta', 'button');
        $block->forceFill(['data' => ['label' => 'go']])->save();

        $editor = Livewire::test('bladewright::block-editor', ['block' => $block])
            ->set('style.hover-background', '#111111')
            ->set('css', 'padding: 2rem;');

        $this->assertSame('#111111', $editor->get('style')['hover-background']);
    }

    /** Patching cards into written code never writes the machine's class in. */
    public function test_written_code_never_gains_a_machine_class(): void
    {
        $this->actingAsRole();

        $block = $this->app->make(BlockManager::class)->create('cta', 'button');
        $block->forceFill(['data' => ['label' => 'go', 'style' => ['hover-background' => '#111111']]])->save();

        $editor = Livewire::test('bladewright::block-editor', ['block' => $block])
            ->set('markup', '<button type="button" class="btn">go</button>')
            ->set('style.color', '#ffffff');

        $this->assertStringNotContainsString('bw-', $editor->get('markup'));
        $this->assertStringContainsString('class="btn"', $editor->get('markup'));
    }

    private string $state = '';
}
