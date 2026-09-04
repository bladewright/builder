<?php

namespace Bladewright\Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Bladewright\Support\Settings;
use Bladewright\Tests\TestCase;

/**
 * The settings screen.
 *
 * It only overrides `config`; **no config file is changed.**
 *
 * The permissions screen went with the roles, and comes back when they are
 * designed again.
 */
class AdminSettingsScreenTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Open a host key.
     *
     * **By default not one is touched**, so a test of the machinery opens one
     * here — the same thing a customer does in `config/bladewright.php`.
     */
    protected function allowHostKeys(array $keys = ['app.name', 'app.timezone', 'app.locale', 'app.fallback_locale', 'mail.*', 'filesystems.disks.*']): void
    {
        config(['bladewright.settings.allow' => array_merge(
            config('bladewright.settings.allow', []),
            $keys,
        )]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->allowHostKeys();
    }

    protected function defineRoutes($router): void
    {
        parent::defineRoutes($router);
        $router->get('login', fn () => 'login')->name('login');
    }


    public function test_a_developer_can_open_the_settings_screen(): void
    {
        $this->actingAsRole('developer');

        // **The index is a hall of doors** — each setting works in a room
        // of its own, and the hall only says where they are.
        $this->get('/bladewright/settings')
            ->assertOk()
            ->assertSee(route('bladewright.admin.settings.colours'), false)
            ->assertSee(route('bladewright.admin.settings.stylesheet'), false)
            ->assertSee(route('bladewright.admin.settings.application'), false);

        $this->get('/bladewright/settings/application')
            ->assertOk()
            ->assertSee('Time zone', false);

        $this->get('/bladewright/settings/colours')->assertOk()->assertSee('accent', false);
        $this->get('/bladewright/settings/stylesheet')->assertOk()->assertSee('Stylesheet', false);
    }

    /** A member does not touch the settings. */
    public function test_a_member_cannot_open_the_settings_screen(): void
    {
        $this->actingAsRole('member');

        $this->get('/bladewright/settings')->assertForbidden();
    }

    /** A changed setting reaches Laravel. */
    public function test_saving_changes_the_configuration(): void
    {
        $this->actingAsRole('developer');

        $panel = Livewire::test('bladewright::settings-panel');
        $keys = array_column($panel->get('values'), 'key');

        $panel->set('values.'.array_search('app.name', $keys, true).'.value', '株式会社サンプル')
            ->set('values.'.array_search('app.timezone', $keys, true).'.value', 'Asia/Tokyo')
            ->call('save')
            ->assertToast('Saved');

        $this->assertSame('株式会社サンプル', config('app.name'));
        $this->assertSame('Asia/Tokyo', config('app.timezone'));
        $this->assertSame('Asia/Tokyo', date_default_timezone_get());
    }

    /** Settings that hold secrets are not on this screen. */
    public function test_structured_settings_are_not_offered_here(): void
    {
        $this->actingAsRole('developer');

        $keys = Livewire::test('bladewright::settings-panel')->instance()->keys();

        $this->assertContains('app.timezone', $keys);
        $this->assertNotContains('filesystems.disks.*', $keys);
        $this->assertNotContains('mail.*', $keys);
    }

    public function test_a_setting_can_be_reset_to_the_file_default(): void
    {
        $this->actingAsRole('developer');
        $this->app->make(Settings::class)->set('app.name', '一時的な名前');

        Livewire::test('bladewright::settings-panel')->call('resetToDefault', 'app.name');

        $this->assertNull($this->app->make(Settings::class)->get('app.name'));
    }

    public function test_a_member_cannot_save_settings_even_by_calling_the_action(): void
    {
        $this->actingAsRole('member');

        $panel = Livewire::test('bladewright::settings-panel');
        $keys = array_column($panel->get('values'), 'key');

        $panel->set('values.'.array_search('app.name', $keys, true).'.value', 'のっとり')
            ->call('save')
            ->assertForbidden();

        $this->assertNotSame('のっとり', config('app.name'));
    }

}
