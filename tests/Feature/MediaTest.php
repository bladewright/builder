<?php

namespace Bladewright\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Bladewright\Media\MediaLibrary;
use Bladewright\Tests\TestCase;

/**
 * The files that were uploaded.
 *
 * **Storage is the truth; the database keeps no copy.** A copy always drifts,
 * and then something has to detect the drift. Put the contents' hash and the
 * original name in the path and no table is needed at all.
 *
 * And **this alone cannot be restored from the database.** Pages and blocks
 * are rebuilt from revisions; an image's contents exist nowhere else.
 */
class MediaTest extends TestCase
{
    use RefreshDatabase;

    private function library(): MediaLibrary
    {
        return $this->app->make(MediaLibrary::class);
    }

    private function image(string $name = 'hero.png'): UploadedFile
    {
        return UploadedFile::fake()->image($name, 40, 30);
    }

    /** **There is no table.** */
    public function test_there_is_no_media_table(): void
    {
        $this->assertFalse(Schema::hasTable('bw_media'));
    }

    public function test_an_upload_is_stored_on_the_configured_disk(): void
    {
        $file = $this->library()->store($this->image());

        $this->assertTrue($file->exists());
        $this->assertSame('bladewright', $file->disk);
        $this->assertTrue(Storage::disk('bladewright')->exists($file->path));
        $this->assertStringStartsWith('image/', $file->mime);
    }

    /** **The path carries the original name.** With no database, it is still there. */
    public function test_the_original_name_lives_in_the_path(): void
    {
        $file = $this->library()->store($this->image('会社ロゴ.png'));

        $this->assertStringEndsWith('.png', $file->path);
        $this->assertSame(basename($file->path), $file->name);
    }

    /** The contents' fingerprint decides the place. **Different contents never share one.** */
    public function test_the_path_is_made_of_the_content_hash(): void
    {
        $file = $this->library()->store(UploadedFile::fake()->createWithContent('a.png', 'PNGDATA'));
        $hash = hash('sha256', 'PNGDATA');

        $this->assertStringContainsString(substr($hash, 0, 2).'/'.substr($hash, 2, 2).'/'.$hash, $file->path);
    }

    /** The same contents do not multiply. Upload the same image ten times and there is one. */
    public function test_the_same_file_is_stored_once(): void
    {
        $first = $this->library()->store(UploadedFile::fake()->createWithContent('a.png', 'PNGDATA'));
        $second = $this->library()->store(UploadedFile::fake()->createWithContent('a.png', 'PNGDATA'));

        $this->assertSame($first->path, $second->path);
        $this->assertCount(1, $this->library()->all());
    }

    /** The list reads the disk. No database is asked. */
    public function test_the_list_comes_from_the_disk(): void
    {
        // **Different contents, different file.** Counted by contents, not by name.
        $this->library()->store(UploadedFile::fake()->createWithContent('one.png', 'ONE'));
        $this->library()->store(UploadedFile::fake()->createWithContent('two.png', 'TWO'));

        $this->assertCount(2, $this->library()->all());

        // Gone from the disk, gone from the list — there is nothing to drift.
        Storage::disk('bladewright')->deleteDirectory('bw');

        $this->assertCount(0, $this->library()->all());
    }

    /** Anything too big is refused. */
    public function test_an_oversized_file_is_refused(): void
    {
        config()->set('bladewright.media.max_size', 1024);

        $this->expectException(RuntimeException::class);

        $this->library()->store(UploadedFile::fake()->create('big.png', 4));
    }

    /** A type that is not accepted is refused. */
    public function test_a_disallowed_type_is_refused(): void
    {
        config()->set('bladewright.media.mimes', ['image/png']);

        $this->expectException(RuntimeException::class);

        $this->library()->store(UploadedFile::fake()->create('note.txt', 1, 'text/plain'));
    }

    /** Nothing outside where we put things is touched. */
    public function test_it_only_touches_its_own_folder(): void
    {
        Storage::disk('bladewright')->put('someone-else.txt', 'keep me');

        $this->library()->delete('someone-else.txt');
        $this->library()->delete('../escape.txt');

        $this->assertTrue(Storage::disk('bladewright')->exists('someone-else.txt'));
        $this->assertNull($this->library()->find('someone-else.txt'));
    }

    /** Report what is referenced and gone. **It cannot be restored, so it should be noticed early.** */
    public function test_missing_files_are_reported(): void
    {
        $file = $this->library()->store($this->image());

        $this->assertSame([], $this->library()->missing([$file->path]));

        Storage::disk('bladewright')->delete($file->path);

        $this->assertSame([$file->path], $this->library()->missing([$file->path]));
    }

    /** A disk with URLs of its own is used (our paths stay off the customer's pages). */
    public function test_a_public_disk_serves_the_file_directly(): void
    {
        config()->set('filesystems.disks.bladewright.url', 'https://cdn.example.com');

        $file = $this->library()->store($this->image());

        $this->assertStringStartsWith('https://cdn.example.com', $file->url());
    }

    /** **No host name is baked in.** The saver's environment left in a page cannot be read in production. */
    public function test_the_url_does_not_carry_a_hostname(): void
    {
        config()->set('filesystems.disks.bladewright.url', null);

        $file = $this->library()->store($this->image());

        $this->assertStringStartsWith('/', $file->url());
        $this->assertStringNotContainsString('http', $file->url());
    }

    /** Only a disk with no URLs of its own is served by us. */
    public function test_a_private_disk_falls_back_to_our_route(): void
    {
        config()->set('filesystems.disks.bladewright.url', null);

        $file = $this->library()->store($this->image());

        $this->assertStringContainsString('/bladewright/media/', $file->url());

        $this->get($file->url())->assertOk();
    }

    /** Files outside are not served. */
    public function test_it_does_not_serve_files_outside_its_folder(): void
    {
        Storage::disk('bladewright')->put('secret.txt', 'nope');

        $this->get(route('bladewright.media', ['path' => 'secret.txt']))->assertNotFound();
    }

    /** An environment that cannot be written to is known before anyone starts. */
    public function test_it_can_tell_whether_the_disk_is_writable(): void
    {
        $this->assertTrue($this->library()->isWritable());
    }

    /** Folders can divide them. **A name in Japanese stays as it is.** */
    public function test_files_can_live_in_folders(): void
    {
        $library = $this->library();

        $library->makeFolder('', '会社案内');
        $library->store($this->image('ロゴ.png'), '会社案内');
        $library->store(UploadedFile::fake()->createWithContent('別.png', 'OTHER'));

        $this->assertSame(['会社案内'], $library->folders());
        $this->assertCount(1, $library->all('会社案内'));

        // **What is nested is not mixed in.** At the top is the one that was not moved.
        $this->assertCount(1, $library->all());
    }

    /** A folder can hold a folder. */
    public function test_folders_can_nest(): void
    {
        $library = $this->library();

        $library->makeFolder('', '製品');
        $library->makeFolder('製品', '2026年');
        $library->store($this->image('写真.png'), '製品/2026年');

        $this->assertSame(['2026年'], $library->folders('製品'));
        $this->assertCount(0, $library->all('製品'));
        $this->assertCount(1, $library->all('製品/2026年'));
    }

    /**
     * **Nothing can be moved.**
     *
     * The path identifies the contents and a page holds that path, so moving
     * it makes the image vanish quietly from the pages that refer to it. The
     * folder is chosen when it is uploaded.
     */
    public function test_there_is_no_way_to_move_a_file(): void
    {
        $this->assertFalse(method_exists($this->library(), 'move'));
    }

    /** **A folder with anything in it cannot be deleted.** The pages referring to it would break quietly. */
    public function test_a_folder_with_files_cannot_be_deleted(): void
    {
        $library = $this->library();
        $library->makeFolder('', '会社案内');
        $library->store($this->image('ロゴ.png'), '会社案内');

        $this->expectException(RuntimeException::class);

        $library->deleteFolder('会社案内');
    }

    /** In a folder's name too, characters that are dangerous in a path are dropped. */
    public function test_a_folder_name_cannot_escape(): void
    {
        $library = $this->library();

        $library->makeFolder('', '../../逃げる');

        $this->assertSame(['逃げる'], $library->folders());
    }
}
