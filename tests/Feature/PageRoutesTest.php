<?php

namespace Bladewright\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Bladewright\Blocks\BlockManager;
use Bladewright\Blocks\LayoutManager;
use Bladewright\Blocks\SitePages;
use Bladewright\Blocks\StructureManager;
use Bladewright\Site\PageRoutes;
use Bladewright\Tests\TestCase;

/**
 * A page may be a shape rather than a place: `news/{slug}` answers every
 * path of that shape, and **what stood in the opening is handed to whoever
 * draws the page.**
 */
class PageRoutesTest extends TestCase
{
    use RefreshDatabase;

    private function aShapedPage(string $url = 'news/{slug}'): \Bladewright\Models\Page
    {
        app(LayoutManager::class)->create('site', 'header');

        $pages = app(SitePages::class);
        $page = $pages->create('Item', $url, 'site');
        $pages->publish($page);

        return $page->refresh();
    }

    public function test_a_shape_answers_a_path_and_says_what_it_heard(): void
    {
        $page = $this->aShapedPage();

        [$found, $given] = app(PageRoutes::class)->match('news/hello');

        $this->assertTrue($found->is($page));
        $this->assertSame(['slug' => 'hello'], $given);
    }

    /** **A plain page always wins**, whatever shape stands beside it. */
    public function test_a_plain_page_beats_a_shape(): void
    {
        $this->aShapedPage();

        $pages = app(SitePages::class);
        $written = $pages->create('About the news', 'news/about', 'site');
        $pages->publish($written);

        [$found, $given] = app(PageRoutes::class)->match('news/about');

        $this->assertTrue($found->is($written));
        $this->assertSame([], $given);
    }

    /** **The more a page says, the better its claim.** */
    public function test_the_more_particular_shape_answers_first(): void
    {
        $this->aShapedPage('news/{slug}');

        $pages = app(SitePages::class);
        $loose = $pages->create('Anything', '{a}/{b}', 'site');
        $pages->publish($loose);

        [$found] = app(PageRoutes::class)->match('news/hello');

        $this->assertSame('news/{slug}', $found->url);

        // And the loose one still takes what nothing else claims.
        [$found] = app(PageRoutes::class)->match('shop/hats');
        $this->assertTrue($found->is($loose));
    }

    /** An opening is a whole piece of the path, and never nothing. */
    public function test_an_opening_never_swallows_a_slash_or_nothing(): void
    {
        $this->aShapedPage();

        $routes = app(PageRoutes::class);

        $this->assertNull($routes->match('news/hello/deep'));
        $this->assertNull($routes->match('news'));
        $this->assertNull($routes->match('news/'));
    }

    /** A mistyped opening is said on the screen, not left to match nothing. */
    public function test_a_mistyped_opening_is_refused(): void
    {
        $pages = app(SitePages::class);

        $this->assertNotNull($pages->urlProblem('news/{sl ug}'));
        $this->assertNotNull($pages->urlProblem('news/item-{slug}'));
        $this->assertNotNull($pages->urlProblem('{a}/{a}'));
        $this->assertNull($pages->urlProblem('news/{slug}'));

        $this->expectException(InvalidArgumentException::class);
        $pages->create('Bad', 'news/{sl ug}');
    }

    /* ------------------------------------------------------------------ */
    /* What the path said, reaching the page                               */
    /* ------------------------------------------------------------------ */

    /** The page's own markup reads it as a word of its own. */
    public function test_the_page_markup_is_given_what_the_path_said(): void
    {
        $page = $this->aShapedPage();
        app(SitePages::class)->saveMarkup($page, '<!DOCTYPE html><html><body><p id="said">{{ $slug }}</p></body></html>');

        $this->get('/news/hello')->assertOk()->assertSee('>hello<', false);
    }

    /** A component's own markup does too. */
    public function test_a_components_markup_is_given_what_the_path_said(): void
    {
        $page = $this->aShapedPage();

        $components = app(StructureManager::class);
        $section = $components->create('reader', 'section');
        $components->saveMarkup($section, '<p data-said>{{ $slug }}</p>');

        app(SitePages::class)->insertComponent($page, $section);

        $this->get('/news/hello')->assertOk()->assertSee('>hello<', false);
    }

    /**
     * **A component that finds nothing can say the page is not there.**
     *
     * Errors are swallowed into a comment so a broken part never takes a
     * page down; a deliberate 404 is not an error, and goes through.
     */
    public function test_a_page_can_say_it_is_not_there(): void
    {
        $page = $this->aShapedPage();

        $components = app(StructureManager::class);
        $section = $components->create('reader', 'section');
        $components->saveMarkup($section, "@php(\$slug === 'hello' || abort(404))\n<p>here</p>");

        app(SitePages::class)->insertComponent($page, $section);

        $this->get('/news/hello')->assertOk();
        $this->get('/news/nothing')->assertNotFound();
    }

    /**
     * **A part that fails leaves nothing behind it.**
     *
     * A render that throws is turned into a comment so it can never take the
     * page down — but it also leaves the view factory mid-stride: a
     * half-open component on its stack, its count of renders in flight one
     * too high, its buffers open. The page then came apart much later and
     * somewhere else. This holds the line: a broken part, and around it a
     * Blade component that must still close.
     */
    public function test_a_broken_part_leaves_the_render_around_it_whole(): void
    {
        $page = $this->aShapedPage('brittle');

        $components = app(StructureManager::class);

        $broken = $components->create('broken', 'section');
        $components->saveMarkup($broken, '@php(throw new \RuntimeException("gone wrong"))');

        $after = $components->create('after', 'section');
        $components->saveMarkup($after, '<p data-after>still here</p>');

        $pages = app(SitePages::class);
        $pages->insertComponent($page, $broken);
        $pages->insertComponent($page, $after);

        // The page is drawn inside a Blade component that has to close.
        $html = \Illuminate\Support\Facades\Blade::render(
            '<x-bladewright::layout title="t">{!! $inner !!}</x-bladewright::layout>',
            ['inner' => app(\Bladewright\Site\PublicSite::class)->assembledDocument($page->refresh())],
        );

        $this->assertStringContainsString('gone wrong', $html);
        $this->assertStringContainsString('still here', $html);
        $this->assertSame(0, ob_get_level() - ob_get_level());
    }

    /** A part that is merely broken still only costs a comment. */
    public function test_a_broken_part_still_only_costs_a_comment(): void
    {
        $page = $this->aShapedPage('broken');

        $components = app(StructureManager::class);
        $section = $components->create('reader', 'section');
        $components->saveMarkup($section, '@php(throw new \RuntimeException("gone wrong"))');

        app(SitePages::class)->insertComponent($page, $section);

        $this->get('/broken')->assertOk()->assertSee('gone wrong');
    }
}
