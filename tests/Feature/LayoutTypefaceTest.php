<?php

namespace Bladewright\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Bladewright\Blocks\LayoutManager;
use Bladewright\Blocks\SitePages;
use Bladewright\Site\PublicSite;
use Bladewright\Tests\TestCase;

/**
 * The typeface is the frame's word. **Top-down, like the framework** — every
 * page wearing the frame reads in it, and empty leaves the framework's own.
 */
class LayoutTypefaceTest extends TestCase
{
    use RefreshDatabase;

    /** Set on the frame, printed for every page wearing it. */
    public function test_the_frame_lends_every_page_its_typeface(): void
    {
        $layout = $this->app->make(LayoutManager::class)->create('site', 'header');
        $this->app->make(LayoutManager::class)->saveTypeface($layout, 'Noto Sans JP, sans-serif');

        $pages = $this->app->make(SitePages::class);
        $page = $pages->create('Demo', 'demo', 'site');

        $this->assertStringContainsString(
            'body{font-family:Noto Sans JP, sans-serif}',
            app(PublicSite::class)->assembledDocument($page),
        );
    }

    /** Nothing set, nothing printed. */
    public function test_an_empty_typeface_prints_nothing(): void
    {
        $layout = $this->app->make(LayoutManager::class)->create('site', 'header');

        $pages = $this->app->make(SitePages::class);
        $page = $pages->create('Demo', 'demo', 'site');

        $this->assertStringNotContainsString('font-family', app(PublicSite::class)->assembledDocument($page));
    }

    /** Only what reads as a font stack is written out. */
    public function test_what_does_not_read_as_a_stack_is_refused(): void
    {
        $layout = $this->app->make(LayoutManager::class)->create('site', 'header');

        $this->expectException(InvalidArgumentException::class);
        $this->app->make(LayoutManager::class)->saveTypeface($layout, 'serif;color:red');
    }

    /** The screen settles it the moment it is typed, like the bands. */
    public function test_the_screen_saves_it_as_typed(): void
    {
        $this->actingAsRole();

        $layout = $this->app->make(LayoutManager::class)->create('site', 'header');

        Livewire::test('bladewright::layout-editor', ['layout' => $layout])
            ->set('fontFamily', 'Georgia, serif')
            ->assertToast('Saved');

        $this->assertSame('Georgia, serif', $layout->refresh()->font_family);

        // And emptied, it is gone rather than stored as ''.
        Livewire::test('bladewright::layout-editor', ['layout' => $layout->refresh()])
            ->set('fontFamily', '');

        $this->assertNull($layout->refresh()->font_family);
    }

    /** A refused stack never lands, and the screen says why. */
    public function test_the_screen_refuses_a_bad_stack(): void
    {
        $this->actingAsRole();

        $layout = $this->app->make(LayoutManager::class)->create('site', 'header');

        Livewire::test('bladewright::layout-editor', ['layout' => $layout])
            ->set('fontFamily', 'serif;color:red')
            ->assertToast('does not read as a font stack');

        $this->assertNull($layout->refresh()->font_family);
    }
}
