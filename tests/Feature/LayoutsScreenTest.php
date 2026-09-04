<?php

namespace Bladewright\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Bladewright\Blocks\BlockManager;
use Bladewright\Blocks\LayoutManager;
use Bladewright\Blocks\StructureManager;
use Bladewright\Blocks\SitePages;
use Bladewright\Models\Layout;
use Bladewright\Models\Page;
use Bladewright\Tests\TestCase;

/**
 * The layouts screens — the last floor of the four-layer world.
 * **The frame is the site's own Blade**, written here.
 */
class LayoutsScreenTest extends TestCase
{
    use RefreshDatabase;

    /** The list shows the layouts, and the row is the way in — by uuid, not id. */
    public function test_the_list_shows_layouts_and_rows_open_them(): void
    {
        $this->actingAsRole();
        $layout = app(LayoutManager::class)->create('site', 'header');

        $this->get('/bladewright/layouts')
            ->assertOk()
            ->assertSee('site', false)
            ->assertSee('data-bw-row-href="'.route('bladewright.admin.layouts.edit', $layout).'"', false)
            ->assertSee($layout->uuid, false);
    }

    /** Creating from the list opens the editor for the new layout. */
    public function test_creating_from_the_list_opens_the_editor(): void
    {
        $this->actingAsRole();

        Livewire::test('bladewright::layouts-list')
            ->call('startCreating')
            ->set('newName', 'site')
            ->set('newType', 'sidebar')
            ->call('create')
            ->assertRedirect(route('bladewright.admin.layouts.edit', Layout::query()->firstOrFail()));

        $layout = Layout::query()->firstOrFail();

        $this->assertSame('sidebar', $layout->type);

        // **Presentable from the first minute** — a whole document, with the
        // page's own place in it.
        $this->assertStringContainsString('<!DOCTYPE html>', $layout->content);
        $this->assertStringContainsString('$slot', $layout->content);
    }

    /**
     * **Pico dresses the bare tags** — which is what blocks put out, so a
     * site can look finished with no classes written at all.
     */
    public function test_a_pico_frame_is_born_dressed(): void
    {
        $this->actingAsRole();
        app(\Bladewright\Support\Framework::class)->save('pico');
        $layout = app(LayoutManager::class)->create('site', 'header');

        // The link is not in the frame — it is the site's declaration,
        // asked at render time.
        $this->assertStringContainsString('@bwframework', $layout->content);
        $this->assertStringContainsString('$slot', $layout->content);

        $page = app(SitePages::class)->create('Home', '', 'site');
        app(SitePages::class)->publish($page);

        $this->get('/')->assertOk()->assertSee('@picocss/pico', false);
    }

    /** A taken name is refused where it was typed. */
    public function test_a_taken_name_is_refused_in_the_modal(): void
    {
        $this->actingAsRole();
        app(LayoutManager::class)->create('site');

        Livewire::test('bladewright::layouts-list')
            ->call('startCreating')
            ->set('newName', 'site')
            ->call('create')
            ->assertHasErrors('newName');
    }

    /** The editor writes the frame, and says how far the edit reached. */
    public function test_the_editor_saves_the_frame_and_says_the_reach(): void
    {
        $this->actingAsRole();
        $layout = app(LayoutManager::class)->create('site', 'header');

        $pages = app(SitePages::class);
        $pages->create('Home', '', 'site');
        $pages->create('About', 'about', 'site');

        Livewire::test('bladewright::layout-editor', ['layout' => $layout])
            ->set('content', '<html><body><header>My site</header>{{ $slot }}</body></html>')
            ->call('save')
            ->assertToast('It changed on 2 pages at once');

        $this->assertStringContainsString('My site', $layout->refresh()->content);
    }

    /**
     * **The preview is the frame through the real renderer**, from what is on
     * the screen rather than what was last saved.
     */
    public function test_the_preview_is_the_frame_as_it_stands(): void
    {
        $this->actingAsRole();
        $layout = app(LayoutManager::class)->create('site', 'header');

        $editor = Livewire::test('bladewright::layout-editor', ['layout' => $layout])
            ->set('content', '<html><body><header>{{ strtoupper("my site") }}</header>{{ $slot }}</body></html>');

        $this->assertStringContainsString('MY SITE', $editor->instance()->preview());
        $this->assertNull($editor->instance()->frameError());

        // Nothing was saved by typing.
        $this->assertStringNotContainsString('MY SITE', $layout->refresh()->content);
    }

    /**
     * **A frame that cannot run says so**, on the screen where it is written,
     * instead of taking the editor down with it.
     */
    public function test_broken_blade_is_said_and_not_thrown(): void
    {
        $this->actingAsRole();
        $layout = app(LayoutManager::class)->create('site', 'header');

        $editor = Livewire::test('bladewright::layout-editor', ['layout' => $layout])
            ->set('content', '{{ bladewright_no_such_helper() }}');

        $this->assertNotNull($editor->instance()->frameError());
        $this->assertSame('', $editor->instance()->preview());
    }

    /** **Said, not refused.** A frame with nowhere for the page still saves. */
    public function test_a_frame_without_a_slot_is_said(): void
    {
        $this->actingAsRole();
        $layout = app(LayoutManager::class)->create('site', 'header');

        $editor = Livewire::test('bladewright::layout-editor', ['layout' => $layout]);

        $this->assertTrue($editor->instance()->holdsThePage());

        $editor->set('content', '<html><body><header>Only a header</header></body></html>');

        $this->assertFalse($editor->instance()->holdsThePage());

        $editor->call('save');

        $this->assertStringContainsString('Only a header', $layout->refresh()->content);
    }

    /**
     * **The header and the footer are components; the middle is the page's.**
     * Placing one reaches every page in the frame at once, and taking it out
     * leaves the component itself on the shelf — **the same grammar as every
     * other screen**: choose, put it in, take it out. Nothing is swapped.
     */
    public function test_the_bands_are_places_to_put_components(): void
    {
        $this->actingAsRole();
        $layout = app(LayoutManager::class)->create('site', 'header');

        // **The component brings its own tag**: this is the <header> itself.
        $header = app(StructureManager::class)->create('site-header', 'header');
        $logo = app(BlockManager::class)->create('logo', 'markdown');
        $logo->forceFill(['data' => ['body' => '# 屋号']])->save();
        app(StructureManager::class)->insertBlock($header, $logo);

        $page = app(SitePages::class)->create('Home', '', 'site');
        app(SitePages::class)->publish($page);

        $editor = Livewire::test('bladewright::layout-editor', ['layout' => $layout]);

        // Three bands, and the page's own cannot be chosen for.
        $this->assertSame(['header', 'main', 'footer'], array_column($editor->instance()->rows(), 'band'));

        // Bare until something is placed.
        $this->get('/')->assertOk()->assertDontSee('屋号', false);

        $editor->set('bands.header', 'site-header')->call('wear', 'header')->assertToast('Placed');

        $this->assertSame($header->uuid, $layout->refresh()->header_uuid);
        // The frame writes no <header> of its own — the component's tag is
        // the band.
        $this->get('/')->assertOk()->assertSee('屋号', false)->assertSee('<header', false);

        // Nothing chosen is not an instruction.
        $editor->set('bands.header', '')->call('wear', 'header')->assertToast('Choose something');

        // Out again with the ×: the component stays on the shelf.
        $editor->call('bare', 'header')->assertToast('still on the shelf');

        $this->assertNull($layout->refresh()->header_uuid);
        $this->assertSame(1, \Bladewright\Models\Structure::query()->count());
    }

    /** **The page's own band is not a band anything can be put in.** */
    public function test_the_middle_band_takes_nothing(): void
    {
        $this->actingAsRole();
        $layout = app(LayoutManager::class)->create('site', 'header');
        $component = app(StructureManager::class)->create('site-header', 'header');

        $this->expectException(\InvalidArgumentException::class);

        app(LayoutManager::class)->wear($layout, 'main', $component);
    }

    /**
     * **A band takes only its own kind**, so it always starts from its tag —
     * and each band's picker offers only that kind.
     */
    public function test_a_band_takes_only_its_own_kind(): void
    {
        $this->actingAsRole();
        $layout = app(LayoutManager::class)->create('site', 'header');

        app(StructureManager::class)->create('site-header', 'header');
        app(StructureManager::class)->create('hero', 'section');

        $editor = Livewire::test('bladewright::layout-editor', ['layout' => $layout]);

        $this->assertSame(['site-header'], $editor->instance()->choices('header'));
        $this->assertSame([], $editor->instance()->choices('footer'));

        $this->expectException(\InvalidArgumentException::class);

        app(LayoutManager::class)->wear($layout, 'header', app(StructureManager::class)->find('hero'));
    }

    /** A deleted component leaves no frame wearing something that is gone. */
    public function test_deleting_a_worn_component_bares_the_band(): void
    {
        $this->actingAsRole();
        $layout = app(LayoutManager::class)->create('site', 'header');
        $footer = app(StructureManager::class)->create('site-footer', 'footer');

        app(LayoutManager::class)->wear($layout, 'footer', $footer);

        Livewire::test('bladewright::component-settings', ['component' => $footer])->call('destroy');

        $this->assertNull($layout->refresh()->footer_uuid);
    }

    /** The editor carries a gear to the settings, where the danger lives. */
    public function test_the_gear_leads_to_the_settings(): void
    {
        $this->actingAsRole();
        $layout = app(LayoutManager::class)->create('site', 'header');

        $this->get(route('bladewright.admin.layouts.edit', $layout))
            ->assertOk()
            ->assertSee(route('bladewright.admin.layouts.settings', $layout), false)
            ->assertDontSee('Danger zone', false);

        $this->get(route('bladewright.admin.layouts.settings', $layout))
            ->assertOk()
            ->assertSee('Danger zone', false);
    }

    /**
     * Renaming keeps the uuid, so the pages wearing it never notice.
     * **Deleting leaves the pages** — they stand bare until another is chosen.
     */
    public function test_settings_rename_and_delete(): void
    {
        $this->actingAsRole();
        $layout = app(LayoutManager::class)->create('site', 'header');
        $uuid = $layout->uuid;

        $page = app(SitePages::class)->create('Home', '', 'site');

        Livewire::test('bladewright::layout-settings', ['layout' => $layout])
            ->set('name', 'frame')
            ->call('saveName')
            ->assertToast('The name has changed');

        $this->assertSame($uuid, $layout->refresh()->uuid);
        $this->assertSame($uuid, $page->refresh()->layout_uuid);

        Livewire::test('bladewright::layout-settings', ['layout' => $layout])
            ->call('destroy')
            ->assertRedirect(route('bladewright.admin.layouts'));

        $this->assertSame(0, Layout::query()->count());
        $this->assertSame(1, Page::query()->count());
        $this->assertNull($page->refresh()->layout_uuid);
    }

    /** A stranger sees none of it. */
    public function test_the_screens_need_the_admin(): void
    {
        $layout = app(LayoutManager::class)->create('site');

        $this->get('/bladewright/layouts')->assertRedirect('/bladewright/login');
        $this->get(route('bladewright.admin.layouts.edit', $layout))->assertRedirect('/bladewright/login');
    }
}
