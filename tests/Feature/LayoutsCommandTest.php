<?php

namespace Bladewright\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Bladewright\Models\Layout;
use Bladewright\Tests\TestCase;

/**
 * `bladewright:layouts` — where the parts of a page sit. Born from a recipe,
 * the site's own from then on.
 */
class LayoutsCommandTest extends TestCase
{
    use RefreshDatabase;

    /** The default is the navigation across the top, in the site's framework. */
    public function test_a_layout_is_born_from_the_default_recipe(): void
    {
        $this->artisan('bladewright:layouts', ['--create' => 'site'])
            ->expectsOutputToContain('header')
            ->assertSuccessful();

        $layout = Layout::query()->firstOrFail();

        $this->assertNotNull($layout->uuid);
        // **No link is baked in**: the head asks the site what it loads.
        $this->assertStringContainsString('@bwframework', $layout->content);
        $this->assertStringNotContainsString('cdn', $layout->content);
        $this->assertStringContainsString('{{ $slot }}', $layout->content);
        $this->assertStringNotContainsString('<aside', $layout->content);
    }

    /** A plain site's frame carries its own tokens, and a sidebar brings an aside. */
    public function test_the_sites_framework_and_a_sidebar_shape_the_frame(): void
    {
        app(\Bladewright\Support\Framework::class)->save('Plain CSS');

        $this->artisan('bladewright:layouts', ['--create' => 'docs', '--type' => 'sidebar'])
            ->assertSuccessful();

        $layout = Layout::query()->firstOrFail();

        $this->assertStringNotContainsString('bootstrap', $layout->content);
        $this->assertStringContainsString('--ink', $layout->content);
        $this->assertStringContainsString('<aside', $layout->content);
    }

    /** A recipe that does not exist is refused, not guessed at. */
    public function test_an_unknown_recipe_is_refused(): void
    {
        $this->artisan('bladewright:layouts', ['--create' => 'site', '--type' => 'floating'])
            ->expectsOutputToContain('not a layout type')
            ->assertFailed();
    }

    /** The copy carries the frame and is its own from birth. */
    public function test_a_copy_carries_the_frame(): void
    {
        $this->artisan('bladewright:layouts', ['--create' => 'site'])->assertSuccessful();

        $this->artisan('bladewright:layouts', ['--copy' => 'site', '--to' => 'campaign'])
            ->assertSuccessful();

        $copy = Layout::query()->where('name', 'campaign')->firstOrFail();

        $this->assertSame(Layout::query()->where('name', 'site')->first()->content, $copy->content);
    }

    /** Renaming keeps the uuid — whatever points at it still points at it. */
    public function test_renaming_keeps_the_uuid(): void
    {
        $this->artisan('bladewright:layouts', ['--create' => 'site'])->assertSuccessful();

        $layout = Layout::query()->firstOrFail();
        $uuid = $layout->uuid;

        $this->artisan('bladewright:layouts', ['--rename' => 'site', '--to' => 'frame'])
            ->assertSuccessful();

        $this->assertSame($uuid, $layout->refresh()->uuid);
        $this->assertSame('frame', $layout->name);
    }

    /** Deleting warns and asks. */
    public function test_deleting_asks_first(): void
    {
        $this->artisan('bladewright:layouts', ['--create' => 'site'])->assertSuccessful();

        $this->artisan('bladewright:layouts', ['--delete' => 'site'])
            ->expectsConfirmation('Delete [site]?', 'no')
            ->assertSuccessful();

        $this->assertSame(1, Layout::query()->count());

        $this->artisan('bladewright:layouts', ['--delete' => 'site'])
            ->expectsConfirmation('Delete [site]?', 'yes')
            ->assertSuccessful();

        $this->assertSame(0, Layout::query()->count());
    }

    /** A search matches part of a name. */
    public function test_search_matches_part_of_a_name(): void
    {
        $this->artisan('bladewright:layouts', ['--create' => 'site-frame'])->assertSuccessful();
        $this->artisan('bladewright:layouts', ['--create' => 'docs'])->assertSuccessful();

        $this->artisan('bladewright:layouts', ['--search' => 'site'])
            ->expectsOutputToContain('site-frame')
            ->doesntExpectOutputToContain('docs')
            ->assertSuccessful();
    }
}
