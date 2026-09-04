<?php

namespace Bladewright\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Bladewright\Support\Settings;
use Bladewright\Tests\TestCase;

/**
 * Changing Laravel's configuration from the admin (the skeleton of a GUI for Laravel).
 *
 * **No file is rewritten.** config is overridden at boot, and to somebody
 * working from the GUI it is indistinguishable from editing config/app.php.
 * v3 did a string replacement on .env, which did nothing in a production with
 * config:cache, could not be written on a read-only cloud container, and
 * quietly did nothing at all when the line it wanted was missing.
 */
class SettingsTest extends TestCase
{
    use RefreshDatabase;

    private function settings(): Settings
    {
        return $this->app->make(Settings::class);
    }

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

    /**
     * **By default not one of the host's settings is touched.**
     *
     * A package meant to live alongside an application has no business
     * changing its behaviour merely by being installed. Otherwise editing "the
     * site name" also changes the sender's name on the host's email, and
     * config/app.php shows no reason why.
     */
    public function test_host_settings_are_left_alone_by_default(): void
    {
        // Back to the packaged defaults, closing what setUp opened.
        $shipped = require __DIR__.'/../../config/bladewright.php';
        config(['bladewright.settings.allow' => $shipped['settings']['allow']]);

        $settings = $this->settings();

        $this->assertFalse($settings->isWritable('app.name'));
        $this->assertFalse($settings->isWritable('mail.default'));
        $this->assertFalse($settings->isWritable('filesystems.disks.s3'));

        // Our own disk stays writable (S3 and friends are connected there).
        $this->assertTrue($settings->isWritable('filesystems.disks.bladewright'));
    }

    public function test_a_setting_overrides_the_config_file(): void
    {
        $this->assertSame('Laravel', config('app.name'));

        $this->settings()->set('app.name', '株式会社サンプル');

        $this->assertSame('株式会社サンプル', config('app.name'));
    }

    /**
     * date_default_timezone_set runs second in the boot, before the providers,
     * but it is a PHP global setting so overriding it later works.
     */
    public function test_changing_the_timezone_actually_moves_the_clock(): void
    {
        $before = now()->format('T');

        $this->settings()->set('app.timezone', 'Asia/Tokyo');

        $this->assertSame('Asia/Tokyo', config('app.timezone'));
        $this->assertSame('Asia/Tokyo', date_default_timezone_get());
        $this->assertSame('JST', now()->format('T'));
        $this->assertNotSame($before, now()->format('T'));
    }

    /** Changing config alone does nothing, so the translator is told as well. */
    public function test_changing_the_locale_reaches_the_translator(): void
    {
        $this->settings()->set('app.locale', 'ja');

        $this->assertSame('ja', config('app.locale'));
        $this->assertSame('ja', $this->app->getLocale());
    }

    /** A broken value does not take the application down. */
    public function test_an_invalid_timezone_is_ignored(): void
    {
        $this->settings()->set('app.timezone', 'Mars/Olympus');

        $this->assertNotSame('Mars/Olympus', date_default_timezone_get());
    }

    /**
     * Some things are never overridden, whatever the allow list says.
     * Change app.key and every piece of existing encrypted data is lost;
     * open app.debug in production and information leaks.
     */
    public function test_dangerous_keys_can_never_be_written(): void
    {
        config(['bladewright.settings.allow' => ['*', 'app.key', 'app.debug', 'database.default']]);

        foreach (['app.key', 'app.debug', 'app.env', 'database.default'] as $key) {
            $this->assertFalse($this->settings()->isWritable($key), "{$key} が書けてしまう");
        }
    }

    public function test_a_key_outside_the_allow_list_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->settings()->set('session.lifetime', 999);
    }

    public function test_wildcards_in_the_allow_list_work(): void
    {
        $this->assertTrue($this->settings()->isWritable('mail.from.address'));
        $this->assertFalse($this->settings()->isWritable('queue.default'));
    }

    /**
     * The override at boot takes effect.
     *
     * Really restarting would take the data with it (the database is in
     * memory), so the config file's values are put back and apply() is called,
     * which is the road the boot takes.
     */
    public function test_the_override_is_applied_at_boot(): void
    {
        $this->settings()->set('app.name', '株式会社サンプル');

        // Back to the state just after the config file was read.
        config(['app.name' => 'Laravel']);

        $this->settings()->apply();

        $this->assertSame('株式会社サンプル', config('app.name'));
    }

    /** Deleting it returns to the config file's default. */
    public function test_forgetting_falls_back_to_the_file(): void
    {
        $this->settings()->set('app.name', '株式会社サンプル');
        $this->settings()->forget('app.name');

        config(['app.name' => 'Laravel']);
        $this->settings()->apply();

        $this->assertSame('Laravel', config('app.name'));
    }

    /**
     * **It must not fall over when the database is unreadable.**
     * Throwing before the migrations, or during a database incident, takes the
     * whole site down.
     */
    public function test_a_missing_table_does_not_break_the_application(): void
    {
        Schema::drop('bw_settings');

        $this->assertSame([], $this->settings()->all());

        $this->settings()->apply();

        $this->get('/nothing-here')->assertNotFound();
    }

}
