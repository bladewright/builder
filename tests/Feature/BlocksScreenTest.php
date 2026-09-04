<?php

namespace Bladewright\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Bladewright\Blocks\BlockManager;
use Bladewright\Blocks\StructureManager;
use Bladewright\Models\Block;
use Bladewright\Tests\TestCase;

/**
 * The blocks screens — the first screens of the four-layer world.
 * **The content lives here**; the commands built only the skeleton.
 */
class BlocksScreenTest extends TestCase
{
    use RefreshDatabase;

    /** The list shows the blocks, and the row is the way in — by uuid, not id. */
    public function test_the_list_shows_blocks_and_rows_open_them(): void
    {
        $this->actingAsRole();
        $block = app(BlockManager::class)->create('intro', 'markdown');

        $this->get('/bladewright/blocks')
            ->assertOk()
            ->assertSee('intro', false)
            ->assertSee('data-bw-row-href="'.route('bladewright.admin.blocks.edit', $block).'"', false)
            ->assertSee($block->uuid, false);
    }

    /** Creating from the list opens the editor for the new block. */
    public function test_creating_from_the_list_opens_the_editor(): void
    {
        $this->actingAsRole();

        Livewire::test('bladewright::blocks-list')
            ->call('startCreating')
            ->set('newName', 'intro')
            ->set('newType', 'markdown')
            ->call('create')
            ->assertRedirect(route('bladewright.admin.blocks.edit', Block::query()->firstOrFail()));
    }

    /**
     * **The label is part of the field.** One is no use without the other,
     * so they are one block: the words wrap the control the way a radio's
     * choices are wrapped — no ids minted, and pressing the words reaches
     * the box all the same.
     */
    public function test_a_field_wears_its_own_label(): void
    {
        $this->actingAsRole();
        $block = app(BlockManager::class)->create('email', 'input');

        // Born with its own name written in the label — a field nobody
        // named cannot be used, and emptying the box is easy.
        $this->assertSame('email', $block->data['label']);

        Livewire::test('bladewright::block-editor', ['block' => $block])
            ->set('data.label', 'メールアドレス')
            ->set('data.name', 'email')
            ->set('data.type', 'email')
            ->call('save');

        $this->assertSame(
            "<div>\n    <label>メールアドレス\n        <input type=\"email\" name=\"email\">\n    </label>\n</div>",
            app(\Bladewright\Site\PublicSite::class)->block($block->refresh()),
        );
    }

    /** Nothing names it, nothing is written: no stray label, no stray id. */
    public function test_a_field_without_a_label_wears_neither(): void
    {
        $this->actingAsRole();
        $block = app(BlockManager::class)->create('email', 'input');

        Livewire::test('bladewright::block-editor', ['block' => $block])
            ->set('data.label', '')
            ->set('data.name', 'email')
            ->call('save');

        $this->assertSame(
            "<div>\n    <input type=\"text\" name=\"email\">\n</div>",
            app(\Bladewright\Site\PublicSite::class)->block($block->refresh()),
        );
    }

    /** **A label is not a block**: it belongs to the field it names. */
    public function test_a_label_is_not_a_type(): void
    {
        $this->actingAsRole();

        Livewire::test('bladewright::blocks-list')
            ->call('startCreating')
            ->set('newName', 'stray')
            ->set('newType', 'label')
            ->call('create')
            ->assertHasErrors('newName');

        $this->assertSame(0, Block::query()->count());
    }

    /** A taken name is refused where it was typed. */
    public function test_a_taken_name_is_refused_in_the_modal(): void
    {
        $this->actingAsRole();
        app(BlockManager::class)->create('intro', 'markdown');

        Livewire::test('bladewright::blocks-list')
            ->call('startCreating')
            ->set('newName', 'intro')
            ->call('create')
            ->assertHasErrors('newName');
    }

    /** The editor saves what the type owns, and says how far the edit reached. */
    public function test_the_editor_saves_content_and_says_the_reach(): void
    {
        $this->actingAsRole();
        $block = app(BlockManager::class)->create('intro', 'markdown');

        $hero = app(StructureManager::class)->create('hero', 'section');
        $footer = app(StructureManager::class)->create('footer', 'nav');
        app(StructureManager::class)->insertBlock($hero, $block);
        app(StructureManager::class)->insertBlock($footer, $block);

        Livewire::test('bladewright::block-editor', ['block' => $block])
            ->set('data.body', '# ようこそ')
            ->call('save')
            ->assertToast('It changed in 2 places at once');

        $this->assertSame('# ようこそ', $block->refresh()->data['body']);
    }

    /**
     * The preview is the real renderer over the unsaved screen, and it comes
     * back as a whole document — it lives in a frame, out of the admin's CSS.
     */
    public function test_the_preview_renders_what_is_typed(): void
    {
        $this->actingAsRole();
        $block = app(BlockManager::class)->create('intro', 'markdown');

        $editor = Livewire::test('bladewright::block-editor', ['block' => $block])
            ->set('data.body', '本文は**強調**される');

        $preview = $editor->instance()->preview();

        $this->assertStringContainsString('<strong>強調</strong>', $preview);
        $this->assertStringContainsString('<!DOCTYPE html>', $preview);

        // Nothing was saved by typing.
        $this->assertSame('', (string) ($block->refresh()->data['body'] ?? ''));
    }

    /**
     * **Both faces are on the page at once**, and the pills are the browser's
     * — switched and remembered there, as the device widths are, so a reload
     * comes back to the one being worked on.
     */
    public function test_both_faces_are_there_for_the_pills(): void
    {
        $this->actingAsRole();
        $block = app(BlockManager::class)->create('intro', 'markdown');

        $this->get(route('bladewright.admin.blocks.edit', $block))
            ->assertOk()
            ->assertSee('data-bw-pills="block" data-bw-pill="preview"', false)
            ->assertSee('data-bw-pills="block" data-bw-pill="code"', false)
            ->assertSee('data-bw-panel="preview"', false)
            ->assertSee('data-bw-panel="code"', false);
    }

    /**
     * **The Code pill shows the block's own HTML**, starting from what the
     * fields make. Change it and it becomes the block.
     */
    public function test_the_code_pill_shows_and_writes_the_markup(): void
    {
        $this->actingAsRole();
        $block = app(BlockManager::class)->create('cta', 'button');
        $block->forceFill(['data' => ['label' => '管理画面へ', 'type' => 'submit']])->save();

        $editor = Livewire::test('bladewright::block-editor', ['block' => $block])
            // **The box the block stands in is part of what it comes out as.**
            ->assertSet('markup', "<div>\n    <button type=\"submit\">管理画面へ</button>\n</div>");

        // Untouched: nothing is stored, so the fields still make the block.
        $editor->call('save');
        $this->assertSame('', $block->refresh()->data['markup']);

        $editor->set('markup', '<button type="submit"><span>→</span> 入る</button>')->call('save');

        $this->assertStringContainsString(
            '<span>→</span> 入る',
            app(\Bladewright\Site\PublicSite::class)->block($block->refresh()),
        );
    }

    /** While nobody has written it, the fields lead and the Code pill follows. */
    public function test_the_markup_follows_the_fields_until_it_is_written(): void
    {
        $this->actingAsRole();
        $block = app(BlockManager::class)->create('cta', 'button');

        Livewire::test('bladewright::block-editor', ['block' => $block])
            ->set('data.label', '買う')
            ->assertSet('markup', "<div>\n    <button type=\"button\">買う</button>\n</div>")
            ->call('save');

        $this->assertSame('', $block->refresh()->data['markup']);
        $this->assertStringContainsString('買う', app(\Bladewright\Site\PublicSite::class)->block($block));
    }

    /**
     * **The markup is Blade, and it runs.** This is where code lives in the
     * four layers: a block can show what is worked out when the page is
     * asked for, not only what was typed beforehand.
     */
    public function test_the_markup_is_blade_and_runs(): void
    {
        $this->actingAsRole();
        $block = app(BlockManager::class)->create('list', 'markdown');

        Livewire::test('bladewright::block-editor', ['block' => $block])
            ->set('markup', '<ul>@foreach ([1, 2] as $n)<li>{{ $n }}</li>@endforeach</ul>')
            ->call('save');

        $this->assertSame(
            '<ul><li>1</li><li>2</li></ul>',
            app(\Bladewright\Site\PublicSite::class)->block($block->refresh()),
        );
    }

    /**
     * **A page never dies of it.** Blade that throws leaves a comment where
     * the block stood; the editor says why, where it can be fixed.
     */
    public function test_broken_blade_does_not_take_the_page_down(): void
    {
        $this->actingAsRole();
        $block = app(BlockManager::class)->create('cta', 'button');

        $editor = Livewire::test('bladewright::block-editor', ['block' => $block])
            ->set('markup', '{{ bladewright_no_such_helper() }}');

        $this->assertNotNull($editor->instance()->codeError());

        $editor->call('save');

        $this->assertStringContainsString('<!--', app(\Bladewright\Site\PublicSite::class)->block($block->refresh()));
    }

    /**
     * **`link` is a kind of button.** The element stays a `<button>`; the URL
     * is asked for only then, and only then does it reach the page.
     */
    public function test_a_link_button_walks_to_the_url(): void
    {
        $this->actingAsRole();
        $block = app(BlockManager::class)->create('cta', 'button');

        $editor = Livewire::test('bladewright::block-editor', ['block' => $block])
            ->set('data.label', '入る');

        // **What it does is chosen, not spelled**, and it stands on one of
        // its own from the moment the editor opens.
        $editor->assertSet('data.type', 'button')
            ->assertSeeHtml('<option value="submit">');

        // Not a link yet: nothing is asked about where it goes.
        $this->assertNotContains('url', array_column($editor->instance()->fields(), 'key'));

        $editor->set('data.type', 'link');

        $this->assertContains('url', array_column($editor->instance()->fields(), 'key'));

        $editor->set('data.url', "/it's")
            ->assertSet('markup', "<div>\n    <button type=\"button\" onclick=\"location.href='/it\\&#039;s'\">入る</button>\n</div>")
            ->call('save');

        // The quote stayed a quote: it never closed the string it sits in.
        $this->assertStringContainsString(
            'onclick="location.href=\'/it\\&#039;s\'"',
            app(\Bladewright\Site\PublicSite::class)->block($block->refresh()),
        );
    }

    /**
     * **The tag stands before it is filled in.** An unfilled block is a shape
     * waiting for its content, not an empty screen that reads as broken — and
     * a `src` with nothing in it is left off rather than written empty.
     */
    public function test_an_unfilled_block_still_shows_its_tag(): void
    {
        $this->actingAsRole();
        $block = app(BlockManager::class)->create('sample', 'image');

        $editor = Livewire::test('bladewright::block-editor', ['block' => $block])
            ->assertSet('markup', "<div>\n    <img alt=\"\" loading=\"lazy\">\n</div>");

        $editor->set('data.source', 'https://example.com/logo.png')
            ->assertSet('markup', "<div>\n    <img src=\"https://example.com/logo.png\" alt=\"\" loading=\"lazy\">\n</div>");
    }

    /** Words not yet written are words, not a shape: nothing stands there. */
    public function test_an_empty_markdown_block_stands_for_nothing(): void
    {
        $this->actingAsRole();
        $block = app(BlockManager::class)->create('intro', 'markdown');

        Livewire::test('bladewright::block-editor', ['block' => $block])
            ->assertSet('markup', '')
            ->assertSee('puts nothing on the page');
    }

    /**
     * **A block carries its own look**, and it lands on the element itself —
     * a button's colour is the button's, not its box's.
     */
    public function test_the_style_lands_on_the_element(): void
    {
        $this->actingAsRole();
        $block = app(BlockManager::class)->create('cta', 'button');

        Livewire::test('bladewright::block-editor', ['block' => $block])
            ->set('data.label', '買う')
            ->set('style.background', '#3538cd')
            ->set('style.color', '#ffffff')
            ->set('style.border-radius', '0.5rem')
            ->call('toggle', 'bold')
            ->call('save');

        $this->assertSame(
            "<div>\n    <button type=\"button\" style=\"color:#ffffff;background:#3538cd;font-weight:700;border-radius:0.5rem\">買う</button>\n</div>",
            app(\Bladewright\Site\PublicSite::class)->block($block->refresh()),
        );
    }

    /**
     * **Where a block sits is the box's business**, whatever the block is —
     * nothing can place itself. A button is moved by its box; its own colours
     * stay on the button.
     */
    public function test_where_it_sits_is_written_on_the_box(): void
    {
        $this->actingAsRole();
        $block = app(BlockManager::class)->create('cta', 'button');

        Livewire::test('bladewright::block-editor', ['block' => $block])
            ->set('data.label', '買う')
            ->call('choose', 'align', 'center')
            ->set('style.background', '#3538cd')
            ->call('save');

        $this->assertSame(
            "<div style=\"text-align:center\">\n    <button type=\"button\" style=\"background:#3538cd\">買う</button>\n</div>",
            app(\Bladewright\Site\PublicSite::class)->block($block->refresh()),
        );
    }

    /**
     * **Bold, italic, underline, strike are pressed on and off**, as in any
     * writing tool — and the two that share one CSS property stand side by
     * side when both are on.
     */
    public function test_the_switches_are_pressed_on_and_off(): void
    {
        $this->actingAsRole();
        $block = app(BlockManager::class)->create('cta', 'button');

        $editor = Livewire::test('bladewright::block-editor', ['block' => $block])
            ->set('data.label', '5,000円')
            ->call('toggle', 'italic')
            ->call('toggle', 'underline')
            ->call('toggle', 'strike');

        $editor->call('save');

        $this->assertStringContainsString(
            'style="font-style:italic;text-decoration:underline line-through"',
            app(\Bladewright\Site\PublicSite::class)->block($block->refresh()),
        );

        // Pressed again, it is off — and nothing of it is stored.
        $editor->call('toggle', 'underline')->call('toggle', 'strike')->call('save');

        $this->assertStringContainsString(
            'style="font-style:italic"',
            app(\Bladewright\Site\PublicSite::class)->block($block->refresh()),
        );
    }

    /**
     * **A size is judged by looking, so it has a slider** — and the box
     * beside it still holds what is stored, each following the other.
     */
    public function test_a_size_can_be_felt_out_or_typed(): void
    {
        $this->actingAsRole();
        $block = app(BlockManager::class)->create('cta', 'button');

        $editor = Livewire::test('bladewright::block-editor', ['block' => $block])
            ->set('data.label', '買う')
            ->set('sliders.border-radius', 1.5);

        $editor->assertSet('style.border-radius', '1.5rem');

        // Typed in, the handle follows.
        $editor->set('style.border-radius', '0.5rem')
            ->assertSet('sliders.border-radius', 0.5);

        // **Nothing at the far left**: no rounding at all, not `0rem`.
        $editor->set('sliders.border-radius', 0)->assertSet('style.border-radius', '');

        // The text size is felt out the same way.
        $editor->set('sliders.font-size', 1.25)->assertSet('style.font-size', '1.25rem');

        // Something the slider cannot say is left alone, and it sits at zero.
        $editor->set('style.border-radius', '50%')
            ->assertSet('sliders.border-radius', 0.0)
            ->call('save');

        $this->assertSame('50%', $block->refresh()->data['style']['border-radius']);
    }

    /**
     * **Padding is edited as a shape**, four sides in a box, and stored as the
     * one value it always was — read back into the box when it can be, left
     * alone and typed when it cannot.
     */
    public function test_padding_is_four_sides_of_one_value(): void
    {
        $this->actingAsRole();
        $block = app(BlockManager::class)->create('cta', 'button');

        $editor = Livewire::test('bladewright::block-editor', ['block' => $block])
            ->set('data.label', '買う');

        // The same all round says itself once.
        $editor->set('sides.top', '1rem')->set('sides.right', '1rem')
            ->set('sides.bottom', '1rem')->set('sides.left', '1rem')
            ->assertSet('style.padding', '1rem');

        // Above and beside, said as two.
        $editor->set('sides.right', '2rem')->set('sides.left', '2rem')
            ->assertSet('style.padding', '1rem 2rem');

        // One side alone: the others stand at nothing, said as zero.
        $editor->set('sides.top', '')->set('sides.bottom', '')->set('sides.left', '')
            ->assertSet('style.padding', '0 2rem 0 0');

        // Written out, it is read back into the box.
        $editor->set('style.padding', '3rem 1rem')
            ->assertSet('sides.top', '3rem')
            ->assertSet('sides.left', '1rem');

        // **Nothing is silently rewritten**: what the box cannot take apart
        // is left exactly as it stands, and typed instead.
        $editor->set('style.padding', 'clamp(1rem, 2vw, 2rem)');

        $this->assertFalse($editor->instance()->paddingFitsTheBox());

        $editor->call('save');

        $this->assertSame('clamp(1rem, 2vw, 2rem)', $block->refresh()->data['style']['padding']);
    }

    /**
     * **A border is three answers** — how thick, what colour, which sides —
     * and they are written out as one. Nothing said about sides is all the
     * way round; a colour by name follows the palette like any other.
     */
    public function test_a_border_is_drawn_on_the_sides_it_is_given(): void
    {
        $this->actingAsRole();
        $block = app(BlockManager::class)->create('cta', 'button');

        $editor = Livewire::test('bladewright::block-editor', ['block' => $block])
            ->set('data.label', '買う')
            ->set('style.border-width', '2px')
            ->set('style.border-color', 'accent');

        // Unsaid, it goes all the way round.
        $editor->call('save');

        $this->assertStringContainsString(
            'border:2px solid #3538cd',
            app(\Bladewright\Site\PublicSite::class)->block($block->refresh()),
        );

        // Pressing a side off leaves the other three, each named.
        $editor->call('toggleSide', 'border-sides', 'top')
            ->call('toggleSide', 'border-sides', 'left')
            ->call('save');

        $served = app(\Bladewright\Site\PublicSite::class)->block($block->refresh());

        $this->assertStringContainsString('border-right:2px solid #3538cd', $served);
        $this->assertStringContainsString('border-bottom:2px solid #3538cd', $served);
        $this->assertStringNotContainsString('border-top:', $served);
    }

    /** A shadow is chosen, and written as it was offered. */
    public function test_a_shadow_is_one_of_the_ones_offered(): void
    {
        $this->actingAsRole();
        $block = app(BlockManager::class)->create('cta', 'button');

        Livewire::test('bladewright::block-editor', ['block' => $block])
            ->set('data.label', '買う')
            ->set('style.box-shadow', '0 4px 12px rgb(0 0 0 / .10)')
            ->call('save');

        $this->assertStringContainsString(
            'box-shadow:0 4px 12px rgb(0 0 0 / .10)',
            app(\Bladewright\Site\PublicSite::class)->block($block->refresh()),
        );

        // A shadow nobody offered is not written.
        Livewire::test('bladewright::block-editor', ['block' => $block])
            ->set('style.box-shadow', '0 0 0 9999px red')
            ->call('save');

        $this->assertArrayNotHasKey('box-shadow', $block->refresh()->data['style']);
    }

    /**
     * **The colours are icons in the top row**, and the palette drops open
     * under them: one press to open, one to paint, and it shuts itself.
     */
    public function test_a_colour_is_taken_from_the_palette_in_two_presses(): void
    {
        $this->actingAsRole();
        $block = app(BlockManager::class)->create('cta', 'button');

        $editor = Livewire::test('bladewright::block-editor', ['block' => $block])
            ->set('data.label', '買う')
            ->assertSet('colouring', null);

        $editor->call('openColour', 'background')->assertSet('colouring', 'background');

        $editor->call('paint', 'background', 'accent')
            ->assertSet('style.background', 'accent')
            ->assertSet('colouring', null)
            ->call('save');

        $this->assertStringContainsString(
            'background:#3538cd',
            app(\Bladewright\Site\PublicSite::class)->block($block->refresh()),
        );

        // And back to nothing, from the same panel.
        Livewire::test('bladewright::block-editor', ['block' => $block])
            ->call('openColour', 'background')
            ->call('paint', 'background', '')
            ->call('save');

        $this->assertArrayNotHasKey('background', $block->refresh()->data['style']);
    }

    /**
     * **The controls make the CSS and the CSS makes the controls.** A
     * property the card knows goes back into its field; anything else is
     * kept as typed and written last, so a hand overrules a press.
     */
    public function test_the_style_can_be_written_as_css(): void
    {
        $this->actingAsRole();
        $block = app(BlockManager::class)->create('cta', 'button');

        $editor = Livewire::test('bladewright::block-editor', ['block' => $block])
            ->set('data.label', '買う')
            ->call('paint', 'background', 'accent');

        // What the controls made, written out.
        $this->assertStringContainsString('background: accent;', $editor->get('css'));

        // Written by hand: what the card knows goes back into its field…
        $editor->set('css', "background: #123456;\nline-height: 1.8;\n");

        $editor->assertSet('style.background', '#123456');

        // …and line-height is a control of its own now, so it lands there.
        $editor->assertSet('style.line-height', '1.8')->call('save');

        $served = app(\Bladewright\Site\PublicSite::class)->block($block->refresh());

        $this->assertStringContainsString('background:#123456', $served);
        $this->assertStringContainsString('line-height:1.8', $served);
    }

    /**
     * **The code reads back into the rows** — take `required` out of the
     * code and the box unticks, even while the code carries something the
     * rows cannot say (a `<br>`), in which case it stays hand-written.
     */
    public function test_a_choice_group_reads_back(): void
    {
        $this->actingAsRole();
        $block = app(BlockManager::class)->create('plan', 'radio');

        $editor = Livewire::test('bladewright::block-editor', ['block' => $block])
            ->set('data.required', true);

        // Clean markup: read in, and the rows lead again.
        $editor->set('markup', '<div><label><input type="radio" name="plan" value="a"> A</label><label><input type="radio" name="plan" value="b" disabled> B</label></div>')
            ->assertSet('data.required', '')
            ->assertSet('data.name', 'plan')
            ->assertSet('mirrored', true);

        $this->assertSame(['a', 'b'], array_column($editor->get('data.options'), 'value'));
        $this->assertTrue($editor->get('data.options')[1]['disabled']);

        // A <br> is nothing a row can say: still read, no longer led.
        $editor->set('markup', '<div><label><input type="radio" name="plan" value="a" required> A</label><br><label><input type="radio" name="plan" value="b"> B</label></div>')
            ->assertSet('data.required', '1')
            ->assertSet('mirrored', false);
    }

    /**
     * **The cards reach into written code, and the code wins.** A field edit
     * lands on the element it owns; the class somebody wrote, the words'
     * own <span>, the box around it all — none of it is touched.
     */
    public function test_the_cards_patch_written_code_without_taking_it_over(): void
    {
        $this->actingAsRole();
        $block = app(BlockManager::class)->create('cta', 'button');

        $editor = Livewire::test('bladewright::block-editor', ['block' => $block])
            ->set('markup', '<div><button type="button" class="btn btn-primary">買う</button></div>');

        // A card edit reaches the code…
        $editor->set('style.background', '#123456');

        $this->assertStringContainsString('class="btn btn-primary"', $editor->get('markup'));
        $this->assertStringContainsString('style="background:#123456"', $editor->get('markup'));

        // …and what it says follows too, with the class still standing.
        $editor->set('data.label', 'いますぐ買う');

        $this->assertStringContainsString('>いますぐ買う</button>', $editor->get('markup'));
        $this->assertStringContainsString('class="btn btn-primary"', $editor->get('markup'));

        // Blade is the one boundary: nothing is patched through it.
        $editor->set('markup', '<div><button type="button">{{ strtoupper("go") }}</button></div>')
            ->set('data.label', '効かない');

        $this->assertStringContainsString('strtoupper', $editor->get('markup'));
    }

    /** A choice group wears its look on its box, as Markdown does. */
    public function test_a_radio_wears_its_look_on_its_box(): void
    {
        $this->actingAsRole();
        $block = app(BlockManager::class)->create('plan', 'radio');

        Livewire::test('bladewright::block-editor', ['block' => $block])
            ->set('data.name', 'plan')
            ->call('paint', 'background', 'rule')
            ->call('save');

        $served = app(\Bladewright\Site\PublicSite::class)->block($block->refresh());

        $this->assertStringContainsString('<div style="background:#e4e7ec">', $served);
        $this->assertStringNotContainsString('value="option-1"'.' style', $served);
    }

    /** Markdown has no one element, so its look goes on the box it stands in. */
    public function test_markdown_wears_its_look_on_its_box(): void
    {
        $this->actingAsRole();
        $block = app(BlockManager::class)->create('intro', 'markdown');

        Livewire::test('bladewright::block-editor', ['block' => $block])
            ->set('data.body', '# ようこそ')
            ->call('choose', 'align', 'center')
            ->call('save');

        $this->assertStringContainsString(
            '<div style="text-align:center">',
            app(\Bladewright\Site\PublicSite::class)->block($block->refresh()),
        );
    }

    /**
     * **A style attribute is not a place to relay whatever was stored.** What
     * does not read as a colour, a size or one of the choices is dropped.
     */
    public function test_a_style_that_is_not_one_is_dropped(): void
    {
        $this->actingAsRole();
        $block = app(BlockManager::class)->create('cta', 'button');

        Livewire::test('bladewright::block-editor', ['block' => $block])
            ->set('data.label', '買う')
            ->set('style.background', 'red; background-image:url(http://elsewhere/x)')
            ->set('style.bold', 'enormous')
            ->call('save');

        $this->assertSame([], $block->refresh()->data['style']);
        $this->assertStringNotContainsString('style=', app(\Bladewright\Site\PublicSite::class)->block($block));
    }

    /**
     * **A textarea's value is its inside**, not an attribute — and only what
     * was answered is written, as with every field.
     */
    public function test_a_textarea_wears_its_attributes(): void
    {
        $this->actingAsRole();
        $block = app(BlockManager::class)->create('message', 'textarea');

        $editor = Livewire::test('bladewright::block-editor', ['block' => $block])
            ->set('data.name', 'message')
            ->set('data.rows', '6')
            ->set('data.maxlength', '500')
            ->set('data.value', '書きかけの言葉')
            ->set('data.required', true)
            ->call('save');

        // The words stand above the box, on a break of the renderer's own.
        $this->assertSame(
            "<div>\n    <label>message<br>\n        <textarea name=\"message\" rows=\"6\" maxlength=\"500\" required>書きかけの言葉</textarea>\n    </label>\n</div>",
            app(\Bladewright\Site\PublicSite::class)->block($block->refresh()),
        );

        // And read back from the code, inside and all.
        $editor->set('markup', '<div><textarea name="note" rows="3">直した言葉</textarea></div>')
            ->assertSet('data.name', 'note')
            ->assertSet('data.rows', '3')
            ->assertSet('data.value', '直した言葉')
            ->assertSet('data.required', '');
    }

    /**
     * **A player's flags are the element's own words.** Born with controls
     * on — a player without them is a picture nobody can press — and the
     * poster is a video's alone.
     */
    public function test_a_player_wears_its_flags(): void
    {
        $this->actingAsRole();
        $block = app(BlockManager::class)->create('clip', 'video');

        // Born pressable.
        $this->assertSame('1', $block->data['controls']);

        Livewire::test('bladewright::block-editor', ['block' => $block])
            ->set('data.source', 'https://example.com/clip.mp4')
            ->set('data.poster', 'https://example.com/still.jpg')
            ->set('data.width', '1280')
            ->set('data.height', '720')
            ->set('data.preload', 'metadata')
            ->set('data.controls', false)
            ->set('data.autoplay', true)
            ->set('data.loop', true)
            ->set('data.muted', true)
            ->set('data.playsinline', true)
            ->call('save');

        $this->assertSame(
            "<div>\n    <video src=\"https://example.com/clip.mp4\" poster=\"https://example.com/still.jpg\" width=\"1280\" height=\"720\" preload=\"metadata\" autoplay loop muted playsinline></video>\n</div>",
            app(\Bladewright\Site\PublicSite::class)->block($block->refresh()),
        );
    }

    /** A window onto somebody else's page, at the size it was meant for. */
    public function test_an_embed_wears_its_size(): void
    {
        $this->actingAsRole();
        $block = app(BlockManager::class)->create('clip', 'embed');

        Livewire::test('bladewright::block-editor', ['block' => $block])
            ->set('data.url', 'https://www.youtube.com/embed/xyz')
            ->set('data.title', '紹介動画')
            ->set('data.width', '560')
            ->set('data.height', '315')
            ->call('save');

        $this->assertStringContainsString(
            '<iframe src="https://www.youtube.com/embed/xyz" width="560" height="315" title="紹介動画"',
            app(\Bladewright\Site\PublicSite::class)->block($block->refresh()),
        );
    }

    /**
     * **A pressed picture is the oldest thing on the web**, so an image
     * given an href is wrapped in its link — sized in pixels so the page
     * keeps the room and nothing jumps.
     */
    public function test_an_image_is_sized_and_pressable(): void
    {
        $this->actingAsRole();
        $block = app(BlockManager::class)->create('banner', 'image');

        $editor = Livewire::test('bladewright::block-editor', ['block' => $block])
            ->set('data.source', 'https://example.com/banner.png')
            ->set('data.alt', '春の案内')
            ->set('data.width', '800')
            ->set('data.height', '600')
            ->set('data.href', '/spring')
            ->call('save');

        $this->assertSame(
            "<div>\n    <a href=\"/spring\"><img src=\"https://example.com/banner.png\" alt=\"春の案内\" width=\"800\" height=\"600\" loading=\"lazy\"></a>\n</div>",
            app(\Bladewright\Site\PublicSite::class)->block($block->refresh()),
        );

        // The link comes off the card, off the code — and onto written code too.
        $editor->set('data.href', '')->call('save');

        $this->assertStringNotContainsString('<a ', app(\Bladewright\Site\PublicSite::class)->block($block->refresh()));

        $editor->set('markup', '<div><img src="https://example.com/banner.png" alt="春の案内" class="hero"></div>')
            ->set('data.href', '/autumn');

        $this->assertStringContainsString('<a href="/autumn"><img', $editor->get('markup'));
        $this->assertStringContainsString('class="hero"', $editor->get('markup'));

        // And read back from a wrapped picture.
        $editor->set('markup', '<div><a href="/winter"><img src="https://example.com/banner.png" alt=""></a></div>')
            ->assertSet('data.href', '/winter');
    }

    /** The sound plays by the same words, and holds back until asked. */
    public function test_an_audio_wears_its_flags(): void
    {
        $this->actingAsRole();
        $block = app(BlockManager::class)->create('episode', 'audio');

        Livewire::test('bladewright::block-editor', ['block' => $block])
            ->set('data.source', 'https://example.com/episode.mp3')
            ->set('data.loop', true)
            ->set('data.preload', 'none')
            ->call('save');

        $this->assertSame(
            "<div>\n    <audio src=\"https://example.com/episode.mp3\" preload=\"none\" controls loop></audio>\n</div>",
            app(\Bladewright\Site\PublicSite::class)->block($block->refresh()),
        );
    }

    /**
     * **A div is the blank page of the types**: one empty element, nothing
     * to fill in, everything said on the Code pill — and it is its own box.
     */
    public function test_a_div_is_written_not_filled_in(): void
    {
        $this->actingAsRole();
        $block = app(BlockManager::class)->create('feature', 'div');

        $editor = Livewire::test('bladewright::block-editor', ['block' => $block]);

        // No Contents card: there is nothing to ask.
        $this->assertSame([], $editor->instance()->fields());
        $editor->assertSet('markup', "<div>\n</div>");

        // The style lands on the div itself — no box around a box.
        $editor->set('style.background', '#123456');

        $this->assertSame('<div style="background:#123456">'."\n".'</div>', $editor->get('markup'));

        // And what is written is the block, Blade and all.
        $editor->set('markup', '<div>@foreach ([1, 2] as $n)<p>{{ $n }}</p>@endforeach</div>')
            ->call('save');

        $this->assertSame(
            '<div><p>1</p><p>2</p></div>',
            app(\Bladewright\Site\PublicSite::class)->block($block->refresh()),
        );
    }

    /**
     * **The second pass's fields land where they mean something**: a square
     * thumbnail out of any picture, a line-height on words, and nothing
     * offered where it cannot matter.
     */
    public function test_the_second_pass_fields(): void
    {
        $this->actingAsRole();
        $photo = app(BlockManager::class)->create('thumb', 'image');

        Livewire::test('bladewright::block-editor', ['block' => $photo])
            ->set('data.source', 'https://example.com/a.jpg')
            ->set('style.width', '12rem')
            ->set('style.aspect-ratio', '1 / 1')
            ->set('style.object-fit', 'cover')
            ->call('save');

        $this->assertStringContainsString(
            'style="width:12rem;object-fit:cover;aspect-ratio:1 / 1"',
            app(\Bladewright\Site\PublicSite::class)->block($photo->refresh()),
        );

        // A button is offered no object-fit: there is no picture to fit.
        $cta = app(BlockManager::class)->create('cta', 'button');
        $keys = array_column(app(BlockManager::class)->styleFieldsFor('button'), 'key');

        $this->assertNotContains('object-fit', $keys);
        $this->assertContains('line-height', $keys);

        // And a picture is offered no line-height: it has no lines.
        $this->assertNotContains('line-height', array_column(app(BlockManager::class)->styleFieldsFor('image'), 'key'));
    }

    /**
     * **The empty div is the measure of the Style card** — the maximum is
     * there, and the spreading questions wait for a picture to spread.
     */
    public function test_the_div_carries_the_maximum(): void
    {
        $this->actingAsRole();
        $block = app(BlockManager::class)->create('band', 'div');

        Livewire::test('bladewright::block-editor', ['block' => $block])
            ->set('style.min-height', '60vh')
            ->set('style.opacity', '0.9')
            ->set('style.overflow', 'hidden')
            ->set('style.text-transform', 'uppercase')
            ->set('style.letter-spacing', '0.05em')
            ->set('style.filter', 'grayscale(1)')
            ->set('style.transition', 'all 0.15s ease')
            ->set('style.background', "url('https://example.com/sky.png')")
            ->set('style.background-size', 'cover')
            ->set('style.background-position', 'center')
            ->call('save');

        $served = app(\Bladewright\Site\PublicSite::class)->block($block->refresh());

        foreach ([
            'min-height:60vh', 'opacity:0.9', 'overflow:hidden',
            'text-transform:uppercase', 'letter-spacing:0.05em',
            'filter:grayscale(1)', 'transition:all 0.15s ease',
            'background-size:cover', 'background-position:center',
        ] as $said) {
            $this->assertStringContainsString($said, $served);
        }

        // A junk preset never reaches the page.
        Livewire::test('bladewright::block-editor', ['block' => $block])
            ->set('style.filter', 'url(evil)')
            ->call('save');

        $this->assertArrayNotHasKey('filter', $block->refresh()->data['style']);
    }

    /** A media field is chosen from the library, not typed. */
    public function test_an_image_is_chosen_from_the_media(): void
    {
        $this->actingAsRole();
        $block = app(BlockManager::class)->create('photo', 'image');

        Livewire::test('bladewright::block-editor', ['block' => $block])
            ->call('pick', 'source')
            ->dispatch('bw-media-selected', path: 'bw/aa/bb/cc/logo.png')
            ->assertSet('data.source', 'bw/aa/bb/cc/logo.png')
            ->call('save');

        $this->assertSame('bw/aa/bb/cc/logo.png', $block->refresh()->data['source']);
    }

    /**
     * **What goes in a field is chosen, not spelled.** The words say what the
     * person filling it in is being asked for; the element gets the type,
     * and a phone gets the right keyboard with it.
     */
    public function test_an_input_is_told_what_goes_in_it(): void
    {
        $this->actingAsRole();
        $block = app(BlockManager::class)->create('email', 'input');

        $editor = Livewire::test('bladewright::block-editor', ['block' => $block])
            // It stands on one from the first minute — never an empty list.
            ->assertSet('data.type', 'text');

        $editor->set('data.label', '')
            ->set('data.name', 'email')
            ->set('data.type', 'email')
            ->set('data.placeholder', 'you@example.com')
            ->call('save');

        $this->assertSame(
            "<div>\n    <input type=\"email\" name=\"email\" placeholder=\"you@example.com\">\n</div>",
            app(\Bladewright\Site\PublicSite::class)->block($block->refresh()),
        );

        // A type nobody offered is not written onto the page.
        Livewire::test('bladewright::block-editor', ['block' => $block])
            ->set('data.type', 'anything-at-all')
            ->call('save');

        $this->assertSame('text', $block->refresh()->data['type']);
    }

    /**
     * **Only what was answered is written.** An attribute nobody asked for
     * changes how a browser behaves, so an empty box leaves none behind —
     * and `required` is there or it is not, never `required="0"`.
     */
    public function test_an_input_wears_only_the_attributes_it_was_given(): void
    {
        $this->actingAsRole();
        $block = app(BlockManager::class)->create('phone', 'input');

        Livewire::test('bladewright::block-editor', ['block' => $block])
            ->set('data.label', '')
            ->set('data.name', 'phone')
            ->set('data.type', 'tel')
            ->set('data.value', '090-')
            ->set('data.maxlength', '13')
            ->set('data.pattern', '[0-9-]+')
            // **What the browser says when it is not met** — without it the
            // message tells nobody what shape was wanted.
            ->set('data.title', '数字とハイフンだけ')
            ->set('data.required', true)
            ->set('data.disabled', true)
            ->call('save');

        $this->assertSame(
            "<div>\n    <input type=\"tel\" name=\"phone\" value=\"090-\" maxlength=\"13\" pattern=\"[0-9-]+\" title=\"数字とハイフンだけ\" required disabled>\n</div>",
            app(\Bladewright\Site\PublicSite::class)->block($block->refresh()),
        );
    }

    /**
     * **Asked for only where they mean something.** A pattern on a date and a
     * step on an email are not questions worth putting to anybody.
     */
    public function test_the_attributes_asked_for_follow_the_type(): void
    {
        $this->actingAsRole();
        $block = app(BlockManager::class)->create('when', 'input');

        $editor = Livewire::test('bladewright::block-editor', ['block' => $block]);

        $asked = fn () => array_column($editor->instance()->fields(), 'key');

        // Words: a length and a shape, no steps.
        $this->assertContains('maxlength', $asked());
        $this->assertNotContains('step', $asked());

        $editor->set('data.type', 'date');

        $this->assertContains('min', $asked());
        $this->assertNotContains('pattern', $asked());

        $editor->set('data.type', 'file');

        $this->assertContains('accept', $asked());
        $this->assertNotContains('min', $asked());
    }

    /**
     * **The code and the fields are the same block.** Change an attribute in
     * the code and the card says so at once — and while it is still this
     * block's own element, the fields go on leading it.
     */
    public function test_the_code_is_read_back_into_the_fields(): void
    {
        $this->actingAsRole();
        $block = app(BlockManager::class)->create('age', 'input');

        $editor = Livewire::test('bladewright::block-editor', ['block' => $block])
            ->set('data.type', 'number')
            ->set('data.name', 'age');

        // **The label is part of the field**, so writing one in the code
        // fills the box that holds it — and taking it out empties that box
        // rather than standing the two apart.
        $editor->set('markup', '<div><label>年齢 <input type="number" name="years"></label></div>')
            ->assertSet('data.label', '年齢')
            ->assertSet('mirrored', true);

        $editor->set('markup', '<div><input type="number" name="years"></div>')
            ->assertSet('data.label', '');

        // Written in the code, and read into the card.
        $editor->set('markup', '<div><input type="number" name="years" min="1" max="8" step="2" required></div>')
            ->assertSet('data.name', 'years')
            ->assertSet('data.min', '1')
            ->assertSet('data.max', '8')
            ->assertSet('data.required', '1')
            ->assertSet('mirrored', true);

        // Still the same block, so the fields still lead it.
        $editor->set('data.max', '10');

        $this->assertStringContainsString('max="10"', $editor->get('markup'));

        // The look travels with it, into the Style card.
        $editor->set('markup', '<div><input type="number" name="years" style="border-radius: 1rem"></div>')
            ->assertSet('style.border-radius', '1rem');

        // Written into something else, the code goes its own way.
        $editor->set('markup', '<div>@foreach ($rows as $row)<input type="number">@endforeach</div>')
            ->assertSet('mirrored', false);

        $editor->set('data.name', 'nothing-doing');

        $this->assertStringContainsString('@foreach', $editor->get('markup'));
    }

    /**
     * **A select is the same rows the choice groups have** — what is sent,
     * what is read, a row closed on its own — and `multiple` wears `[]` the
     * way a checkbox group does. The value is written only when it differs
     * from the words.
     */
    public function test_select_options_are_rows(): void
    {
        $this->actingAsRole();
        $block = app(BlockManager::class)->create('plan', 'select');

        // Born with three, so the dropdown drops something.
        $this->assertCount(3, $block->data['options']);

        Livewire::test('bladewright::block-editor', ['block' => $block])
            ->set('data.label', '')
            ->set('data.name', 'plan')
            ->set('data.options', [
                ['value' => 'free', 'label' => 'Freeプラン', 'disabled' => false],
                ['value' => 'Pro', 'label' => 'Pro', 'disabled' => true],
            ])
            ->set('data.required', true)
            ->set('data.multiple', true)
            ->call('save');

        $this->assertSame(
            "<div>\n    <select name=\"plan[]\" multiple required>\n        <option value=\"free\">Freeプラン</option>\n        <option disabled>Pro</option>\n    </select>\n</div>",
            app(\Bladewright\Site\PublicSite::class)->block($block->refresh()),
        );
    }

    /**
     * **A radio is several inputs sharing one name** — its own kind, one
     * input per choice, each inside its own label so the words are
     * pressable. `required` on the first speaks for the group.
     */
    public function test_a_radio_is_one_input_per_choice(): void
    {
        $this->actingAsRole();
        $block = app(BlockManager::class)->create('plan', 'radio');

        // **Born with a pair** — switching is the whole element, and
        // switching takes two. They are placeholders to rewrite.
        $this->assertSame(['option-1', 'option-2'], array_column($block->data['options'], 'value'));

        // A row is added and taken away from the screen.
        $editor = Livewire::test('bladewright::block-editor', ['block' => $block]);

        $editor->call('addChoice', 'options');
        $this->assertCount(3, $editor->get('data.options'));

        $editor->call('removeChoice', 'options', 2);
        $this->assertCount(2, $editor->get('data.options'));

        // One row, one choice; required is the group's, disabled the row's.
        Livewire::test('bladewright::block-editor', ['block' => $block])
            ->set('data.name', 'plan')
            ->set('data.required', true)
            ->set('data.options', [
                ['value' => 'free', 'label' => 'Freeプラン', 'disabled' => false],
                ['value' => 'pro', 'label' => 'Proプラン', 'disabled' => true],
            ])
            ->call('save');

        // No label of the group's own: every choice is its own.
        $this->assertSame(
            "<div>\n    <label><input type=\"radio\" name=\"plan\" value=\"free\" required> Freeプラン</label>\n    <label><input type=\"radio\" name=\"plan\" value=\"pro\" disabled> Proプラン</label>\n</div>",
            app(\Bladewright\Site\PublicSite::class)->block($block->refresh()),
        );
    }

    /**
     * **A checkbox is radio's pair: any of several.** Several share the name
     * as a list; a lone one is the ordinary "I agree" box, and `required`
     * is written only there — across several it would demand every one.
     */
    public function test_a_checkbox_group_and_a_lone_agreement(): void
    {
        $this->actingAsRole();
        $block = app(BlockManager::class)->create('interests', 'checkbox');

        // Born with one row: a lone box is the ordinary "I agree".
        $this->assertCount(1, $block->data['options']);

        $editor = Livewire::test('bladewright::block-editor', ['block' => $block])
            ->set('data.name', 'interests')
            ->set('data.options', [
                ['value' => 'design', 'label' => '設計', 'disabled' => false],
                ['value' => 'build', 'label' => '施工', 'disabled' => false],
            ])
            ->set('data.required', true)
            ->call('save');

        $served = app(\Bladewright\Site\PublicSite::class)->block($block->refresh());

        $this->assertStringContainsString('<label><input type="checkbox" name="interests[]" value="design"> 設計</label>', $served);
        $this->assertStringNotContainsString('required', $served);

        // One choice alone: no [], and required means "tick this".
        $editor->set('data.options', [
            ['value' => 'agree', 'label' => '利用規約に同意する', 'disabled' => false],
        ])->call('save');

        $this->assertStringContainsString(
            '<label><input type="checkbox" name="interests" value="agree" required> 利用規約に同意する</label>',
            app(\Bladewright\Site\PublicSite::class)->block($block->refresh()),
        );
    }

    /** Only the keys the type owns are stored. */
    public function test_foreign_keys_are_dropped_on_save(): void
    {
        $this->actingAsRole();
        $block = app(BlockManager::class)->create('cta', 'button');

        Livewire::test('bladewright::block-editor', ['block' => $block])
            ->set('data.label', '始める')
            ->set('data.type', 'submit')
            ->call('save');

        // `markup` and `style` belong to every block, whatever its type.
        $this->assertSame(['markup', 'style', 'class', 'label', 'type', 'url'], array_keys($block->refresh()->data));
    }

    /** The editor carries a gear to the settings, where the danger lives. */
    public function test_the_gear_leads_to_the_settings(): void
    {
        $this->actingAsRole();
        $block = app(BlockManager::class)->create('intro', 'markdown');

        $this->get(route('bladewright.admin.blocks.edit', $block))
            ->assertOk()
            ->assertSee(route('bladewright.admin.blocks.settings', $block), false)
            ->assertDontSee('Danger zone', false);

        $this->get(route('bladewright.admin.blocks.settings', $block))
            ->assertOk()
            ->assertSee('Danger zone', false);
    }

    /** Renaming from the settings keeps the uuid; deleting sweeps and leaves. */
    public function test_settings_rename_and_delete(): void
    {
        $this->actingAsRole();
        $block = app(BlockManager::class)->create('intro', 'markdown');
        $uuid = $block->uuid;
        $hero = app(StructureManager::class)->create('hero', 'section');
        app(StructureManager::class)->insertBlock($hero, $block);

        Livewire::test('bladewright::block-settings', ['block' => $block])
            ->set('name', 'welcome')
            ->call('saveName')
            ->assertToast('The name has changed');

        $this->assertSame($uuid, $block->refresh()->uuid);
        $this->assertSame('welcome', $block->name);

        Livewire::test('bladewright::block-settings', ['block' => $block])
            ->call('destroy')
            ->assertRedirect(route('bladewright.admin.blocks'));

        $this->assertSame(0, Block::query()->count());
        $this->assertSame(0, \Bladewright\Models\StructureChild::query()->count());
    }

    /**
     * **The Class card is the class attribute's mirror**, the way Style is
     * the style attribute's — Bootstrap's words from a card, no Code pill
     * needed. It lands where the look lands: the element for most, the box
     * for a group.
     */
    public function test_the_class_card_dresses_the_element(): void
    {
        $this->actingAsRole();
        $block = app(BlockManager::class)->create('cta', 'button');

        $editor = Livewire::test('bladewright::block-editor', ['block' => $block])
            ->set('class', 'btn btn-primary');

        $this->assertStringContainsString('class="btn btn-primary"', $editor->get('markup'));

        $editor->call('save');

        $this->assertSame('btn btn-primary', $block->refresh()->data['class']);
        $this->assertStringContainsString('class="btn btn-primary"', app(\Bladewright\Site\PublicSite::class)->block($block));

        // A group has no one element, so the box wears the class too.
        $group = app(BlockManager::class)->create('plan', 'radio');

        $boxed = Livewire::test('bladewright::block-editor', ['block' => $group])
            ->set('class', 'form-check');

        $this->assertStringContainsString('<div class="form-check"', $boxed->get('markup'));
    }

    /**
     * The class is the cards' own now, both ways: written in the code it
     * fills the card, and the card patches it back — **while everything the
     * cards never grew a control for stands exactly as written.**
     */
    public function test_a_written_class_reads_back_and_is_patched(): void
    {
        $this->actingAsRole();
        $block = app(BlockManager::class)->create('cta', 'button');

        $editor = Livewire::test('bladewright::block-editor', ['block' => $block])
            ->set('markup', '<div><button type="button" class="btn" aria-live="polite">Go</button></div>')
            ->assertSet('class', 'btn');

        $editor->set('class', 'btn btn-lg');

        $this->assertStringContainsString('class="btn btn-lg"', $editor->get('markup'));
        $this->assertStringContainsString('aria-live="polite"', $editor->get('markup'));

        // Nothing that could close the attribute ever reaches the page.
        $editor->set('class', '"onmouseover="x')->call('save');

        $this->assertSame('', $block->refresh()->data['class']);
    }
}
