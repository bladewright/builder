<?php

namespace Bladewright\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Bladewright\Blocks\BlockManager;
use Bladewright\Support\Framework;
use Bladewright\Tests\TestCase;

/**
 * The site's one CSS framework — **where bottom-up meets top-down.**
 *
 * A block is placed anywhere, but what its classes mean comes from whatever
 * stylesheet wraps the page, so the declaration is the site's: made at
 * install, read by the frames through `@bwframework`, and read again by the
 * editors' previews so a class looks in the editor like it will on the page.
 */
class FrameworkTest extends TestCase
{
    use RefreshDatabase;

    /** Undeclared reads as Bootstrap — what every site was born with before. */
    public function test_an_undeclared_site_reads_as_bootstrap(): void
    {
        $this->assertSame('bootstrap', app(Framework::class)->get());
        $this->assertStringContainsString('bootstrap@5', app(Framework::class)->linkTag());
    }

    /** The owner's table writes "Plain CSS"; the declaration is easy about spelling. */
    public function test_the_spelling_is_forgiven_and_junk_is_refused(): void
    {
        $framework = app(Framework::class);

        $framework->save('Plain CSS');
        $this->assertSame('plain', app(Framework::class)->get());
        $this->assertSame('', app(Framework::class)->linkTag());

        $framework->save('Pico');
        $this->assertSame('pico', app(Framework::class)->get());
        $this->assertStringContainsString('@picocss/pico', app(Framework::class)->linkTag());

        $this->expectException(\InvalidArgumentException::class);
        $framework->save('tailwind');
    }

    /**
     * **The preview wears what the site wears.** A `btn btn-primary` typed
     * on the Class card has to look in the editor like it will on the page,
     * which means the declared framework's stylesheet is in the preview's
     * head — and follows the declaration when it changes.
     */
    public function test_the_block_preview_loads_the_declared_framework(): void
    {
        $this->actingAsRole();
        $block = app(BlockManager::class)->create('cta', 'button');

        $editor = Livewire::test('bladewright::block-editor', ['block' => $block]);

        $this->assertStringContainsString('bootstrap@5', $editor->instance()->preview());

        app(Framework::class)->save('pico');

        $this->assertStringContainsString('@picocss/pico', Livewire::test(
            'bladewright::block-editor', ['block' => $block],
        )->instance()->preview());
    }
}
