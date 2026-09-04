<?php

namespace Bladewright\Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Bladewright\Tests\TestCase;

/**
 * The admin is guarded by permissions.
 *
 * The default middleware is web → auth → can:bladewright.access-admin.
 * **Signing in is not enough**; only somebody holding a role gets in.
 */
class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function defineRoutes($router): void
    {
        parent::defineRoutes($router);

        // Where the auth middleware redirects — the host application's own.
        $router->get('login', fn () => 'login')->name('login');
    }

    private function user(int $id = 1): GenericUser
    {
        return new GenericUser(['id' => $id]);
    }

    /**
     * **Even with a login on the host, the admin's login stays ours.**
     * A destination that changes with the setup can be neither explained nor
     * investigated.
     */
    public function test_a_guest_is_sent_to_our_login_screen(): void
    {
        $this->get('/bladewright/media')->assertRedirect('/bladewright/login');
    }

    /**
     * **Signing in is enough, for now.**
     *
     * Roles are being designed again, so the door is open to anybody the host
     * application can sign in. When they come back, this test changes with
     * them.
     */
    public function test_anybody_signed_in_can_open_the_admin(): void
    {
        $this->actingAs($this->user())->get('/bladewright/media')->assertOk();
    }

    /** A screen the host has closed is still closed. */
    public function test_an_ability_the_host_closed_is_refused(): void
    {
        \Illuminate\Support\Facades\Gate::define(
            \Bladewright\Access\Abilities::gate(\Bladewright\Access\Abilities::MANAGE_SETTINGS),
            fn () => false,
        );

        $this->actingAs($this->user())->get('/bladewright/settings')->assertForbidden();
    }

    /** The CSS alone is outside authentication; the login screen has to load it. */
    public function test_the_stylesheet_is_reachable_without_logging_in(): void
    {
        $this->get('/bladewright/assets/bladewright.css')->assertOk();
    }

    /** The public site is visible as ever, of course. */
    public function test_the_public_site_is_unaffected(): void
    {
        $pages = $this->app->make(\Bladewright\Blocks\SitePages::class);
        $block = $this->app->make(\Bladewright\Blocks\BlockManager::class)->create('about-copy', 'markdown');
        $block->forceFill(['data' => ['body' => '# 会社概要']])->save();
        $section = $this->app->make(\Bladewright\Blocks\StructureManager::class)->create('about-body', 'section');
        $this->app->make(\Bladewright\Blocks\StructureManager::class)->insertBlock($section, $block);
        $page = $pages->create('About', 'about');
        $pages->insertComponent($page, $section);
        $pages->publish($page);

        $this->get('/about')->assertOk()->assertSee('会社概要', false);
    }
}
