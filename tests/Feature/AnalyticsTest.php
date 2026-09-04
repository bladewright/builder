<?php

namespace Bladewright\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Bladewright\Blocks\LayoutManager;
use Bladewright\Blocks\SitePages;
use Bladewright\Support\Analytics;
use Bladewright\Tests\TestCase;

/**
 * Google Analytics from one measurement id.
 *
 * **The id is data; the script is ours** — and a preview is not a visit,
 * so the admin's own screens carry nothing.
 */
class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function defineRoutes($router): void
    {
        parent::defineRoutes($router);
        $router->get('login', fn () => 'login')->name('login');
    }

    private function aPublishedPage(): \Bladewright\Models\Page
    {
        app(LayoutManager::class)->create('site', 'header');

        $pages = app(SitePages::class);
        $page = $pages->create('Home', '', 'site');
        $pages->publish($page);

        return $page;
    }

    /** Saved in the room, served on the page — and off again when emptied. */
    public function test_the_id_reaches_every_public_page(): void
    {
        $page = $this->aPublishedPage();

        // Nothing declared, nothing served.
        $this->get('/')->assertOk()->assertDontSee('googletagmanager', false);

        $this->actingAsRole();

        Livewire::test('bladewright::analytics-panel')
            ->set('code', 'AB12CD34EF')
            ->call('save')
            ->assertToast('Saved');

        $this->get('/')
            ->assertOk()
            ->assertSee('googletagmanager.com/gtag/js?id=G-AB12CD34EF', false)
            ->assertSee("gtag('config','G-AB12CD34EF')", false);

        // Emptied is off.
        Livewire::test('bladewright::analytics-panel')->set('code', '')->call('save');

        $this->get('/')->assertOk()->assertDontSee('googletagmanager', false);
    }

    /** **A preview is not a visit.** The admin's preview route stays silent. */
    public function test_the_admin_preview_carries_no_analytics(): void
    {
        $page = $this->aPublishedPage();
        app(Analytics::class)->save('G-AB12CD34EF');

        $this->actingAsRole();

        $this->get(route('bladewright.admin.pages.preview', $page))
            ->assertOk()
            ->assertDontSee('googletagmanager', false);
    }

    /** Only the shape Google hands out is kept — nothing pasted ever runs. */
    public function test_junk_is_refused(): void
    {
        $this->actingAsRole();

        Livewire::test('bladewright::analytics-panel')
            ->set('code', '"></script><script>alert(1)</script>')
            ->call('save')
            ->assertToast('does not look like');

        // The whole id pasted into the half-box is met halfway.
        Livewire::test('bladewright::analytics-panel')
            ->set('code', 'G-AB12CD34EF')
            ->call('save')
            ->assertSet('code', 'AB12CD34EF')
            ->assertToast('Saved');

        $this->assertSame('G-AB12CD34EF', app(Analytics::class)->get());

        app(Analytics::class)->save('');

    }

    /** The door stands in the settings hall. */
    public function test_the_room_is_behind_its_door(): void
    {
        $this->actingAsRole();

        $this->get('/bladewright/settings')
            ->assertOk()
            ->assertSee(route('bladewright.admin.settings.analytics'), false);

        $this->get('/bladewright/settings/analytics')
            ->assertOk()
            ->assertSee('measurement id', false);
    }
}
