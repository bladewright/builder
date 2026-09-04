<?php

namespace Bladewright\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Bladewright\Blocks\LayoutManager;
use Bladewright\Blocks\SitePages;
use Bladewright\Support\SiteCss;
use Bladewright\Tests\TestCase;

/**
 * The site's one stylesheet: kept with the content, served as a file.
 * **This is where what an inline style cannot say gets said** — hover,
 * media queries, shared classes.
 */
class SiteCssTest extends TestCase
{
    use RefreshDatabase;

    /** Saved on the screen, served at the URL — to anybody, like the media. */
    public function test_it_is_written_in_the_settings_and_served_public(): void
    {
        $this->actingAsRole();

        Livewire::test('bladewright::site-css-panel')
            ->set('css', ".btn:hover { filter: brightness(1.1); }\n@media (max-width: 40rem) { .band { padding: 1rem; } }")
            ->call('save')
            ->assertToast('changes at once');

        $this->assertStringContainsString('.btn:hover', app(SiteCss::class)->get());
    }

    /** A guest fetches it: it dresses the published pages. */
    public function test_it_is_served_to_anybody(): void
    {
        app(SiteCss::class)->save(".btn:hover { filter: brightness(1.1); }\n@media (max-width: 40rem) { .band { padding: 1rem; } }");

        $this->get(route('bladewright.site.css'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/css; charset=utf-8')
            ->assertSee('.btn:hover', false)
            ->assertSee('@media (max-width: 40rem)', false);
    }

    /** The starter frames link it, versioned so caches never go stale. */
    public function test_a_published_page_links_it(): void
    {
        app(LayoutManager::class)->create('site', 'header');
        $pages = app(SitePages::class);
        $pages->publish($pages->create('Home', '', 'site'));

        $version = app(SiteCss::class)->version();

        $this->get('/')
            ->assertOk()
            ->assertSee('bladewright/site.css?v='.$version, false);

        // A change is a new URL.
        app(SiteCss::class)->save('body { background: #fafafa; }');

        $this->assertNotSame($version, app(SiteCss::class)->version());
    }

    /** Only the settings hand may write it. */
    public function test_writing_needs_the_settings_hand(): void
    {
        $this->actingAsRole('member');

        Livewire::test('bladewright::site-css-panel')
            ->set('css', 'body { display: none; }')
            ->call('save')
            ->assertForbidden();

        $this->assertSame('', app(SiteCss::class)->get());
    }
}
