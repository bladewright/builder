<?php

namespace Bladewright\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Bladewright\Blocks\BlockManager;
use Bladewright\Blocks\LayoutManager;
use Bladewright\Blocks\SitePages;
use Bladewright\Blocks\StructureManager;
use Bladewright\Tests\TestCase;

/**
 * The four-layer world, served. **The new world answers first; everywhere
 * else the old world carries on.**
 */
class PublicSiteTest extends TestCase
{
    use RefreshDatabase;

    /** layout > page > component > block, out of one URL. */
    private function aServedPage(string $url = 'demo'): void
    {
        // This suite reads the plain frame's own markup (.shell), so the
        // site declares it.
        app(\Bladewright\Support\Framework::class)->save('plain');
        $layout = $this->app->make(LayoutManager::class)->create('site', 'header');

        $block = $this->app->make(BlockManager::class)->create('intro', 'markdown');
        $block->forceFill(['data' => ['body' => "## ようこそ\n\nこれが**新世界**です。"]])->save();

        $button = $this->app->make(BlockManager::class)->create('cta', 'button');
        $button->forceFill(['data' => ['label' => 'はじめる', 'type' => 'submit']])->save();

        $hero = $this->app->make(StructureManager::class)->create('hero', 'section');
        $this->app->make(StructureManager::class)->insertBlock($hero, $block);
        $this->app->make(StructureManager::class)->insertBlock($hero, $button);

        $pages = $this->app->make(SitePages::class);
        $page = $pages->create('Demo', $url, 'site');
        $pages->insertComponent($page, $hero);
        $pages->publish($page);
    }

    public function test_a_published_page_is_served_whole(): void
    {
        $this->aServedPage();

        $this->get('/demo')
            ->assertOk()
            // The markdown came out as HTML (headings pushed down by the
            // configured offset), inside the component's tag, inside the frame.
            ->assertSee('ようこそ', false)
            ->assertSee('<strong>新世界</strong>', false)
            ->assertSee('<section', false)
            ->assertSee('--ink', false)
            ->assertSee('はじめる', false)
            // **Every block stands in a box of its own** — otherwise the
            // component's own flex stretches a button across the page.
            ->assertSee("    <div>\n        <button type=\"submit\">はじめる</button>\n    </div>", false);
    }

    /** Not published (or not yet): the old world answers, which here is 404. */
    public function test_an_unpublished_page_stays_invisible(): void
    {
        $this->aServedPage();

        \Bladewright\Models\Page::query()->firstOrFail()->forceFill(['is_published' => false])->save();

        $this->get('/demo')->assertNotFound();

        \Bladewright\Models\Page::query()->firstOrFail()
            ->forceFill(['is_published' => true, 'published_from' => now()->addDay()])->save();

        $this->get('/demo')->assertNotFound();
    }

    /** A closed window is 410 Gone: taken away on purpose. */
    public function test_a_closed_window_is_gone(): void
    {
        $this->aServedPage();

        \Bladewright\Models\Page::query()->firstOrFail()
            ->forceFill(['published_until' => now()->subHour()])->save();

        $this->get('/demo')->assertStatus(410);
    }

    /** A path with no page is 404 — and the host's own routes still win. */
    public function test_everything_else_is_a_404_and_the_host_wins(): void
    {
        $this->aServedPage('demo');

        $this->get('/nowhere')->assertNotFound();
        $this->get('/host-owned')->assertOk()->assertSee('host application', false);
    }

    /** Editing a block reaches the served page — nothing was baked. */
    public function test_editing_a_block_reaches_the_page(): void
    {
        $this->aServedPage();

        $this->get('/demo')->assertSee('ようこそ', false);

        \Bladewright\Models\Block::query()->where('name', 'intro')->firstOrFail()
            ->forceFill(['data' => ['body' => '直した本文']])->save();

        $this->get('/demo')->assertSee('直した本文', false)->assertDontSee('ようこそ', false);
    }

    /** What a block holds is content, never markup. */
    public function test_block_data_is_escaped(): void
    {
        $this->aServedPage();

        \Bladewright\Models\Block::query()->where('name', 'cta')->firstOrFail()
            ->forceFill(['data' => ['label' => '<script>alert(1)</script>', 'url' => '/x']])->save();

        $response = $this->get('/demo');

        $response->assertOk();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $response->getContent());
    }

    /** A page with no layout comes out bare — a minimal document, not an invalid one. */
    public function test_a_bare_page_is_a_whole_document(): void
    {
        $pages = $this->app->make(SitePages::class);
        $page = $pages->create('Bare', 'bare');
        $pages->publish($page);

        $this->get('/bare')
            ->assertOk()
            ->assertSee('<!DOCTYPE html>', false)
            ->assertSee('<title>Bare</title>', false);
    }

    /**
     * **A page costs a handful of questions, not one a part.**
     *
     * Walking a page used to ask the database for every block and every
     * component by name, one at a time: forty blocks meant fifty-eight round
     * trips. Each level is fetched together now, and the level below with
     * it. This holds the line.
     */
    public function test_a_page_is_read_in_a_handful_of_questions(): void
    {
        app(\Bladewright\Support\Framework::class)->save('plain');
        $layout = $this->app->make(LayoutManager::class)->create('site', 'header');

        $pages = $this->app->make(SitePages::class);
        $page = $pages->create('Big', 'big', 'site');

        foreach (range(1, 6) as $s) {
            $section = $this->app->make(StructureManager::class)->create('s'.$s, 'section');

            foreach (range(1, 5) as $b) {
                $block = $this->app->make(BlockManager::class)->create('b'.$s.'-'.$b, 'markdown');
                $block->forceFill(['data' => ['body' => 'words']])->save();
                $this->app->make(StructureManager::class)->insertBlock($section, $block);
            }

            $pages->insertComponent($page, $section);
        }

        $site = $this->app->make(\Bladewright\Site\PublicSite::class);

        // Warm, so what is counted is the reading and not the booting.
        $site->assembledDocument($page->refresh());

        $asked = 0;
        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Database\Events\QueryExecuted::class,
            function () use (&$asked) { $asked++; },
        );

        $html = $this->app->make(\Bladewright\Site\PublicSite::class)->assembledDocument($page);

        $this->assertStringContainsString('words', $html);

        // Six components and thirty blocks. **A part each would be thirty-six.**
        $this->assertLessThan(12, $asked, "A page of 36 parts asked {$asked} questions.");
    }
}
