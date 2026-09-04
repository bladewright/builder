<?php

namespace Bladewright\Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Bladewright\Support\Settings;
use Bladewright\Tests\TestCase;

/**
 * Where a thing was decided has to be visible.
 *
 * **Neither `.env` nor a config file is rewritten.** They are overridden at
 * boot, so the file can look right while the behaviour differs. Whatever
 * overrode it is responsible for saying so.
 */
class ConfigVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['bladewright.settings.allow' => array_merge(
            config('bladewright.settings.allow', []),
            ['app.name'],
        )]);
    }

    public function test_about_says_what_is_overridden(): void
    {
        $this->artisan('about')
            ->expectsOutputToContain('Bladewright')
            ->assertSuccessful();

        $this->app->make(Settings::class)->set('app.name', '株式会社サンプル');
        $this->app->make(Settings::class)->apply();

        $this->artisan('about')
            ->expectsOutputToContain('app.name')
            ->assertSuccessful();
    }

    /**
     * Say which database the site's contents live in.
     *
     * So nobody believes they are in a separate database while they share one.
     * Sharing is the default, and then it says that `migrate:fresh` takes them.
     */
    public function test_about_says_which_database_holds_the_site(): void
    {
        $this->artisan('about')
            ->expectsOutputToContain('same as the app')
            ->assertSuccessful();

        config(['bladewright.database.connection' => 'testing']);

        $this->artisan('about')
            ->expectsOutputToContain('separate from the app')
            ->assertSuccessful();
    }

    /** With nothing overridden it says so, which settles a suspicion at once. */
    public function test_about_says_none_when_nothing_is_overridden(): void
    {
        $this->artisan('about')
            ->expectsOutputToContain('none')
            ->assertSuccessful();
    }

    /** The screen shows the config file's value beside it too. */
    public function test_the_screen_shows_the_file_value(): void
    {
        $user = new GenericUser(['id' => 1]);
        $this->actingAs($user);

        $this->app->make(Settings::class)->set('app.name', '株式会社サンプル');
        $this->app->make(Settings::class)->apply();

        Livewire::test('bladewright::settings-panel')
            ->assertSee('The config file (.env) says Laravel', false);
    }

    /**
     * A setting that was saved and does nothing is never ignored quietly.
     *
     * **It happened:** `app.timezone` came off the allow list, the
     * `published_at` values written in Asia/Tokyo were read as UTC, and
     * published pages became "not published yet" and answered 404.
     */
    public function test_saved_but_not_allowed_settings_are_surfaced(): void
    {
        $this->app->make(Settings::class)->set('app.name', '株式会社サンプル');

        // Take it off the allow list — the same as a customer narrowing it.
        $shipped = require __DIR__.'/../../config/bladewright.php';
        config(['bladewright.settings.allow' => $shipped['settings']['allow']]);

        $this->assertSame(['app.name' => '株式会社サンプル'], $this->app->make(Settings::class)->ignored());

        $this->artisan('about')
            ->expectsOutputToContain('app.name')
            ->assertSuccessful();
    }
}
