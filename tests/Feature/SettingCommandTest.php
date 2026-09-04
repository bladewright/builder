<?php

namespace Bladewright\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Bladewright\Blocks\SitePages;
use Bladewright\Support\Settings;
use Bladewright\Tests\TestCase;

/**
 * `bladewright:setting`, in the core shape: **the site's language, and that
 * is all.** Everything else went back to where it already lived.
 */
class SettingCommandTest extends TestCase
{
    use RefreshDatabase;

    /** Left alone, the site follows the answer the Laravel developer gave. */
    public function test_new_pages_follow_the_application_by_default(): void
    {
        config()->set('app.locale', 'en');

        $page = $this->app->make(SitePages::class)->create('About', 'about');

        $this->assertSame('en', $page->locale);
    }

    /** Set, and pages are born in it — without a single file being written. */
    public function test_the_locale_can_be_set(): void
    {
        $this->artisan('bladewright:setting', ['--locale' => 'ja'])
            ->expectsOutputToContain('born in [ja]')
            ->assertSuccessful();

        $page = $this->app->make(SitePages::class)->create('会社概要', 'about');

        $this->assertSame('ja', $page->locale);
    }

    /** Existing pages keep the language they were made with. */
    public function test_existing_pages_keep_their_language(): void
    {
        config()->set('app.locale', 'en');
        $page = $this->app->make(SitePages::class)->create('About', 'about');

        $this->artisan('bladewright:setting', ['--locale' => 'ja'])->assertSuccessful();

        $this->assertSame('en', $page->refresh()->locale);
    }

    /** `--locale=""` follows the app again — being set is not a one-way door. */
    public function test_an_empty_locale_follows_the_app_again(): void
    {
        $this->app->make(Settings::class)->set('bladewright.locale', 'ja');

        $this->artisan('bladewright:setting', ['--locale' => ''])
            ->expectsOutputToContain('follow the application again')
            ->assertSuccessful();

        $this->assertNull($this->app->make(Settings::class)->get('bladewright.locale'));
    }

    /** The bare command says what it is now, and where the answer comes from. */
    public function test_the_bare_command_shows_the_answer_and_its_source(): void
    {
        config()->set('app.locale', 'en');

        $this->artisan('bladewright:setting')
            ->expectsOutputToContain('en')
            ->expectsOutputToContain('the application (app.locale)')
            ->assertSuccessful();

        $this->app->make(Settings::class)->set('bladewright.locale', 'ja');
        $this->app->make(Settings::class)->apply();

        $this->artisan('bladewright:setting')
            ->expectsOutputToContain('ja')
            ->expectsOutputToContain('this setting')
            ->assertSuccessful();
    }

    /** Something that is not a language code is refused, not stored. */
    public function test_a_broken_code_is_refused(): void
    {
        $this->artisan('bladewright:setting', ['--locale' => 'not a code'])
            ->assertFailed();

        $this->assertNull($this->app->make(Settings::class)->get('bladewright.locale'));
    }
}
