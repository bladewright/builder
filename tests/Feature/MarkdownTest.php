<?php

namespace Bladewright\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Bladewright\Content\Markdown;
use Bladewright\Tests\TestCase;

/**
 * Markdown in a body.
 *
 * Rich text wrecks a design because it starts by expressing anything and then
 * forbids things afterwards. What is held here is that nothing dangerous gets
 * through, and that **a block decides what may be written in its fields**.
 */
class MarkdownTest extends TestCase
{
    use RefreshDatabase;

    private function markdown(): Markdown
    {
        return $this->app->make(Markdown::class);
    }

    /** Formatting pasted from Word or Google Docs cannot be expressed. */
    public function test_pasted_styling_cannot_survive(): void
    {
        $html = $this->markdown()->render(
            '<p style="font-size:48px;color:#f0f"><font face="MS Pゴシック">貼り付け</font></p>',
        );

        $this->assertStringNotContainsString('font-size', $html);
        $this->assertStringNotContainsString('<font', $html);
        $this->assertStringNotContainsString('style=', $html);
    }

    /**
     * CommonMark's defaults let an onerror attribute and a javascript: link
     * through. Both are attacks that really work, so they are closed always,
     * not by a setting.
     */
    public function test_dangerous_html_and_links_are_neutralised(): void
    {
        $html = $this->markdown()->render(<<<'MD'
        <script>alert('攻撃')</script>
        <img src=x onerror="alert(1)">

        [危険](javascript:alert(1))
        MD);

        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('javascript:', $html);
    }

    /** No page gets two h1s: whatever level was written is pushed down. */
    public function test_headings_are_pushed_down(): void
    {
        $html = $this->markdown()->render("# 大見出し\n\n## 中見出し");

        $this->assertStringContainsString('<h3>大見出し</h3>', $html);
        $this->assertStringContainsString('<h4>中見出し</h4>', $html);
        $this->assertStringNotContainsString('<h1>', $html);
    }

    public function test_the_heading_offset_can_be_set_per_field(): void
    {
        $html = $this->markdown()->render('# 見出し', headingOffset: 0);

        $this->assertStringContainsString('<h1>見出し</h1>', $html);
    }

    /** A block can decide that no heading may be written in an intro. */
    public function test_a_field_can_forbid_headings(): void
    {
        $html = $this->markdown()->render("# 見出しのつもり\n\n本文", allow: ['bold', 'link']);

        $this->assertStringNotContainsString('<h1', $html);
        $this->assertStringNotContainsString('<h3', $html);

        // **The words written are kept.** Only the formatting is dropped.
        $this->assertStringContainsString('見出しのつもり', $html);
        $this->assertStringContainsString('本文', $html);
    }

    public function test_a_field_can_forbid_links_but_keep_the_text(): void
    {
        $html = $this->markdown()->render('詳しくは [こちら](/contact) から', allow: ['bold']);

        $this->assertStringNotContainsString('<a ', $html);
        $this->assertStringContainsString('こちら', $html);
    }

    public function test_a_field_can_forbid_images(): void
    {
        $html = $this->markdown()->render('![代替テキスト](/huge.jpg)', allow: ['bold']);

        $this->assertStringNotContainsString('<img', $html);
    }

    public function test_allowed_formatting_still_works(): void
    {
        $html = $this->markdown()->render(<<<'MD'
        **太字**と[リンク](/contact)。

        - 一つ目
        - 二つ目
        MD);

        $this->assertStringContainsString('<strong>太字</strong>', $html);
        $this->assertStringContainsString('<a href="/contact">リンク</a>', $html);
        $this->assertStringContainsString('<li>一つ目</li>', $html);
    }

    /** Tables are off by default; they break a container on a narrow screen, so only a block that wants them opens them. */
    public function test_tables_are_off_by_default_but_can_be_enabled(): void
    {
        $table = "| a | b |\n|---|---|\n| 1 | 2 |";

        $this->assertStringNotContainsString('<table', $this->markdown()->render($table));
        $this->assertStringContainsString('<table', $this->markdown()->render($table, allow: ['table']));
    }

    /** Deep nesting breaks the container. */
    public function test_deep_nesting_is_capped(): void
    {
        $html = $this->markdown()->render("- a\n  - b\n    - c\n      - d\n        - e\n          - f");

        $this->assertLessThanOrEqual(4, substr_count($html, '<ul>'));
    }

    /** Our own name. A layout's Blade can call it. */
    public function test_the_blade_directive_renders_markdown(): void
    {
        $html = \Illuminate\Support\Facades\Blade::render('<article>@bwmarkdown("**強調**された本文")</article>');

        $this->assertStringContainsString('<strong>強調</strong>', $html);
    }

    /** When it is free, the shorter name is claimed too. */
    public function test_the_short_name_works_when_it_is_free(): void
    {
        $html = \Illuminate\Support\Facades\Blade::render('<article>@markdown("**強調**された本文")</article>');

        $this->assertStringContainsString('<strong>強調</strong>', $html);
    }
}
