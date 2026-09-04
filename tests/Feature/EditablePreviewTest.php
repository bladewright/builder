<?php

namespace Bladewright\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Bladewright\Blocks\BlockManager;
use Bladewright\Blocks\SitePages;
use Bladewright\Blocks\StructureManager;
use Bladewright\Tests\TestCase;

/**
 * The preview that can be pressed.
 *
 * One press opens the block's cards beside the page; two presses let the
 * words be typed where they stand. **The stamps that make it possible exist
 * only on the admin's editing preview** — the public page carries none.
 */
class EditablePreviewTest extends TestCase
{
    use RefreshDatabase;

    private function aPageWithWords(string $body = 'ようこそ皆さん'): array
    {
        $block = app(BlockManager::class)->create('intro', 'markdown');
        $block->forceFill(['data' => ['body' => $body]])->save();

        $hero = app(StructureManager::class)->create('hero', 'section');
        app(StructureManager::class)->insertBlock($hero, $block);

        $pages = app(SitePages::class);
        $page = $pages->create('Home', '');
        $pages->insertComponent($page, $hero);
        $pages->publish($page);

        return [$page, $block];
    }

    /**
     * **Save must not move the nonce** — it stands in the panels' keys, and
     * bumping it in the same response would replace the very editor holding
     * the unsaved cards with a fresh one before bw-save-part could reach it:
     * the fresh copy would then "save" the database over the typing. The
     * panels answer with bw-block-saved, and *that* is what bumps it.
     */
    public function test_save_keeps_the_panel_alive_until_it_answers(): void
    {
        [$page, $block] = $this->aPageWithWords();
        $this->actingAsRole();

        $editor = \Livewire\Livewire::test('bladewright::page-editor', ['page' => $page])
            ->call('select', $block->uuid)
            ->dispatch('bw-part-drafted', uuid: $block->uuid, kind: 'block', data: [
                'body' => 'ようこそ皆さん',
                'style' => ['color' => '#123456'],
            ]);

        $nonce = $editor->get('nonce');

        $editor->call('save')
            ->assertDispatched('bw-save-part')
            ->assertSet('nonce', $nonce);

        // The panel answers, and only now does the page turn the keys.
        $editor->dispatch('bw-block-saved')
            ->assertSet('nonce', $nonce + 1)
            ->assertSet('draftParts', []);
    }

    /** The stamps are the editing preview's own — nowhere else. */
    public function test_the_stamps_exist_only_on_the_editing_preview(): void
    {
        [$page, $block] = $this->aPageWithWords();
        $this->actingAsRole();

        $hero = \Bladewright\Models\Structure::query()->where('name', 'hero')->firstOrFail();

        $this->get(route('bladewright.admin.pages.preview', $page).'?editing=1')
            ->assertOk()
            ->assertSee('data-bw-block="'.$block->uuid.'"', false)
            // The page's own rows say where they stand; the + buttons read it.
            ->assertSee('data-bw-slot="1" data-bw-component="'.$hero->uuid.'"', false);

        $this->get(route('bladewright.admin.pages.preview', $page))
            ->assertOk()
            ->assertDontSee('data-bw-block', false)
            ->assertDontSee('data-bw-component', false);

        // The public page never wears them, whoever is signed in.
        $this->get('/')->assertOk()->assertDontSee('data-bw-block', false);
    }

    /** One press opens the block's cards where the tree stood. */
    public function test_pressing_a_block_opens_its_cards_in_place(): void
    {
        [$page, $block] = $this->aPageWithWords();
        $this->actingAsRole();

        $editor = Livewire::test('bladewright::page-editor', ['page' => $page])
            ->call('select', $block->uuid)
            ->assertSet('editing', $block->uuid)
            ->assertSee('Back to the page', false)
            ->assertSee('its own screen', false);

        $editor->call('closeEditor')->assertSet('editing', null);

        // Pressing the space around the words opens the component instead.
        $hero = \Bladewright\Models\Structure::query()->where('name', 'hero')->firstOrFail();

        $editor->call('select', $hero->uuid)
            ->assertSet('editing', $hero->uuid)
            ->assertSee('Arrangement', false);

        // A uuid that is nobody's opens nothing.
        $editor->call('select', 'not-a-uuid')->assertSet('editing', null);
    }

    /** Words typed on the preview land in the markdown, when unmistakable. */
    public function test_typing_on_the_preview_writes_the_markdown(): void
    {
        [$page, $block] = $this->aPageWithWords("# ようこそ皆さん\n\nはじめまして。");
        $this->actingAsRole();

        Livewire::test('bladewright::page-editor', ['page' => $page])
            ->call('inlineText', $block->uuid, 'ようこそ皆さん', 'こんにちは')
            ->assertToast('Saved');

        $this->assertSame("# こんにちは\n\nはじめまして。", $block->refresh()->data['body']);
    }

    /**
     * **Nothing ambiguous is guessed at.** Repeated words, formatting the
     * rendered text no longer carries, or an authored block — the words go
     * back, and the panel opens where it can be said precisely.
     */
    public function test_ambiguous_words_open_the_panel_instead(): void
    {
        [$page, $block] = $this->aPageWithWords("おなじ言葉\n\nおなじ言葉");
        $this->actingAsRole();

        Livewire::test('bladewright::page-editor', ['page' => $page])
            ->call('inlineText', $block->uuid, 'おなじ言葉', '違う言葉')
            ->assertSet('editing', $block->uuid)
            ->assertToast('say it on the card');

        $this->assertSame("おなじ言葉\n\nおなじ言葉", $block->refresh()->data['body']);

        // Words wholly wrapped in formatting still match the source, so the
        // markers survive the retyping.
        $bold = app(BlockManager::class)->create('bold', 'markdown');
        $bold->forceFill(['data' => ['body' => '**太い言葉**']])->save();

        Livewire::test('bladewright::page-editor', ['page' => $page])
            ->call('inlineText', $bold->uuid, '太い言葉', '細い言葉')
            ->assertToast('Saved');

        $this->assertSame('**細い言葉**', $bold->refresh()->data['body']);

        // But formatting **inside** the words breaks the match — the rendered
        // text is no longer written anywhere — and the panel opens instead.
        $broken = app(BlockManager::class)->create('broken', 'markdown');
        $broken->forceFill(['data' => ['body' => '途中から**太い**言葉']])->save();

        Livewire::test('bladewright::page-editor', ['page' => $page])
            ->call('inlineText', $broken->uuid, '途中から太い言葉', '別の言葉')
            ->assertSet('editing', $broken->uuid);

        $this->assertSame('途中から**太い**言葉', $broken->refresh()->data['body']);
    }

    /**
     * Embedded, the block editor is **the cards only** — the page beside it
     * is the preview — and saving tells the holder to refresh.
     */
    public function test_the_embedded_editor_is_cards_only_and_says_when_it_saved(): void
    {
        $this->actingAsRole();
        $block = app(BlockManager::class)->create('cta', 'button');

        $editor = Livewire::test('bladewright::block-editor', ['block' => $block, 'embedded' => true]);

        $editor->assertDontSeeHtml('bw-preview-stage');

        $editor->set('data.label', '押す')
            ->call('save')
            ->assertDispatched('bw-block-saved');

        $this->assertSame('押す', $block->refresh()->data['label']);

        // **One Save on the desk**: the page's button reaches the panel.
        $editor->set('data.label', '進む')->dispatch('bw-save-part');

        $this->assertSame('進む', $block->refresh()->data['label']);

        // On its own screen, the event is not for it.
        $alone = Livewire::test('bladewright::block-editor', ['block' => $block])
            ->set('data.label', '別物')
            ->dispatch('bw-save-part');

        $this->assertSame('進む', $block->refresh()->data['label']);

        // The page editor hears it and turns the preview over.
        [$page] = $this->aPageWithWords();

        Livewire::test('bladewright::page-editor', ['page' => $page])
            ->dispatch('bw-block-saved')
            ->assertSet('nonce', 1);
    }

    /**
     * **The + opens a shelf of miniatures** — every placeable component
     * rendered small through the real renderer, the layout's own kinds not
     * offered — and pressing one puts it right there, above or below.
     */
    public function test_the_shelf_offers_miniatures_and_places_them(): void
    {
        [$page, $block] = $this->aPageWithWords();
        app(StructureManager::class)->create('closing', 'section');
        app(StructureManager::class)->create('site-header', 'header');
        $this->actingAsRole();

        $editor = Livewire::test('bladewright::page-editor', ['page' => $page]);

        $shelf = $editor->instance()->componentShelf();

        // Rendered small, wearing the real renderer's output — and the
        // layout's kinds stay off this shelf like every other.
        $this->assertStringContainsString('data-bw-mini="closing"', $shelf);
        $this->assertStringContainsString('data-bw-mini="hero"', $shelf);
        $this->assertStringContainsString('ようこそ皆さん', $shelf);
        $this->assertStringNotContainsString('site-header', $shelf);

        $names = fn () => $page->refresh()->children()->orderBy('position')->get()
            ->map(fn ($child) => \Bladewright\Models\Structure::query()->where('uuid', $child->child_uuid)->value('name'))
            ->all();

        // A miniature pressed above the band lands in the draft — the live
        // page does not move until Save, **but the Code pill already says it.**
        $closingUuid = \Bladewright\Models\Structure::query()->where('name', 'closing')->value('uuid');
        $editor->call('placeComponent', 'closing', 1, false)->assertToast('when the page is saved');
        $this->assertSame(['hero'], $names());
        $this->assertSame(2, substr_count($editor->get('markup'), '<section'));

        // Below lands below; junk names and slots land nowhere.
        $editor->call('placeComponent', 'closing', 2, true);
        $editor->call('placeComponent', 'nobody', 1, false);
        $editor->call('placeComponent', 'closing', 9, false);

        $editor->call('save');

        $this->assertSame(['closing', 'hero', 'closing'], $names());
    }

    /**
     * **A dragged band lands where it was dropped.** Slots are 1-based, the
     * destination said as above or below another band — both directions,
     * and a drop on itself moves nothing.
     */
    public function test_a_dragged_band_lands_where_dropped(): void
    {
        $this->actingAsRole();

        $pages = app(SitePages::class);
        $page = $pages->create('Home', '');

        foreach (['one', 'two', 'three'] as $name) {
            $pages->insertComponent($page, app(StructureManager::class)->create($name, 'section'));
        }

        $order = fn () => $page->refresh()->children()->orderBy('position')->get()
            ->map(fn ($child) => \Bladewright\Models\Structure::query()->where('uuid', $child->child_uuid)->value('name'))
            ->all();

        $editor = Livewire::test('bladewright::page-editor', ['page' => $page]);

        // one below three, in the draft: the live page waits for Save.
        $editor->call('moveSlot', 1, 3, true);
        $this->assertSame(['one', 'two', 'three'], $order());

        $editor->call('save');
        $this->assertSame(['two', 'three', 'one'], $order());

        // one above two: back to the front.
        $editor->call('moveSlot', 3, 1, false)->call('save');
        $this->assertSame(['one', 'two', 'three'], $order());

        // above itself, or out of range: nothing moves
        $editor->call('moveSlot', 2, 2, false);
        $editor->call('moveSlot', 2, 9, true);
        $editor->call('save');
        $this->assertSame(['one', 'two', 'three'], $order());
    }

    /**
     * **A block's + works the same one layer down**: the stamp says where
     * the block stands in its component, the shelf offers every block
     * rendered small, and the pressed one lands right there.
     */
    public function test_the_block_shelf_places_into_the_component(): void
    {
        [$page, $block] = $this->aPageWithWords();
        $cta = app(BlockManager::class)->create('cta', 'button');
        $cta->forceFill(['data' => ['label' => '押す', 'type' => 'button']])->save();
        $this->actingAsRole();

        $hero = \Bladewright\Models\Structure::query()->where('name', 'hero')->firstOrFail();

        // The stamp carries the block's place in its component.
        $this->get(route('bladewright.admin.pages.preview', $page).'?editing=1')
            ->assertOk()
            ->assertSee('data-bw-at="1" data-bw-block="'.$block->uuid.'"', false);

        $editor = Livewire::test('bladewright::page-editor', ['page' => $page]);

        $shelf = $editor->instance()->blockShelf();

        $this->assertStringContainsString('data-bw-mini="cta"', $shelf);
        $this->assertStringContainsString('押す', $shelf);

        // Above the first block, into the hero's draft — landed on Save.
        $editor->call('placeBlock', $hero->uuid, 'cta', 1, false)->assertToast('when the page is saved');

        $this->assertCount(1, $hero->refresh()->children);

        $editor->call('save');

        $this->assertSame(
            [$cta->uuid, $block->uuid],
            $hero->refresh()->children()->orderBy('position')->pluck('child_uuid')->all(),
        );

        // Below lands below; junk lands nowhere.
        $editor->call('placeBlock', $hero->uuid, 'cta', 2, true);
        $editor->call('placeBlock', 'nobody', 'cta', 1, false);
        $editor->call('placeBlock', $hero->uuid, 'nobody', 1, false);
        $editor->call('placeBlock', $hero->uuid, 'cta', 9, false);
        $editor->call('save');
        $this->assertCount(3, $hero->refresh()->children);
    }

    /**
     * **A dragged block lands within its own component** — before or after
     * another of its rows, both directions, nothing on a drop on itself.
     */
    public function test_a_dragged_block_lands_within_its_component(): void
    {
        $this->actingAsRole();

        $pages = app(SitePages::class);
        $page = $pages->create('Home', '');
        $hero = app(StructureManager::class)->create('hero', 'section');
        $pages->insertComponent($page, $hero);

        $blocks = [];

        foreach (['one', 'two', 'three'] as $name) {
            $blocks[$name] = app(BlockManager::class)->create($name, 'markdown');
            app(StructureManager::class)->insertBlock($hero, $blocks[$name]);
        }

        $order = fn () => $hero->refresh()->children()->orderBy('position')->pluck('child_uuid')
            ->map(fn ($uuid) => \Bladewright\Models\Block::query()->where('uuid', $uuid)->value('name'))
            ->all();

        $editor = Livewire::test('bladewright::page-editor', ['page' => $page]);

        // one after three, in the draft; the component waits for Save.
        $editor->call('moveBlock', $hero->uuid, 1, 3, true);
        $this->assertSame(['one', 'two', 'three'], $order());

        $editor->call('save');
        $this->assertSame(['two', 'three', 'one'], $order());

        // one before two: back to the front
        $editor->call('moveBlock', $hero->uuid, 3, 1, false)->call('save');
        $this->assertSame(['one', 'two', 'three'], $order());

        // itself, out of range, or nobody's component: nothing moves
        $editor->call('moveBlock', $hero->uuid, 2, 2, false);
        $editor->call('moveBlock', $hero->uuid, 2, 9, true);
        $editor->call('moveBlock', 'nobody', 1, 2, true);
        $editor->call('save');
        $this->assertSame(['one', 'two', 'three'], $order());
    }

    /**
     * **The × takes things off, never away.** A band leaves the page, a
     * block leaves its component — and both stay on the shelf, whole.
     */
    public function test_the_x_takes_off_but_never_away(): void
    {
        [$page, $block] = $this->aPageWithWords();
        $this->actingAsRole();

        $hero = \Bladewright\Models\Structure::query()->where('name', 'hero')->firstOrFail();

        $editor = Livewire::test('bladewright::page-editor', ['page' => $page]);

        // The block leaves the hero's draft; nothing moves until Save.
        $editor->call('removeBlockAt', $hero->uuid, 1)->assertToast('when the page is saved');
        $this->assertCount(1, $hero->refresh()->children);

        // The band leaves the page's draft too.
        $editor->call('removeSlot', 1)->assertToast('when the page is saved');
        $this->assertCount(1, $page->refresh()->children);

        // A place nobody stands in takes nothing.
        $editor->call('removeSlot', 9);
        $editor->call('removeBlockAt', 'nobody', 1);

        $editor->call('save');

        // Off the page and out of the hero — and both still stand, whole.
        $this->assertCount(0, $hero->refresh()->children);
        $this->assertCount(0, $page->refresh()->children);
        $this->assertNotNull($block->refresh());
        $this->assertNotNull($hero->refresh());
    }

    /**
     * Embedded, the component editor is the cards only too — and everything
     * it commits (save, place, remove, drag) tells the holder to refresh.
     */
    public function test_the_embedded_component_editor_is_cards_only(): void
    {
        $this->actingAsRole();
        $hero = app(StructureManager::class)->create('panel-hero', 'section');

        Livewire::test('bladewright::component-editor', ['component' => $hero, 'embedded' => true])
            ->assertDontSeeHtml('bw-preview-stage')
            ->call('save')
            ->assertDispatched('bw-component-saved');

        // The page editor hears that event the same as a block's.
        [$page] = $this->aPageWithWords();

        Livewire::test('bladewright::page-editor', ['page' => $page])
            ->dispatch('bw-component-saved')
            ->assertSet('nonce', 1);
    }

    /**
     * **Every face shows the typing as it happens.** The embedded panel
     * whispers its unsaved rendering up; the page wears it in the preview
     * and the Code pill — and drops it when the panel closes unsaved, when
     * another opens, or when the part is truly saved.
     */
    public function test_the_panel_whispers_and_every_face_hears(): void
    {
        [$page, $block] = $this->aPageWithWords();
        $this->actingAsRole();

        // The panel speaks on every edit while embedded.
        Livewire::test('bladewright::block-editor', ['block' => $block, 'embedded' => true])
            ->set('data.body', '打ちかけの言葉')
            ->assertDispatched('bw-part-drafted');

        // The page hears the DATA and renders it itself — stamps, drafts
        // and sanitizing intact, nothing client-forged.
        $editor = Livewire::test('bladewright::page-editor', ['page' => $page])
            ->call('select', $block->uuid)
            ->dispatch('bw-part-drafted', uuid: $block->uuid, kind: 'block', data: ['body' => '打ちかけの言葉']);

        $this->assertStringContainsString('打ちかけの言葉', $editor->instance()->preview());
        $this->assertStringContainsString('打ちかけの言葉', $editor->get('markup'));
        $this->assertTrue($editor->instance()->dirty());
        $this->assertSame('ようこそ皆さん', $block->refresh()->data['body']);

        // Closing the panel takes the unsaid words back.
        $editor->call('closeEditor');

        $this->assertStringNotContainsString('打ちかけの言葉', $editor->instance()->preview());
        $this->assertStringNotContainsString('打ちかけの言葉', $editor->get('markup'));
    }

    /**
     * **The words are edited inside the component's panel.** A block's row
     * opens the slim face — the Contents card alone — a component's row
     * turns the panel over, and the slim face still whispers upward.
     */
    public function test_the_panel_opens_block_contents_in_place(): void
    {
        [$page, $block] = $this->aPageWithWords();
        $this->actingAsRole();

        $hero = \Bladewright\Models\Structure::query()->where('name', 'hero')->firstOrFail();

        // The component's panel carries no Structure card: the page's own
        // Structure face holds the words. The panel is the component itself.
        Livewire::test('bladewright::component-editor', ['component' => $hero, 'embedded' => true])
            ->assertDontSee('Structure', false)
            ->assertSee('Arrangement', false);

        // A title's press opens the words under it AND the panel beside it;
        // the same press closes both.
        $tree = Livewire::test('bladewright::page-editor', ['page' => $page])
            ->call('openRow', 'tree-'.$block->uuid.'-0.0', $block->uuid)
            ->assertSet('treeOpen', 'tree-'.$block->uuid.'-0.0')
            ->assertSet('editing', $block->uuid)
            ->assertSeeLivewire('bladewright::block-editor');

        $tree->call('openRow', 'tree-'.$block->uuid.'-0.0', $block->uuid)
            ->assertSet('treeOpen', null)
            ->assertSet('editing', null);

        // Slim is the writing alone — no card chrome, no Class, no Style.
        Livewire::test('bladewright::block-editor', ['block' => $block, 'embedded' => true, 'slim' => true])
            ->assertSeeHtml('data-bw-markdown')
            ->assertDontSee('Straight onto the element', false)
            ->assertDontSeeHtml('bw-pill-style');

        // A component still opens its cards by uuid, from any surface.
        Livewire::test('bladewright::page-editor', ['page' => $page])
            ->call('select', $hero->uuid)
            ->assertSet('editing', $hero->uuid);
    }

    /**
     * **The gatekeeper rides along on Livewire updates.** Livewire replays
     * `can:` but not our own middleware — and ours is what points the Gate
     * at the admin's guard. Without this registration, an admin on a guard
     * of its own gets 403 on every single Livewire update.
     */
    public function test_the_gatekeeper_is_persistent_middleware(): void
    {
        $persistent = \Livewire\Livewire::getPersistentMiddleware();

        $this->assertContains(\Bladewright\Http\Middleware\AdminAuthenticate::class, $persistent);
    }
}
