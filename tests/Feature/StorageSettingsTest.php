<?php

namespace Bladewright\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Bladewright\Support\Settings;
use Bladewright\Tests\TestCase;

/**
 * Setting storage's connection from a screen.
 *
 * **Exactly the machinery app.timezone uses.** The key is a path into config
 * and it is overridden at boot; the effect is that of editing
 * config/filesystems.php.
 */
class StorageSettingsTest extends TestCase
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

    private function settings(): Settings
    {
        return $this->app->make(Settings::class);
    }

    public function test_a_disk_can_be_added_from_settings(): void
    {
        $root = $this->siteRoot.'/media';

        $this->settings()->set('filesystems.disks.media', [
            'driver' => 'local',
            'root' => $root,
        ]);

        Storage::disk('media')->put('hello.txt', 'こんにちは');

        $this->assertFileExists($root.'/hello.txt');
        $this->assertSame('こんにちは', Storage::disk('media')->get('hello.txt'));
    }

    /** The override at boot takes effect, and survives a reload. */
    public function test_the_disk_survives_a_reload(): void
    {
        $this->settings()->set('filesystems.disks.media', [
            'driver' => 'local',
            'root' => $this->siteRoot.'/media',
        ]);

        config(['filesystems.disks.media' => null]);
        $this->settings()->apply();

        $this->assertSame('local', config('filesystems.disks.media.driver'));
    }

    /** An upload destination the host already uses is not switched from here. */
    public function test_the_host_default_disk_is_not_writable_by_default(): void
    {
        $this->assertFalse($this->settings()->isWritable('filesystems.default'));
        $this->assertTrue($this->settings()->isWritable('filesystems.disks.media'));
    }

    /** S3-compatible storage (R2, MinIO) is configured the same way. */
    public function test_an_s3_compatible_disk_can_be_configured(): void
    {
        $this->settings()->set('filesystems.disks.r2', [
            'driver' => 's3',
            'key' => 'AKIAEXAMPLE',
            'secret' => 'shhh',
            'region' => 'auto',
            'bucket' => 'site-media',
            'endpoint' => 'https://example.r2.cloudflarestorage.com',
        ]);

        $this->assertSame('s3', config('filesystems.disks.r2.driver'));
        $this->assertSame('site-media', config('filesystems.disks.r2.bucket'));
    }

    public function test_a_disk_can_be_configured_through_the_settings(): void
    {
        $this->app->make(\Bladewright\Support\Settings::class)->set(
            'filesystems.disks.media',
            json_encode(['driver' => 'local', 'root' => $this->siteRoot.'/media']),
        );

        $this->assertNotNull(config('filesystems.disks.media'));
    }
}
