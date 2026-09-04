<?php

namespace Bladewright\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Bladewright\Blocks\SitePages;
use Bladewright\Media\MediaLibrary;
use Bladewright\Models\User;
use Bladewright\Tests\TestCase;

/**
 * `bladewright:uninstall` — the heaviest thing the package can do, behind
 * the heaviest confirmation it has: **the site's name, typed.**
 */
class UninstallCommandTest extends TestCase
{
    use RefreshDatabase;

    /** Everything of ours goes; the one step left is composer remove. */
    public function test_it_removes_the_tables_and_the_accounts(): void
    {
        $this->app->make(SitePages::class)->create('Home', '');
        User::create(['email' => 'kanri@example.com', 'password' => 'secret-password']);

        $this->artisan('bladewright:uninstall')
            ->expectsConfirmation('Delete the uploaded files too?', 'no')
            ->expectsQuestion("Type the site's name (Laravel) to remove Bladewright's data", 'Laravel')
            ->expectsOutputToContain('composer remove bladewright/bladewright')
            ->assertSuccessful();

        $this->assertFalse(Schema::hasTable('bw_pages'));
        $this->assertFalse(Schema::hasTable('bw_users'));
    }

    /** Answering no keeps the uploaded files, and says where they are. */
    public function test_media_stays_unless_asked_for(): void
    {
        $this->app->make(SitePages::class)->create('Home', '');
        $file = $this->app->make(MediaLibrary::class)->store(UploadedFile::fake()->image('logo.png'));

        $this->artisan('bladewright:uninstall')
            ->expectsConfirmation('Delete the uploaded files too?', 'no')
            ->expectsQuestion("Type the site's name (Laravel) to remove Bladewright's data", 'Laravel')
            ->expectsOutputToContain('still at')
            ->assertSuccessful();

        $this->assertTrue($this->app->make(MediaLibrary::class)->disk()->exists($file->path));
    }

    /** Answering yes takes them, and the whole root with them. */
    public function test_media_goes_when_asked_for(): void
    {
        $this->app->make(SitePages::class)->create('Home', '');
        $file = $this->app->make(MediaLibrary::class)->store(UploadedFile::fake()->image('logo.png'));

        $this->artisan('bladewright:uninstall')
            ->expectsConfirmation('Delete the uploaded files too?', 'yes')
            ->expectsQuestion("Type the site's name (Laravel) to remove Bladewright's data", 'Laravel')
            ->assertSuccessful();

        $this->assertFalse($this->app->make(MediaLibrary::class)->disk()->exists($file->path));
    }

    /** A wrong name removes nothing. */
    public function test_a_wrong_name_removes_nothing(): void
    {
        $this->app->make(SitePages::class)->create('Home', '');

        $this->artisan('bladewright:uninstall')
            ->expectsConfirmation('Delete the uploaded files too?', 'no')
            ->expectsQuestion("Type the site's name (Laravel) to remove Bladewright's data", 'not-the-name')
            ->assertFailed();

        $this->assertTrue(Schema::hasTable('bw_pages'));
    }

    /** **The customer's own tables are not touched.** */
    public function test_the_customers_tables_stay(): void
    {
        Schema::create('shop_orders', fn ($table) => $table->id());
        $this->app->make(SitePages::class)->create('Home', '');

        $this->artisan('bladewright:uninstall')
            ->expectsConfirmation('Delete the uploaded files too?', 'no')
            ->expectsQuestion("Type the site's name (Laravel) to remove Bladewright's data", 'Laravel')
            ->assertSuccessful();

        $this->assertTrue(Schema::hasTable('shop_orders'));
    }
}
