<?php

namespace Bladewright\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Bladewright\Media\MediaLibrary;
use Bladewright\Tests\TestCase;

/**
 * The media command, in the core shape: **list, search, upload, delete.**
 *
 * One noun, the verbs as options, everything addressed by name — and
 * deleting always warns and asks.
 */
class MediaCommandTest extends TestCase
{
    use RefreshDatabase;

    /** A 1×1 PNG on disk, to upload from. */
    private function localFile(string $name = 'logo.png'): string
    {
        $source = $this->siteRoot.'/'.$name;

        file_put_contents($source, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        ));

        return $source;
    }

    public function test_a_file_can_be_uploaded_into_a_folder(): void
    {
        $this->artisan('bladewright:media', [
            '--upload' => 'brochures/logo.png',
            '--from' => $this->localFile(),
        ])->assertSuccessful();

        $files = $this->app->make(MediaLibrary::class)->everything();

        $this->assertCount(1, $files);
        $this->assertSame('logo.png', $files->first()->name);
        $this->assertStringContainsString('/brochures/', $files->first()->path);
    }

    /** Without a source there is nothing to upload, and it says so. */
    public function test_uploading_needs_a_source(): void
    {
        $this->artisan('bladewright:media', ['--upload' => 'logo.png'])
            ->expectsOutputToContain('--from')
            ->assertFailed();
    }

    /** The bare command lists everything, whichever folder it is in. */
    public function test_the_whole_library_is_listed(): void
    {
        $media = $this->app->make(MediaLibrary::class);
        $media->store(UploadedFile::fake()->image('hero.png'));
        $media->store(UploadedFile::fake()->image('price.png'), 'brochures');

        $this->artisan('bladewright:media')
            ->expectsOutputToContain('hero.png')
            ->expectsOutputToContain('price.png')
            ->assertSuccessful();
    }

    /** A search matches part of a file name. */
    public function test_search_matches_part_of_a_name(): void
    {
        $media = $this->app->make(MediaLibrary::class);
        $media->store(UploadedFile::fake()->image('hero.png'));
        $media->store(UploadedFile::fake()->image('price.png'));

        $this->artisan('bladewright:media', ['--search' => 'her'])
            ->expectsOutputToContain('hero.png')
            ->doesntExpectOutputToContain('price.png')
            ->assertSuccessful();
    }

    /** A path (it has slashes) has to match exactly. */
    public function test_search_by_path_is_exact(): void
    {
        $file = $this->app->make(MediaLibrary::class)->store(UploadedFile::fake()->image('hero.png'));

        $this->artisan('bladewright:media', ['--search' => $file->path])
            ->expectsOutputToContain('hero.png')
            ->assertSuccessful();

        $this->artisan('bladewright:media', ['--search' => substr($file->path, 0, -1)])
            ->expectsOutputToContain('Nothing matches')
            ->assertSuccessful();
    }

    /** Deleting warns, asks, and only then removes. */
    public function test_deleting_asks_first(): void
    {
        $file = $this->app->make(MediaLibrary::class)->store(UploadedFile::fake()->image('hero.png'));

        $this->artisan('bladewright:media', ['--delete' => $file->path])
            ->expectsConfirmation("Delete [{$file->path}]?", 'yes')
            ->assertSuccessful();

        $this->assertCount(0, $this->app->make(MediaLibrary::class)->everything());
    }

    /** Answering no leaves the file where it is. */
    public function test_declining_deletes_nothing(): void
    {
        $file = $this->app->make(MediaLibrary::class)->store(UploadedFile::fake()->image('hero.png'));

        $this->artisan('bladewright:media', ['--delete' => $file->path])
            ->expectsConfirmation("Delete [{$file->path}]?", 'no')
            ->expectsOutputToContain('Nothing was deleted')
            ->assertSuccessful();

        $this->assertCount(1, $this->app->make(MediaLibrary::class)->everything());
    }

    /** A path that is not in the library is refused, not silently skipped. */
    public function test_deleting_something_absent_fails(): void
    {
        $this->artisan('bladewright:media', ['--delete' => 'bw/nope.png'])
            ->assertFailed();
    }
}
