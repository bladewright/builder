<?php

namespace Bladewright\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Bladewright\Blocks\BlockManager;
use Bladewright\Blocks\SitePages;
use Bladewright\Blocks\StructureManager;
use Bladewright\Models\Structure;
use Bladewright\Tests\TestCase;

/**
 * The components screens: the list, the editor with the arrangement, and
 * the settings behind the gear. **Spacing lives here and nowhere else.**
 */
class ComponentsScreenTest extends TestCase
{
    use RefreshDatabase;

    /** The list shows components, and the row is the way in. */
    public function test_the_list_shows_components(): void
    {
        $this->actingAsRole();
        $hero = app(StructureManager::class)->create('hero', 'section');

        $this->get('/bladewright/components')
            ->assertOk()
            ->assertSee('hero', false)
            ->assertSee('data-bw-row-href="'.route('bladewright.admin.components.edit', $hero).'"', false);
    }

    /** Creating from the list opens the editor for the new component. */
    public function test_creating_from_the_list(): void
    {
        $this->actingAsRole();

        Livewire::test('bladewright::components-list')
            ->call('startCreating')
            ->set('newName', 'hero')
            ->set('newType', 'section')
            ->set('newLayout', 'grid')
            ->call('create')
            ->assertRedirect(route('bladewright.admin.components.edit', Structure::query()->firstOrFail()));

        $this->assertSame('grid', Structure::query()->firstOrFail()->layout);
    }

    /** A block and another component both go in, and the order can be changed. */
    public function test_the_editor_arranges_what_is_inside(): void
    {
        $this->actingAsRole();
        $hero = app(StructureManager::class)->create('hero', 'section');
        $block = app(BlockManager::class)->create('intro', 'markdown');
        app(StructureManager::class)->create('inner', 'article');

        $editor = Livewire::test('bladewright::component-editor', ['component' => $hero]);

        $editor->set('adding', 'block:intro')->call('add')->assertToast('Placed');
        $editor->set('adding', 'component:inner')->call('add')->assertToast('Placed');

        $this->assertSame(2, $hero->children()->count());

        // **Dragged into place**: the second row, dropped on the first.
        $editor->call('moveTo', '1', '0');

        $this->assertSame('component', $hero->children()->where('position', 1)->first()->child_kind);

        // Take the block out: the block itself stays on the shelf.
        $blockRow = $hero->children()->where('child_uuid', $block->uuid)->firstOrFail();
        $editor->call('remove', $blockRow->id)->assertToast('still on the shelf');

        $this->assertSame(1, $hero->children()->count());
        $this->assertSame(1, \Bladewright\Models\Block::query()->count());
    }

    /**
     * **Dropping says where a thing lands**, not which way it moved: the top
     * row dragged to the bottom leaves the rest closed up behind it.
     */
    public function test_dragging_puts_a_child_where_it_was_dropped(): void
    {
        $this->actingAsRole();
        $hero = app(StructureManager::class)->create('hero', 'section');

        $names = ['one', 'two', 'three'];

        foreach ($names as $name) {
            app(StructureManager::class)->insertBlock($hero, app(BlockManager::class)->create($name, 'markdown'));
        }

        $uuids = \Bladewright\Models\Block::query()->orderBy('id')->pluck('uuid', 'name');

        Livewire::test('bladewright::component-editor', ['component' => $hero])
            ->call('moveTo', '0', '2');

        $this->assertSame(
            [$uuids['two'], $uuids['three'], $uuids['one']],
            $hero->children()->pluck('child_uuid')->all(),
        );

        // The places are renumbered, not left with a hole.
        $this->assertSame([1, 2, 3], $hero->children()->pluck('position')->all());
    }

    /** **A component cannot hold itself, nor anything that already holds it.** */
    public function test_a_loop_is_refused(): void
    {
        $this->actingAsRole();
        $outer = app(StructureManager::class)->create('outer', 'section');
        $inner = app(StructureManager::class)->create('inner', 'article');
        app(StructureManager::class)->insertComponent($outer, $inner);

        // outer already holds inner; putting outer inside inner would loop.
        Livewire::test('bladewright::component-editor', ['component' => $inner])
            ->set('adding', 'component:outer')
            ->call('add')
            ->assertToast('round forever');

        $this->assertSame(0, $inner->children()->count());
    }

    /** The arrangement saves, and says how far it reached. */
    public function test_the_arrangement_saves_and_says_its_reach(): void
    {
        $this->actingAsRole();
        $hero = app(StructureManager::class)->create('hero', 'section');

        $pages = app(SitePages::class);
        $pages->insertComponent($pages->create('Home', ''), $hero);
        $pages->insertComponent($pages->create('About', 'about'), $hero);

        Livewire::test('bladewright::component-editor', ['component' => $hero])
            ->set('gap', '1.5rem')
            ->set('layout', 'grid')
            ->call('saveArrangement')
            ->assertToast('It changed on 2 pages at once');

        $hero->refresh();

        $this->assertSame('1.5rem', $hero->data['gap']);
        $this->assertSame('grid', $hero->layout);
    }

    /** Something that is not a size never reaches a style attribute. */
    public function test_a_bad_size_is_refused(): void
    {
        $this->actingAsRole();
        $hero = app(StructureManager::class)->create('hero', 'section');

        Livewire::test('bladewright::component-editor', ['component' => $hero])
            ->set('gap', 'red; background:url(x)')
            ->call('saveArrangement')
            ->assertToast('does not read as a size');

        $this->assertArrayNotHasKey('gap', $hero->refresh()->data ?? []);
    }

    /**
     * **Two faces, on pills**: what it looks like, and the HTML it comes out
     * as. The arrangement is not one of them — it is the column beside, where
     * it can be dragged.
     */
    public function test_the_editor_has_two_faces_and_a_tree(): void
    {
        $this->actingAsRole();
        $hero = app(StructureManager::class)->create('hero', 'section');
        $block = app(BlockManager::class)->create('intro', 'markdown');
        $block->forceFill(['data' => ['body' => '# 見出しだけ']])->save();
        app(StructureManager::class)->insertBlock($hero, $block);

        $this->get(route('bladewright.admin.components.edit', $hero))
            ->assertOk()
            ->assertSee('data-bw-pills="component" data-bw-pill="preview"', false)
            ->assertSee('data-bw-pills="component" data-bw-pill="code"', false)
            ->assertDontSee('data-bw-pill="tree"', false)
            // The arranging column: the component's own tag stands over its
            // contents, and the rows inside it are draggable.
            ->assertSee('>section</span>', false)
            ->assertSee('draggable="true" data-bw-path="0"', false)
            // The tree links a block to its own words.
            ->assertSee(route('bladewright.admin.blocks.edit', $block), false);
    }

    /** The preview is the component through the real renderer, unsaved. */
    public function test_the_preview_renders_the_component_alone(): void
    {
        $this->actingAsRole();
        $hero = app(StructureManager::class)->create('hero', 'section');
        $block = app(BlockManager::class)->create('intro', 'markdown');
        $block->forceFill(['data' => ['body' => '# 見出しだけ']])->save();
        app(StructureManager::class)->insertBlock($hero, $block);

        $preview = Livewire::test('bladewright::component-editor', ['component' => $hero])
            ->instance()
            ->preview();

        $this->assertStringContainsString('<section', $preview);
        $this->assertStringContainsString('見出しだけ', $preview);
        $this->assertStringContainsString('<!DOCTYPE html>', $preview);
    }

    /**
     * **The Code pill shows the component's own HTML**, starting from what
     * the arrangement makes. Write it and it becomes the component.
     */
    public function test_the_code_pill_shows_and_writes_the_markup(): void
    {
        $this->actingAsRole();
        $hero = app(StructureManager::class)->create('hero', 'section');

        $editor = Livewire::test('bladewright::component-editor', ['component' => $hero]);

        $this->assertStringContainsString('<section', $editor->get('markup'));

        // Untouched: nothing is stored, so the arrangement still leads.
        // **One Save settles the whole screen** — the arrangement and the code.
        $editor->call('save');
        $this->assertSame('', $hero->refresh()->data['markup']);

        $editor->set('markup', '<section>@foreach ([1, 2] as $n)<p>{{ $n }}</p>@endforeach</section>')
            ->call('save');

        $this->assertSame(
            '<section><p>1</p><p>2</p></section>',
            app(\Bladewright\Site\PublicSite::class)->structure($hero->refresh()),
        );
    }

    /** **A page never dies of it.** Broken Blade leaves a comment behind. */
    public function test_broken_blade_does_not_take_the_page_down(): void
    {
        $this->actingAsRole();
        $hero = app(StructureManager::class)->create('hero', 'section');

        $editor = Livewire::test('bladewright::component-editor', ['component' => $hero])
            ->set('markup', '{{ bladewright_no_such_helper() }}');

        $this->assertNotNull($editor->instance()->codeError());

        $editor->call('save');

        $this->assertStringContainsString('<!--', app(\Bladewright\Site\PublicSite::class)->structure($hero->refresh()));
    }

    /** Saving the arrangement leaves the hand-written markup where it is. */
    public function test_the_arrangement_does_not_touch_the_markup(): void
    {
        $this->actingAsRole();
        $hero = app(StructureManager::class)->create('hero', 'section');

        app(StructureManager::class)->saveMarkup($hero, '<section>書いたもの</section>');

        Livewire::test('bladewright::component-editor', ['component' => $hero])
            ->set('gap', '2rem')
            ->set('layout', 'grid')
            ->call('saveArrangement');

        $this->assertSame('<section>書いたもの</section>', $hero->refresh()->data['markup']);
        $this->assertSame('2rem', $hero->data['gap']);
    }

    /**
     * **A component wears the same Style card as a block**, on its whole
     * tag — the full-width band of colour a page is mostly made of. Names
     * follow the palette, and the CSS pill's words come last.
     */
    public function test_a_component_wears_its_own_look(): void
    {
        $this->actingAsRole();
        $hero = app(StructureManager::class)->create('hero', 'section');

        Livewire::test('bladewright::component-editor', ['component' => $hero])
            ->set('style.padding', '3rem 0')
            ->call('paint', 'background', 'accent')
            ->set('css', "background: accent;\npadding: 3rem 0;\nmin-height: 60vh;")
            ->assertSet('style.min-height', '60vh')
            ->call('save');

        $served = app(\Bladewright\Site\PublicSite::class)->structure($hero->refresh());

        $this->assertStringContainsString('padding:3rem 0', $served);
        $this->assertStringContainsString('background:#3538cd', $served);
        $this->assertStringContainsString('min-height:60vh', $served);

        // The palette still speaks for it: change the colour once, and the
        // band follows.
        app(\Bladewright\Support\Palette::class)->save(['accent' => '#c81e5b']);

        $this->assertStringContainsString('background:#c81e5b', app(\Bladewright\Site\PublicSite::class)->structure($hero->refresh()));
    }

    /**
     * On written code, a card edit rewrites only the tag's own style —
     * everything inside stands, and the tag's style reads back to the cards.
     */
    public function test_the_look_patches_written_code(): void
    {
        $this->actingAsRole();
        $hero = app(StructureManager::class)->create('hero', 'section');

        $editor = Livewire::test('bladewright::component-editor', ['component' => $hero])
            ->set('markup', '<section class="hero"><h1>手書き</h1></section>');

        $editor->call('paint', 'background', 'accent');

        $this->assertStringContainsString('class="hero"', $editor->get('markup'));
        $this->assertStringContainsString('background:#3538cd', $editor->get('markup'));
        $this->assertStringContainsString('<h1>手書き</h1>', $editor->get('markup'));

        // And written style comes back into the card.
        $editor->set('markup', '<section style="background:#123456"><h1>手書き</h1></section>')
            ->assertSet('style.background', '#123456');
    }

    /**
     * **A background may be a picture** — chosen from the media, landing in
     * the CSS as the URL a browser can fetch. Junk dressed as a url is not.
     */
    public function test_a_background_may_be_a_picture(): void
    {
        $this->actingAsRole();
        $hero = app(StructureManager::class)->create('hero', 'section');

        $file = app(\Bladewright\Media\MediaLibrary::class)->store(
            \Illuminate\Http\UploadedFile::fake()->image('sky.png'),
        );

        $editor = Livewire::test('bladewright::component-editor', ['component' => $hero])
            ->call('pickStyleImage', 'background')
            ->dispatch('bw-media-selected', path: $file->path)
            ->assertSet('stylePicking', null)
            ->call('save');

        // The quotes are entity-encoded in the attribute; a browser hands
        // the CSS real ones.
        $this->assertStringContainsString(
            'background:url(&#039;'.$file->url().'&#039;)',
            app(\Bladewright\Site\PublicSite::class)->structure($hero->refresh()),
        );

        // One url and nothing else: a second declaration is refused.
        $editor->set('style.background', 'url(x); position:fixed')->call('save');

        $this->assertArrayNotHasKey('background', $hero->refresh()->data['style']);
    }

    /** The gear leads to the settings; renaming keeps the uuid; deleting leaves the blocks. */
    public function test_the_settings(): void
    {
        $this->actingAsRole();
        $hero = app(StructureManager::class)->create('hero', 'section');
        $uuid = $hero->uuid;
        app(StructureManager::class)->insertBlock($hero, app(BlockManager::class)->create('intro', 'markdown'));

        $this->get(route('bladewright.admin.components.edit', $hero))
            ->assertOk()
            ->assertSee(route('bladewright.admin.components.settings', $hero), false)
            ->assertDontSee('Danger zone', false);

        Livewire::test('bladewright::component-settings', ['component' => $hero])
            ->set('name', 'banner')
            ->call('saveName')
            ->assertToast('The name has changed');

        $this->assertSame($uuid, $hero->refresh()->uuid);

        Livewire::test('bladewright::component-settings', ['component' => $hero])
            ->call('destroy')
            ->assertRedirect(route('bladewright.admin.components'));

        $this->assertSame(0, Structure::query()->count());
        $this->assertSame(1, \Bladewright\Models\Block::query()->count());
    }

    /**
     * **The Class card is the class attribute's mirror** on a component's
     * whole tag — `container-fluid` from a card, and a hand-written class
     * read back into it.
     */
    public function test_a_component_wears_its_class_on_the_tag(): void
    {
        $this->actingAsRole();
        $hero = app(StructureManager::class)->create('hero', 'section');

        $editor = Livewire::test('bladewright::component-editor', ['component' => $hero])
            ->set('class', 'container-fluid py-5');

        $this->assertStringContainsString('<section class="container-fluid py-5"', $editor->get('markup'));

        $editor->call('save');

        $this->assertSame('container-fluid py-5', $hero->refresh()->data['class']);
        $this->assertStringContainsString('class="container-fluid py-5"', app(\Bladewright\Site\PublicSite::class)->structure($hero));

        // Written on the tag, it comes back into the card.
        $editor->set('markup', '<section class="hero-band"><h1>手書き</h1></section>')
            ->assertSet('class', 'hero-band');
    }

    /**
     * **A band starts from its own tag.** A header component is a <header>,
     * a footer a <footer> — the layout's frame writes neither.
     */
    public function test_header_and_footer_are_tags_a_component_can_be(): void
    {
        $this->actingAsRole();

        $header = app(StructureManager::class)->create('site-header', 'header');
        $footer = app(StructureManager::class)->create('site-footer', 'footer');

        $this->assertStringStartsWith('<header', app(\Bladewright\Site\PublicSite::class)->structure($header));
        $this->assertStringStartsWith('<footer', app(\Bladewright\Site\PublicSite::class)->structure($footer));
    }

    /**
     * **A band's component is the layout's own.** It cannot be put inside a
     * component or onto a page — the frame already puts it on every page —
     * and the shelves those screens offer do not show it.
     */
    public function test_a_bands_component_stands_nowhere_else(): void
    {
        $this->actingAsRole();

        $header = app(StructureManager::class)->create('site-header', 'header');
        $hero = app(StructureManager::class)->create('hero', 'section');

        try {
            app(StructureManager::class)->insertComponent($hero, $header);
            $this->fail('A header went inside a section.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('placed on a layout', $e->getMessage());
        }

        $page = app(\Bladewright\Blocks\SitePages::class)->create('Home', '');

        try {
            app(\Bladewright\Blocks\SitePages::class)->insertComponent($page, $header);
            $this->fail('A header went onto a page.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('placed on a layout', $e->getMessage());
        }

        // The shelves say so too.
        $this->assertArrayNotHasKey('component:site-header',
            Livewire::test('bladewright::component-editor', ['component' => $hero])->instance()->choices());
        $this->assertNotContains('site-header',
            Livewire::test('bladewright::page-editor', ['page' => $page])->instance()->available());
    }
}
