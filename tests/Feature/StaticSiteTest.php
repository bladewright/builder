<?php

namespace Bladewright\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use ZipArchive;
use Bladewright\Blocks\BlockManager;
use Bladewright\Blocks\LayoutManager;
use Bladewright\Blocks\SitePages;
use Bladewright\Blocks\StructureManager;
use Bladewright\Media\MediaLibrary;
use Bladewright\Site\StaticSite;
use Bladewright\Tests\TestCase;

/**
 * The site taken as files: **what the application was serving becomes a
 * file**, and what is fetched from somewhere else stays fetched from there.
 */
class StaticSiteTest extends TestCase
{
    use RefreshDatabase;

    private function aSite(): void
    {
        app(\Bladewright\Support\Framework::class)->save('bootstrap');
        app(LayoutManager::class)->create('site', 'header');

        $pages = app(SitePages::class);
        $components = app(StructureManager::class);
        $blocks = app(BlockManager::class);

        foreach ([['Home', ''], ['About', 'about'], ['Deep', 'company/people']] as [$name, $url]) {
            $page = $pages->create($name, $url, 'site');

            $section = $components->create('s-'.($url ?: 'home'), 'section');
            $block = $blocks->create('b-'.($url ?: 'home'), 'markdown');
            $block->forceFill(['data' => ['body' => 'words of '.$name]])->save();
            $components->insertBlock($section, $block);

            $pages->insertComponent($page, $section);
            $pages->publish($page);
        }

        // One that is not published, and one that is a shape.
        $pages->create('Secret', 'secret', 'site');
        $pages->publish($pages->create('Item', 'news/{slug}', 'site'));
    }

    private function opened(string $path): ZipArchive
    {
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true);

        return $zip;
    }

    public function test_every_published_page_becomes_a_file(): void
    {
        $this->aSite();

        $path = tempnam(sys_get_temp_dir(), 'bw').'.zip';
        $said = app(StaticSite::class)->writeTo($path);

        $zip = $this->opened($path);

        $this->assertNotFalse($zip->locateName('index.html'));
        $this->assertNotFalse($zip->locateName('about/index.html'));
        $this->assertNotFalse($zip->locateName('company/people/index.html'));
        $this->assertNotFalse($zip->locateName('site.css'));

        // Not published: not there. A shape: not there either.
        $this->assertFalse($zip->locateName('secret/index.html'));
        $this->assertSame(3, $said['pages']);

        $this->assertStringContainsString('words of About', $zip->getFromName('about/index.html'));

        $zip->close();
        unlink($path);
    }

    /** **What was a route becomes a file**, reached from where the page stands. */
    public function test_the_stylesheet_is_a_file_beside_the_pages(): void
    {
        $this->aSite();

        $path = tempnam(sys_get_temp_dir(), 'bw').'.zip';
        app(StaticSite::class)->writeTo($path);
        $zip = $this->opened($path);

        $front = $zip->getFromName('index.html');
        $deep = $zip->getFromName('company/people/index.html');

        $this->assertStringContainsString('href="site.css"', $front);
        $this->assertStringNotContainsString('bladewright/site.css', $front);

        // Two deep, so two steps back — the copy works from a folder too.
        $this->assertStringContainsString('href="../../site.css"', $deep);

        $zip->close();
        unlink($path);
    }

    /** **A CDN stays a CDN.** Rewriting it would only make the copy worse. */
    public function test_what_is_fetched_elsewhere_is_left_alone(): void
    {
        $this->aSite();

        $path = tempnam(sys_get_temp_dir(), 'bw').'.zip';
        app(StaticSite::class)->writeTo($path);
        $zip = $this->opened($path);

        $this->assertStringContainsString('cdn.jsdelivr.net', $zip->getFromName('index.html'));

        $zip->close();
        unlink($path);
    }

    /** A picture travels with the pages, and is pointed at where it landed. */
    public function test_the_pictures_come_too(): void
    {
        $this->aSite();

        $file = app(MediaLibrary::class)->store(UploadedFile::fake()->image('hero.png', 8, 8));

        $blocks = app(BlockManager::class);
        $picture = $blocks->create('hero', 'image');
        $picture->forceFill(['data' => ['source' => $file->path, 'alt' => 'a hero']])->save();

        $section = \Bladewright\Models\Structure::query()->where('name', 's-about')->firstOrFail();
        app(StructureManager::class)->insertBlock($section, $picture);

        $path = tempnam(sys_get_temp_dir(), 'bw').'.zip';
        app(StaticSite::class)->writeTo($path);
        $zip = $this->opened($path);

        $this->assertNotFalse($zip->locateName('media/'.$file->path));

        $about = $zip->getFromName('about/index.html');
        $this->assertStringContainsString('src="../media/'.$file->path.'"', $about);
        $this->assertStringNotContainsString('bladewright/media', $about);

        $zip->close();
        unlink($path);
    }

    /* ------------------------------------------------------------------ */
    /* The screen                                                          */
    /* ------------------------------------------------------------------ */

    public function test_the_room_says_what_will_come_out(): void
    {
        $this->actingAsRole();
        $this->aSite();

        $this->get(route('bladewright.admin.settings.export'))
            ->assertOk()
            ->assertSee('about/index.html')
            ->assertSee('company/people/index.html')
            // The shape is said, not hidden.
            ->assertSee('news/{slug}');
    }

    public function test_the_press_hands_over_a_zip(): void
    {
        $this->actingAsRole();
        $this->aSite();

        Livewire::test('bladewright::site-export')->call('take')->assertFileDownloaded();
    }

    /** With nothing published there is nothing to take, and it says so. */
    public function test_an_empty_site_is_refused_kindly(): void
    {
        $this->actingAsRole();
        app(LayoutManager::class)->create('site', 'header');

        Livewire::test('bladewright::site-export')
            ->call('take')
            ->assertToast('no published page');
    }
}
