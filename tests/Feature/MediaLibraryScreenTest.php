<?php

namespace Bladewright\Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Bladewright\Media\MediaLibrary;
use Bladewright\Models\Component;
use Bladewright\Tests\TestCase;

/**
 * The media library screen.
 *
 * As far as **uploading, then choosing one from a block and using it.**
 */
class MediaLibraryScreenTest extends TestCase
{
    use RefreshDatabase;

    protected function defineRoutes($router): void
    {
        parent::defineRoutes($router);
        $router->get('login', fn () => 'login')->name('login');
    }


    public function test_the_library_lists_what_has_been_uploaded(): void
    {
        $this->app->make(MediaLibrary::class)->store(UploadedFile::fake()->image('hero.png'));
        $this->actingAsRole('member');

        $this->get('/bladewright/media')
            ->assertOk()
            ->assertSee('hero.png', false);
    }

    /**
     * **A picker asked for pictures offers pictures.** A src field for an
     * image has no business offering films.
     */
    public function test_a_picker_offers_only_the_kind_asked_for(): void
    {
        $this->actingAsRole('member');
        $library = $this->app->make(MediaLibrary::class);
        $library->store(UploadedFile::fake()->image('hero.png'));
        $library->store(UploadedFile::fake()->create('clip.mp4', 200, 'video/mp4'));

        Livewire::test('bladewright::media-library', ['picking' => true, 'accept' => 'image'])
            ->assertSee('hero.png')
            ->assertDontSee('clip.mp4');

        Livewire::test('bladewright::media-library', ['picking' => true, 'accept' => 'video'])
            ->assertSee('clip.mp4')
            ->assertDontSee('hero.png');

        // Unasked, everything is offered.
        Livewire::test('bladewright::media-library', ['picking' => true])
            ->assertSee('hero.png')
            ->assertSee('clip.mp4');
    }

    public function test_a_guest_cannot_open_the_library(): void
    {
        $this->get('/bladewright/media')->assertRedirect('/bladewright/login');
    }

    public function test_uploading_through_the_screen_stores_the_file(): void
    {
        $this->actingAsRole('member');

        Livewire::test('bladewright::media-library')
            ->set('uploads', [UploadedFile::fake()->image('logo.png', 20, 20)])
            ->assertToast('Uploaded');

        $files = $this->app->make(MediaLibrary::class)->all();

        $this->assertCount(1, $files);
        $this->assertSame('logo.png', $files->first()->name);
        $this->assertTrue($files->first()->exists());
    }

    public function test_an_oversized_upload_is_reported_not_stored(): void
    {
        config(['bladewright.media.max_size' => 100]);
        $this->actingAsRole('member');

        Livewire::test('bladewright::media-library')
            ->set('uploads', [UploadedFile::fake()->create('big.png', 50, 'image/png')])
            ->assertToast('too large');

        $this->assertCount(0, $this->app->make(MediaLibrary::class)->all());
    }

    /**
     * Delete it and it really goes.
     *
     * **Merely hiding it from the list is not on.** Holding that state would
     * need a database, which contradicts storage being the truth. The screen
     * says it will disappear from the pages that use it.
     */
    public function test_removing_a_file_deletes_it(): void
    {
        $library = $this->app->make(MediaLibrary::class);
        $file = $library->store(UploadedFile::fake()->image('old.png'));
        $this->actingAsRole('member');

        Livewire::test('bladewright::media-library')
            ->call('remove', $file->path)
            ->assertToast('Deleted');

        $this->assertFalse($file->exists());
        $this->assertCount(0, $library->all());
    }

    /** What reaches a block is a path, not an id. */
    public function test_choosing_hands_over_the_path(): void
    {
        $file = $this->app->make(MediaLibrary::class)->store(UploadedFile::fake()->image('pick.png'));
        $this->actingAsRole('member');

        Livewire::test('bladewright::media-library', ['picking' => true])
            ->call('choose', $file->path)
            ->assertDispatched('bw-media-selected');
    }


    /** Folders and files stand in one list. */
    public function test_folders_and_files_are_listed_side_by_side(): void
    {
        $library = $this->app->make(MediaLibrary::class);
        $library->makeFolder('', '会社案内');
        $library->store(UploadedFile::fake()->image('banner.png'));
        $this->actingAsRole('member');

        $this->get('/bladewright/media')
            ->assertOk()
            ->assertSee('会社案内', false)
            ->assertSee('banner.png', false);
    }

    /** Choosing one opens the drawer on the right, with the file in it. */
    public function test_opening_a_file_shows_it_in_the_drawer(): void
    {
        $file = $this->app->make(MediaLibrary::class)->store(UploadedFile::fake()->image('hero.png', 20, 20));
        $this->actingAsRole('member');

        Livewire::test('bladewright::media-library')
            ->call('select', $file->path)
            ->assertSet('drawerOpen', true)
            ->assertSee('hero.png', false)
            ->assertSee($file->url(), false)
            ->assertSee('Copy URL', false);
    }

    /**
     * The chosen file is remembered even when it closes.
     *
     * **Forget it and the contents vanish first, halfway through the closing.**
     */
    public function test_closing_the_drawer_keeps_what_was_open(): void
    {
        $file = $this->app->make(MediaLibrary::class)->store(UploadedFile::fake()->image('hero.png'));
        $this->actingAsRole('member');

        Livewire::test('bladewright::media-library')
            ->call('select', $file->path)
            ->call('closeDrawer')
            ->assertSet('drawerOpen', false)
            ->assertSet('selected', $file->path);
    }

    /**
     * **The little window closes when the folder is made.**
     *
     * Whether it is open is the browser's to keep, so the server has to say
     * when it is done — and the browser has to be listening. It was not: the
     * listener was registered from `DOMContentLoaded`, by which time
     * `livewire:initialized` had already fired, so the window stayed open
     * while the toast beside it appeared.
     */
    public function test_making_a_folder_says_to_close_the_window(): void
    {
        $this->actingAsRole();

        Livewire::test('bladewright::media-library')
            ->set('newFolder', 'Brochures')
            ->call('createFolder')
            ->assertDispatched('bw-close-modal');
    }

    /** Deleting a folder goes up one — nobody is left standing where it was. */
    public function test_deleting_the_folder_walks_back_up(): void
    {
        $this->app->make(MediaLibrary::class)->makeFolder('', '古い');
        $this->actingAsRole('member');

        Livewire::test('bladewright::media-library')
            ->call('open', '古い')
            ->call('removeFolder', '古い')
            ->assertSet('folder', '')
            ->assertToast('Folder deleted');
    }

    /**
     * **A library grows; a screen does not.**
     *
     * Five hundred tiles and five hundred images in one screen is a slow page
     * and a lot of bytes, so it is paged.
     */
    public function test_the_files_are_paged(): void
    {
        $this->actingAsRole();

        $library = $this->app->make(MediaLibrary::class);

        foreach (range(1, 5) as $n) {
            $library->store(UploadedFile::fake()->image("shot-{$n}.png", 10 + $n, 10));
        }

        $screen = Livewire::test('bladewright::media-library')->set('perPage', 2);

        $this->assertSame(2, $screen->instance()->media()->count());
        $this->assertSame(5, $screen->instance()->media()->total());

        // The last page holds what is left.
        $screen->call('nextPage')->call('nextPage');

        $this->assertSame(1, $screen->instance()->media()->count());
    }

    /** Moving to another folder starts at its first page, not somebody else's third. */
    public function test_opening_a_folder_goes_back_to_the_first_page(): void
    {
        $this->actingAsRole();

        $this->app->make(MediaLibrary::class)->makeFolder('', 'brochures');

        $screen = Livewire::test('bladewright::media-library')
            ->set('perPage', 1)
            ->call('nextPage')
            ->call('open', 'brochures');

        $this->assertSame(1, $screen->instance()->media()->currentPage());
    }

    /** Without permission, nothing is uploaded. */
    public function test_a_user_without_edit_content_cannot_upload(): void
    {

        $user = new GenericUser(['id' => 2]);
        $this->withoutAbility(\Bladewright\Access\Abilities::EDIT_CONTENT);
        $this->actingAs($user);

        Livewire::test('bladewright::media-library')
            ->set('uploads', [UploadedFile::fake()->image('x.png')])
            ->assertForbidden();
    }
}
