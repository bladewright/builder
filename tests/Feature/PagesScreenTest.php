<?php

namespace Bladewright\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Bladewright\Blocks\BlockManager;
use Bladewright\Blocks\LayoutManager;
use Bladewright\Blocks\SitePages;
use Bladewright\Blocks\StructureManager;
use Bladewright\Models\Page;
use Bladewright\Tests\TestCase;

/**
 * The pages screens: the list, the editor with the real page on the left,
 * the publish window, and the settings behind the gear.
 */
class PagesScreenTest extends TestCase
{
    use RefreshDatabase;

    /** The list shows pages, and the row is the way in — by uuid. */
    public function test_the_list_shows_pages_and_rows_open_them(): void
    {
        $this->actingAsRole();
        $page = app(SitePages::class)->create('About', 'about');

        $this->get('/bladewright/pages')
            ->assertOk()
            ->assertSee('About', false)
            ->assertSee('data-bw-row-href="'.route('bladewright.admin.pages.edit', $page).'"', false);
    }

    /** The admin's front door opens on the pages. */
    public function test_the_front_door_opens_on_pages(): void
    {
        $this->actingAsRole();

        $this->get('/bladewright')->assertRedirect(route('bladewright.admin.pages'));
    }

    /** The create modal asks name, URL and frame; a taken URL says so in place. */
    public function test_creating_from_the_list(): void
    {
        $this->actingAsRole();
        app(LayoutManager::class)->create('site');

        Livewire::test('bladewright::pages-list')
            ->call('startCreating')
            ->assertSet('newLayout', 'site')
            ->set('newName', 'About')
            ->set('newUrl', 'about')
            ->call('create')
            ->assertRedirect(route('bladewright.admin.pages.edit', Page::query()->firstOrFail()));

        $this->assertSame(
            app(LayoutManager::class)->find('site')->uuid,
            Page::query()->firstOrFail()->layout_uuid,
        );

        Livewire::test('bladewright::pages-list')
            ->call('startCreating')
            ->set('newName', 'Company')
            ->set('newUrl', 'about')
            ->call('create')
            ->assertHasErrors('newUrl');
    }

    /** The editor shows the real page's preview and arranges components. */
    public function test_the_editor_arranges_components(): void
    {
        $this->actingAsRole();
        $pages = app(SitePages::class);
        $page = $pages->create('Home', '');

        $hero = app(StructureManager::class)->create('hero', 'section');
        $footer = app(StructureManager::class)->create('footer', 'nav');
        $pages->insertComponent($page, $hero);
        $pages->insertComponent($page, $footer);

        $editor = Livewire::test('bladewright::page-editor', ['page' => $page]);

        $editor->assertSee('hero')->assertSee('footer');

        // **Dragged into place — into the draft.** The live page does not
        // move until Save; the preview shows the draft all the same.
        $editor->call('moveTo', '1', '0');

        $this->assertSame(
            [$hero->uuid, $footer->uuid],
            $page->children()->pluck('child_uuid')->all(),
        );
        $this->assertStringContainsString('data-bw-slot="1" data-bw-component="'.$footer->uuid.'"', $editor->instance()->preview());

        // Take hero off the draft: still on the saved page, gone on Save.
        $editor->call('removeSlot', 2)->call('save');

        $this->assertSame([$footer->uuid], $page->refresh()->children()->pluck('child_uuid')->all());
        $this->assertSame(2, \Bladewright\Models\Structure::query()->count());
    }

    /**
     * **The arrangement is the column, not a pill.** The tree stands beside
     * the preview, the page's own row is draggable, and a block's name opens
     * its editor.
     */
    public function test_the_arrangement_tree(): void
    {
        $this->actingAsRole();
        $pages = app(SitePages::class);
        $page = $pages->create('Home', '');

        $block = app(BlockManager::class)->create('intro', 'markdown');
        $hero = app(StructureManager::class)->create('hero', 'section');
        app(StructureManager::class)->insertBlock($hero, $block);
        $pages->insertComponent($page, $hero);

        // The frame's own bands stand in the tree, read-only, where a
        // visitor meets them.
        $layout = app(\Bladewright\Blocks\LayoutManager::class)->create('site', 'header');
        $band = app(StructureManager::class)->create('site-header', 'header');
        app(\Bladewright\Blocks\LayoutManager::class)->wear($layout, 'header', $band);
        app(SitePages::class)->changeLayout($page, 'site');

        $this->get(route('bladewright.admin.pages.edit', $page))
            ->assertOk()
            ->assertSee('hero', false)
            ->assertSee('intro', false)
            // Everything of the page's own can be picked up, at every depth.
            ->assertSee('draggable="true" data-bw-path="0"', false)
            ->assertSee('draggable="true" data-bw-path="0.0"', false)
            // Rows open their cards here — a press, not a navigation — and
            // the bare footer still links to the frame's own screen.
            ->assertSee('site-header', false)
            ->assertSee('Nothing here yet.', false)
            ->assertSee(route('bladewright.admin.layouts.edit', $layout), false)
            ->assertSee("openRow(&#039;", false);
    }

    /**
     * **The grid inside a section is dragged from here too.** The order is
     * what the page shows, so it is changed where the page is looked at —
     * the rule is still the component's own.
     */
    public function test_dragging_reaches_inside_a_component(): void
    {
        $this->actingAsRole();
        $pages = app(SitePages::class);
        $page = $pages->create('Home', '');

        $hero = app(StructureManager::class)->create('hero', 'section');
        $one = app(BlockManager::class)->create('one', 'markdown');
        $two = app(BlockManager::class)->create('two', 'markdown');
        app(StructureManager::class)->insertBlock($hero, $one);
        app(StructureManager::class)->insertBlock($hero, $two);
        $pages->insertComponent($page, $hero);

        $editor = Livewire::test('bladewright::page-editor', ['page' => $page]);

        // Moved in the draft, landed on Save — however deep it was.
        $editor->call('moveTo', '0.1', '0.0');

        $this->assertSame([$one->uuid, $two->uuid], $hero->children()->pluck('child_uuid')->all());

        $editor->call('save');

        $this->assertSame(
            [$two->uuid, $one->uuid],
            $hero->refresh()->children()->pluck('child_uuid')->all(),
        );

        // Across two parents, nothing moves.
        $editor->call('moveTo', '0.0', '0')->call('save');

        $this->assertSame([$two->uuid, $one->uuid], $hero->refresh()->children()->pluck('child_uuid')->all());
    }

    /** Adding places the chosen component at the end. */
    public function test_the_editor_places_a_component(): void
    {
        $this->actingAsRole();
        $page = app(SitePages::class)->create('Home', '');
        app(StructureManager::class)->create('hero', 'section');

        $editor = Livewire::test('bladewright::page-editor', ['page' => $page])
            ->set('adding', 'hero')
            ->call('add')
            ->assertToast('Placed');

        // In the draft only, until Save.
        $this->assertSame(0, $page->children()->count());

        $editor->call('save');

        $this->assertSame(1, $page->refresh()->children()->count());
    }

    /** The preview route serves the page whole, published or not. */
    public function test_the_preview_shows_the_unpublished_page(): void
    {
        $this->actingAsRole();
        $pages = app(SitePages::class);
        $page = $pages->create('Home', '');

        $block = app(BlockManager::class)->create('intro', 'markdown');
        $block->forceFill(['data' => ['body' => '# まだ未公開の下書き']])->save();
        $hero = app(StructureManager::class)->create('hero', 'section');
        app(StructureManager::class)->insertBlock($hero, $block);
        $pages->insertComponent($page, $hero);

        // Not published: the public site refuses, the admin's preview shows.
        $this->get('/')->assertNotFound();
        $this->get(route('bladewright.admin.pages.preview', $page))
            ->assertOk()
            ->assertSee('まだ未公開の下書き', false);
    }

    /** A stranger gets no preview. */
    public function test_the_preview_needs_the_admin(): void
    {
        $page = app(SitePages::class)->create('Home', '');

        $this->get(route('bladewright.admin.pages.preview', $page))
            ->assertRedirect('/bladewright/login');
    }

    /** The publish window puts it on the site and takes it off. */
    public function test_the_publish_window(): void
    {
        $this->actingAsRole();
        $page = app(SitePages::class)->create('Home', '');

        Livewire::test('bladewright::page-publish', ['page' => $page])
            ->call('publish')
            ->assertToast('on the site');

        $this->assertTrue($page->refresh()->is_published);

        Livewire::test('bladewright::page-publish', ['page' => $page])
            ->set('from', '2026-10-01T09:00')
            ->call('publish');

        $this->assertSame('2026-10-01 09:00', $page->refresh()->published_from->format('Y-m-d H:i'));

        Livewire::test('bladewright::page-publish', ['page' => $page])
            ->call('unpublish')
            ->assertToast('Unpublished');

        $this->assertFalse($page->refresh()->is_published);
    }

    /** Settings: rename leaves the URL; the URL box says its own errors; the frame changes. */
    public function test_the_settings(): void
    {
        $this->actingAsRole();
        app(LayoutManager::class)->create('site');
        $pages = app(SitePages::class);
        $pages->create('About', 'about');
        $page = $pages->create('Home', '');

        Livewire::test('bladewright::page-settings', ['page' => $page])
            ->set('name', 'Front page')
            ->call('saveName')
            ->assertToast('The URL did not move');

        $this->assertSame('', $page->refresh()->url);

        Livewire::test('bladewright::page-settings', ['page' => $page])
            ->set('url', 'about')
            ->call('saveUrl')
            ->assertHasErrors('url');

        Livewire::test('bladewright::page-settings', ['page' => $page])
            ->set('url', 'front')
            ->call('saveUrl')
            ->assertToast('The URL has changed');

        $this->assertSame('front', $page->refresh()->url);

        Livewire::test('bladewright::page-settings', ['page' => $page])
            ->set('layout', 'site')
            ->call('saveLayout')
            ->assertToast('The frame is site now');

        $this->assertNotNull($page->refresh()->layout_uuid);
    }

    /** The gear leads to the settings, where the danger lives — not under the editor. */
    public function test_the_gear_leads_to_the_settings(): void
    {
        $this->actingAsRole();
        $page = app(SitePages::class)->create('Home', '');

        $this->get(route('bladewright.admin.pages.edit', $page))
            ->assertOk()
            ->assertSee(route('bladewright.admin.pages.settings', $page), false)
            ->assertDontSee('Danger zone', false);

        $this->get(route('bladewright.admin.pages.settings', $page))
            ->assertOk()
            ->assertSee('Danger zone', false);
    }

    /** Deleting from the settings leaves the components standing. */
    public function test_deleting_leaves_the_components(): void
    {
        $this->actingAsRole();
        $pages = app(SitePages::class);
        $page = $pages->create('Home', '');
        $pages->insertComponent($page, app(StructureManager::class)->create('hero', 'section'));

        Livewire::test('bladewright::page-settings', ['page' => $page])
            ->call('destroy')
            ->assertRedirect(route('bladewright.admin.pages'));

        $this->assertSame(0, Page::query()->count());
        $this->assertSame(1, \Bladewright\Models\Structure::query()->count());
    }

    /**
     * **The page makes the same bargain as every layer below it.** The Code
     * pill starts as what the arrangement makes; write it and that is the
     * page — inside its frame, which stays the layout's — and emptying it
     * hands the page back to the arrangement.
     */
    public function test_the_code_pill_shows_and_writes_the_page(): void
    {
        $this->actingAsRole();

        $pages = app(SitePages::class);
        $page = $pages->create('Home', 'home');

        $block = app(\Bladewright\Blocks\BlockManager::class)->create('intro', 'markdown');
        $block->forceFill(['data' => ['body' => '組まれた言葉']])->save();
        $hero = app(StructureManager::class)->create('hero', 'section');
        app(StructureManager::class)->insertBlock($hero, $block);
        $pages->insertComponent($page, $hero);
        $pages->publish($page);

        $editor = Livewire::test('bladewright::page-editor', ['page' => $page->refresh()]);

        // The starting point is the whole document the four layers make.
        $this->assertStringStartsWith('<!DOCTYPE html>', trim($editor->get('markup')));
        $this->assertStringContainsString('<section', $editor->get('markup'));
        $this->assertStringContainsString('組まれた言葉', $editor->get('markup'));

        // Written by hand, that is the page — and the amber dot knows it,
        // and the preview shows it before anything is saved.
        $editor->set('markup', '<h1>手書きの家</h1>');
        $this->assertTrue($editor->instance()->dirty());
        $this->assertStringContainsString('<h1>手書きの家</h1>', $editor->instance()->preview());

        $editor->call('save')->assertToast('Saved');
        $this->assertFalse($editor->instance()->dirty());

        $this->get('/home')
            ->assertOk()
            ->assertSee('<h1>手書きの家</h1>', false)
            ->assertDontSee('組まれた言葉', false);

        // Emptied, the arrangement leads again.
        $editor->set('markup', '')->call('save');

        $this->get('/home')->assertOk()->assertSee('組まれた言葉', false);
    }

    /** Broken Blade leaves a comment in the frame, and the editor says why. */
    public function test_broken_page_blade_does_not_take_the_site_down(): void
    {
        $this->actingAsRole();

        $pages = app(SitePages::class);
        $page = $pages->create('Home', 'home');
        $pages->publish($page);

        $editor = Livewire::test('bladewright::page-editor', ['page' => $page])
            ->set('markup', '{{ $nobody->home }}')
            ->call('save');

        $this->assertNotNull($editor->instance()->codeError());
        $this->get('/home')->assertOk()->assertSee('<!--', false);
    }

    /**
     * **The words are the page's, the place is the frame's.** The SEO card
     * writes what the page says about itself; the frame's @bwmeta puts it
     * in the head — title falling back to the page's name, description and
     * picture only when written, noindex only when asked.
     */
    public function test_the_page_speaks_for_itself_in_the_head(): void
    {
        $this->actingAsRole();

        app(\Bladewright\Blocks\LayoutManager::class)->create('site', 'header');

        $pages = app(SitePages::class);
        $page = $pages->create('About us', 'about', 'site');
        $pages->publish($page);

        // Nothing written yet: the name is the title, and nothing else is said.
        $this->get('/about')
            ->assertOk()
            ->assertSee('<title>About us</title>', false)
            ->assertDontSee('name="description"', false)
            ->assertDontSee('noindex', false);

        $file = app(\Bladewright\Media\MediaLibrary::class)->store(
            \Illuminate\Http\UploadedFile::fake()->image('cover.png'),
        );

        Livewire::test('bladewright::page-settings', ['page' => $page])
            ->set('seo.title', '会社案内 | Bladewright')
            ->set('seo.description', '私たちのこと。')
            ->set('seo.noindex', true)
            ->call('pickImage')
            ->dispatch('bw-media-selected', path: $file->path)
            ->assertSet('pickingImage', false)
            ->call('saveSeo')
            ->assertToast('Saved');

        $this->get('/about')
            ->assertOk()
            ->assertSee('<title>会社案内 | Bladewright</title>', false)
            ->assertSee('<meta name="description" content="私たちのこと。">', false)
            ->assertSee('<meta property="og:title" content="会社案内 | Bladewright">', false)
            // Absolute, so a crawler can fetch it.
            ->assertSee('<meta property="og:image" content="http://localhost'.$file->url().'"', false)
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);

        // A page with no frame says the same things in its bare document.
        app(SitePages::class)->changeLayout($page, null);

        $this->get('/about')->assertOk()->assertSee('<title>会社案内 | Bladewright</title>', false);
    }
}
