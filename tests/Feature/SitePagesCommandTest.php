<?php

namespace Bladewright\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Bladewright\Blocks\LayoutManager;
use Bladewright\Blocks\SitePages;
use Bladewright\Blocks\StructureManager;
use Bladewright\Models\Page;
use Bladewright\Models\PageChild;
use Bladewright\Tests\TestCase;

/**
 * `bladewright:pages`, in the core shape — the top of the four-layer model.
 * **A page answers for its URL and its publishing; what it shows is
 * components, referenced by uuid.**
 */
class SitePagesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_page_is_created_with_its_url(): void
    {
        $this->artisan('bladewright:pages', ['--create' => 'About', '--url' => 'about'])
            ->expectsOutputToContain('is at /about')
            ->assertSuccessful();

        $page = Page::query()->firstOrFail();

        $this->assertSame('about', $page->url);
        $this->assertNotNull($page->uuid);
    }

    /** The URL has to be said — "" is the front page, absence is a question. */
    public function test_the_url_is_required(): void
    {
        $this->artisan('bladewright:pages', ['--create' => 'About'])
            ->expectsOutputToContain('--url=')
            ->assertFailed();

        $this->artisan('bladewright:pages', ['--create' => 'Home', '--url' => ''])
            ->expectsOutputToContain('is at /')
            ->assertSuccessful();

        $this->assertSame('', Page::query()->firstOrFail()->url);
    }

    /** Two pages cannot answer one URL. */
    public function test_a_taken_url_is_refused(): void
    {
        $this->artisan('bladewright:pages', ['--create' => 'About', '--url' => 'about'])->assertSuccessful();

        $this->artisan('bladewright:pages', ['--create' => 'Company', '--url' => 'about'])
            ->expectsOutputToContain('already in use')
            ->assertFailed();
    }

    /** No layout is allowed — and said out loud, as the table orders. */
    public function test_a_bare_page_is_warned_about(): void
    {
        $this->artisan('bladewright:pages', ['--create' => 'About', '--url' => 'about'])
            ->expectsOutputToContain('wears no layout')
            ->assertSuccessful();
    }

    /** With a layout, the name becomes a uuid on the way in. */
    public function test_the_layout_is_resolved_to_its_uuid(): void
    {
        $layout = $this->app->make(LayoutManager::class)->create('site');

        $this->artisan('bladewright:pages', ['--create' => 'About', '--url' => 'about', '--layout' => 'site'])
            ->assertSuccessful();

        $this->assertSame($layout->uuid, Page::query()->firstOrFail()->layout_uuid);

        $this->artisan('bladewright:pages', ['--create' => 'News', '--url' => 'news', '--layout' => 'nope'])
            ->expectsOutputToContain('not a layout')
            ->assertFailed();
    }

    /** A component goes on by name and is held by uuid; --order places it. */
    public function test_components_stand_on_pages_in_order(): void
    {
        $pages = $this->app->make(SitePages::class);
        $page = $pages->create('Home', '');

        $manager = $this->app->make(StructureManager::class);
        $hero = $manager->create('hero', 'section');
        $footer = $manager->create('footer', 'nav');
        $middle = $manager->create('middle', 'article');

        $this->artisan('bladewright:components', ['--insert' => 'hero', '--to' => 'Home'])
            ->expectsOutputToContain('stands on [Home] at 1')
            ->assertSuccessful();
        $this->artisan('bladewright:components', ['--insert' => 'footer', '--to' => 'Home'])->assertSuccessful();
        $this->artisan('bladewright:components', ['--insert' => 'middle', '--to' => 'Home', '--order' => '2'])
            ->expectsOutputToContain('at 2')
            ->assertSuccessful();

        $this->assertSame(
            [$hero->uuid, $middle->uuid, $footer->uuid],
            $page->children()->pluck('child_uuid')->all(),
        );
    }

    /** The copy shows the same components, takes a free URL, and is never published. */
    public function test_a_copy_shares_components_and_stays_down(): void
    {
        $pages = $this->app->make(SitePages::class);
        $page = $pages->create('About', 'about');
        $pages->publish($page);
        $pages->insertComponent($page, $this->app->make(StructureManager::class)->create('hero', 'section'));

        $this->artisan('bladewright:pages', ['--copy' => 'About', '--to' => 'About B'])
            ->expectsOutputToContain('not published')
            ->assertSuccessful();

        $copy = Page::query()->where('name', 'About B')->firstOrFail();

        $this->assertSame('about-2', $copy->url);
        $this->assertFalse($copy->is_published);
        $this->assertSame(1, $copy->children()->count());
    }

    /** Renaming moves the name and nothing else. */
    public function test_renaming_moves_neither_url_nor_uuid(): void
    {
        $page = $this->app->make(SitePages::class)->create('About', 'about');
        $uuid = $page->uuid;

        $this->artisan('bladewright:pages', ['--rename' => 'About', '--to' => 'Company'])
            ->expectsOutputToContain('The URL did not move')
            ->assertSuccessful();

        $page->refresh();

        $this->assertSame('Company', $page->name);
        $this->assertSame('about', $page->url);
        $this->assertSame($uuid, $page->uuid);
    }

    /** Publishing, with and without a window; a broken date is refused. */
    public function test_publishing_and_the_window(): void
    {
        $this->app->make(SitePages::class)->create('About', 'about');

        $this->artisan('bladewright:pages', ['--publish' => 'About', '--from' => '2026-10-01 09:00', '--until' => '2026-12-01 09:00'])
            ->expectsOutputToContain('is published from')
            ->assertSuccessful();

        $page = Page::query()->firstOrFail();

        $this->assertTrue($page->is_published);
        $this->assertSame('2026-10-01 09:00', $page->published_from->format('Y-m-d H:i'));

        $this->artisan('bladewright:pages', ['--publish' => 'About', '--from' => 'not a date'])
            ->expectsOutputToContain('does not read as a date')
            ->assertFailed();
    }

    /** Deleting warns, asks, and leaves the components standing. */
    public function test_deleting_leaves_the_components(): void
    {
        $pages = $this->app->make(SitePages::class);
        $page = $pages->create('About', 'about');
        $pages->insertComponent($page, $this->app->make(StructureManager::class)->create('hero', 'section'));

        $this->artisan('bladewright:pages', ['--delete' => 'About'])
            ->expectsOutputToContain('the components it showed stay')
            ->expectsConfirmation('Delete [About]?', 'yes')
            ->assertSuccessful();

        $this->assertSame(0, Page::query()->count());
        $this->assertSame(0, PageChild::query()->count());
        $this->assertSame(1, \Bladewright\Models\Structure::query()->count());
    }

    /** Deleting a component sweeps it off every page, and says the reach first. */
    public function test_deleting_a_component_sweeps_the_pages(): void
    {
        $pages = $this->app->make(SitePages::class);
        $hero = $this->app->make(StructureManager::class)->create('hero', 'section');
        $pages->insertComponent($pages->create('Home', ''), $hero);
        $pages->insertComponent($pages->create('About', 'about'), $hero);

        $this->artisan('bladewright:components', ['--delete' => 'hero'])
            ->expectsOutputToContain('Shown on 2 page(s)')
            ->expectsConfirmation('Delete [hero]?', 'yes')
            ->assertSuccessful();

        $this->assertSame(0, PageChild::query()->count());
    }
}
