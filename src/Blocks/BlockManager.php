<?php

namespace Bladewright\Blocks;

use InvalidArgumentException;
use Bladewright\Models\Block;

/**
 * Creating, copying, renaming and deleting blocks. **The rule lives here,
 * once** — the command and the screens both come through.
 *
 * A block is addressed by its name, so the name is unique and never empty;
 * what uses a block holds its uuid, so renaming is safe exactly because it
 * changes nothing but the word people say.
 */
class BlockManager
{
    public function find(string $name): ?Block
    {
        return Block::query()->where('name', trim($name))->first();
    }

    public function create(string $name, string $type): Block
    {
        $name = $this->assertNameIsFree($name);

        if (! in_array($type, Block::TYPES, true)) {
            throw new InvalidArgumentException(__('[:type] is not a block type. One of: :types.', [
                'type' => $type,
                'types' => implode(', ', Block::TYPES),
            ]));
        }

        // **The content comes later, on the screens.** The commands build the
        // skeleton; screen work is too fine for a terminal.
        //
        // A field is born with its label written — **a field nobody named
        // cannot be used**, and emptying the box is easier than remembering
        // to fill it.
        $data = in_array($type, self::FIELDS, true) ? ['label' => $name] : [];

        // A flag marked `on` is born ticked: a player without controls is a
        // picture nobody can press.
        foreach ($this->fieldsFor($type) as $field) {
            if (($field['on'] ?? false) === true && $field['kind'] === 'flag') {
                $data[$field['key']] = '1';
            }
        }

        // **A radio means nothing alone** — switching is the whole element,
        // and switching takes two. Born with a pair to rewrite; a checkbox
        // is meaningful by itself, so it is born with one.
        if ($type === 'radio') {
            $data['options'] = [
                ['value' => 'option-1', 'label' => 'Option 1'],
                ['value' => 'option-2', 'label' => 'Option 2'],
            ];
        } elseif ($type === 'checkbox') {
            $data['options'] = [['value' => 'option-1', 'label' => 'Option 1']];
        } elseif ($type === 'select') {
            $data['options'] = [
                ['value' => 'option-1', 'label' => 'Option 1'],
                ['value' => 'option-2', 'label' => 'Option 2'],
                ['value' => 'option-3', 'label' => 'Option 3'],
            ];
        }

        return Block::create(['name' => $name, 'type' => $type, 'data' => $data]);
    }

    /** The name asked for, or the first one after it that nobody has. */
    public function freeName(string $wanted): string
    {
        $name = trim($wanted);
        $try = $name;
        $n = 1;

        while (Block::query()->where('name', $try)->exists()) {
            $try = $name.'-'.(++$n);
        }

        return $try;
    }

    /**
     * One more like it, under its own name.
     *
     * **This is how a block is made to diverge.** Editing the original
     * reaches every place that uses it; the copy is its own thing from birth.
     */
    public function copy(Block $block, string $to): Block
    {
        $to = $this->assertNameIsFree($to);

        return Block::create(['name' => $to, 'type' => $block->type, 'data' => $block->data]);
    }

    /**
     * Change what it is called. **Nothing that uses it notices** — they hold
     * the uuid, not the word.
     */
    public function rename(Block $block, string $to): Block
    {
        $to = $this->assertNameIsFree($to);

        $block->forceFill(['name' => $to])->save();

        return $block;
    }

    /**
     * What each type holds, and how it is edited. **One list, one place** —
     * the editor draws its form from this, and `Site\PublicSite` reads the
     * same keys when it renders.
     *
     * **The labels are the names the HTML uses**, not sentences about them:
     * whoever fills this in can read the Code pill beside it, and a card
     * that says `maxlength` next to a box is read in one glance where "at
     * most this many letters" has to be parsed.
     *
     * kinds: markdown / text / media / options / choice
     *
     * A field may carry `when` — **it is only asked for when another field
     * says so**, so nothing is on screen that has no bearing on the block.
     * Its value may be one thing or a list of them.
     *
     * @return array<int, array{key: string, kind: string, label: string, choices?: array<string, string>, when?: array<string, string>}>
     */
    public function fieldsFor(string $type): array
    {
        return match ($type) {
            'markdown' => [
                ['key' => 'body', 'kind' => 'markdown', 'label' => __('body')],
            ],
            'image' => [
                // `accept` narrows the picker: a src field for a picture has
                // no business offering films.
                ['key' => 'source', 'kind' => 'media', 'accept' => 'image', 'label' => __('src')],
                ['key' => 'alt', 'kind' => 'text', 'label' => __('alt')],
                // Said in pixels, so the page keeps the room before the file
                // arrives and nothing jumps.
                ['key' => 'width', 'kind' => 'text', 'label' => __('width'), 'placeholder' => '800', 'row' => 'size'],
                ['key' => 'height', 'kind' => 'text', 'label' => __('height'), 'placeholder' => '600', 'row' => 'size'],
                // Pressed, it goes somewhere: the picture wrapped in a link.
                ['key' => 'href', 'kind' => 'text', 'label' => __('href'), 'placeholder' => '/about'],
            ],
            'video' => [
                ['key' => 'source', 'kind' => 'media', 'accept' => 'video', 'label' => __('src')],
                // The picture shown before anybody presses play. **The card
                // says thumbnail** — the one label that is not the HTML's own
                // word, because nobody outside a spec says poster.
                ['key' => 'poster', 'kind' => 'media', 'accept' => 'image', 'label' => __('thumbnail')],
                ['key' => 'width', 'kind' => 'text', 'label' => __('width'), 'placeholder' => '1280', 'row' => 'size'],
                ['key' => 'height', 'kind' => 'text', 'label' => __('height'), 'placeholder' => '720', 'row' => 'size'],
                ['key' => 'controls', 'kind' => 'flag', 'label' => __('controls'), 'row' => 'flags', 'on' => true],
                ['key' => 'autoplay', 'kind' => 'flag', 'label' => __('autoplay'), 'row' => 'flags'],
                ['key' => 'loop', 'kind' => 'flag', 'label' => __('loop'), 'row' => 'flags'],
                ['key' => 'muted', 'kind' => 'flag', 'label' => __('muted'), 'row' => 'flags'],
                // Without it an iPhone takes the film fullscreen the moment
                // it plays — a background loop is impossible without it.
                ['key' => 'playsinline', 'kind' => 'flag', 'label' => __('playsinline'), 'row' => 'flags2'],
                // What is fetched before anybody presses play.
                ['key' => 'preload', 'kind' => 'choice', 'label' => __('preload'), 'choices' => [
                    '' => __('As it comes'),
                    'none' => __('Nothing'),
                    'metadata' => __('Length and size only'),
                    'auto' => __('The film itself'),
                ]],
            ],
            'audio' => [
                ['key' => 'source', 'kind' => 'media', 'accept' => 'audio', 'label' => __('src')],
                ['key' => 'controls', 'kind' => 'flag', 'label' => __('controls'), 'row' => 'flags', 'on' => true],
                ['key' => 'autoplay', 'kind' => 'flag', 'label' => __('autoplay'), 'row' => 'flags'],
                ['key' => 'loop', 'kind' => 'flag', 'label' => __('loop'), 'row' => 'flags'],
                ['key' => 'muted', 'kind' => 'flag', 'label' => __('muted'), 'row' => 'flags'],
                // What is fetched before anybody presses play — a page of
                // episodes should not download them all on arrival.
                ['key' => 'preload', 'kind' => 'choice', 'label' => __('preload'), 'choices' => [
                    '' => __('As it comes'),
                    'none' => __('Nothing'),
                    'metadata' => __('Length only'),
                    'auto' => __('The sound itself'),
                ]],
            ],
            'button' => [
                ['key' => 'label', 'kind' => 'text', 'label' => __('text')],
                // **A closed set is chosen from, not typed.** The values are
                // the element's own; the words beside them say what happens.
                ['key' => 'type', 'kind' => 'choice', 'label' => __('type'), 'choices' => [
                    'button' => __('Nothing on its own'),
                    'submit' => __('Sends the form'),
                    'reset' => __('Clears the form'),
                    'link' => __('Goes to a URL'),
                ]],
                ['key' => 'url', 'kind' => 'text', 'label' => __('url'), 'when' => ['type' => 'link']],
            ],
            'input' => [
                // **The label is part of the field**, not a block beside it:
                // one is no use without the other, and nobody should have to
                // remember to place two things that are one thing. It wraps
                // the control, so no ids are minted to tie the two — pressing
                // the words reaches the box all the same. A hidden field is
                // the one that has no reader to name it for.
                ['key' => 'label', 'kind' => 'text', 'label' => __('label'),
                    'when' => ['type' => ['text', 'email', 'tel', 'url', 'number', 'date', 'time', 'password', 'search', 'file']]],
                ['key' => 'name', 'kind' => 'text', 'label' => __('name')],
                // **Chosen, not spelled.** The words say what the person
                // filling it in is being asked for; the value is the
                // element's own, and the keyboard a phone offers follows it.
                ['key' => 'type', 'kind' => 'choice', 'label' => __('type'), 'choices' => [
                    'text' => __('Words'),
                    'email' => __('An email address'),
                    'tel' => __('A telephone number'),
                    'url' => __('A web address'),
                    'number' => __('A number'),
                    'date' => __('A date'),
                    'time' => __('A time'),
                    'password' => __('A password (hidden as it is typed)'),
                    'search' => __('Something to search for'),
                    'file' => __('A file'),
                    'hidden' => __('Nothing — carried along unseen'),
                ]],
                // **Asked for only where a browser would show it.** A date
                // or a time draws its own picker; a checkbox has no box to
                // write faint words into.
                ['key' => 'placeholder', 'kind' => 'text', 'label' => __('placeholder'),
                    'when' => ['type' => ['text', 'email', 'tel', 'url', 'search', 'password', 'number']]],
                ['key' => 'value', 'kind' => 'text', 'label' => __('value'),
                    'when' => ['type' => ['text', 'email', 'tel', 'url', 'search', 'password', 'number', 'date', 'time', 'hidden']]],
                // Side by side: two little answers are not worth two rows.
                ['key' => 'required', 'kind' => 'flag', 'label' => __('required'), 'row' => 'flags'],
                ['key' => 'disabled', 'kind' => 'flag', 'label' => __('disabled'), 'row' => 'flags'],
                // **Asked for only where they mean something.** A pattern on
                // a date, a step on an email: neither is a question worth
                // putting to anybody.
                // A password below a floor is no password at all — but the
                // floor is any written type's to set.
                ['key' => 'minlength', 'kind' => 'text', 'label' => __('minlength'), 'placeholder' => '8', 'row' => 'shape',
                    'when' => ['type' => ['text', 'email', 'tel', 'url', 'search', 'password']]],
                ['key' => 'maxlength', 'kind' => 'text', 'label' => __('maxlength'), 'placeholder' => '60', 'row' => 'shape',
                    'when' => ['type' => ['text', 'email', 'tel', 'url', 'search', 'password']]],
                ['key' => 'pattern', 'kind' => 'text', 'label' => __('pattern'), 'placeholder' => '[0-9]{3}-[0-9]{4}', 'row' => 'shape',
                    'when' => ['type' => ['text', 'email', 'tel', 'url', 'search', 'password']]],
                // **What the browser says when the pattern is not met.**
                // Without it the message is the browser's own, which tells
                // nobody what shape was wanted.
                ['key' => 'title', 'kind' => 'text', 'label' => __('title'), 'placeholder' => __('Three digits, a dash, four digits'),
                    'when' => ['type' => ['text', 'email', 'tel', 'url', 'search', 'password']]],
                // Three of a kind, said in one line. **The empty box shows
                // the shape**, because a date's bounds are written as a date
                // and a time's as a time, and nobody should have to guess —
                // step counts days for a date and seconds for a time.
                ['key' => 'min', 'kind' => 'text', 'label' => __('min'), 'row' => 'range', 'when' => ['type' => ['number', 'date', 'time']],
                    'placeholders' => ['number' => '0', 'date' => '2026-01-01', 'time' => '09:00']],
                ['key' => 'max', 'kind' => 'text', 'label' => __('max'), 'row' => 'range', 'when' => ['type' => ['number', 'date', 'time']],
                    'placeholders' => ['number' => '100', 'date' => '2026-12-31', 'time' => '18:00']],
                ['key' => 'step', 'kind' => 'text', 'label' => __('step'), 'row' => 'range', 'when' => ['type' => ['number', 'date', 'time']],
                    'placeholders' => ['number' => '1', 'date' => '7', 'time' => '900']],
                ['key' => 'accept', 'kind' => 'text', 'label' => __('accept'), 'placeholder' => 'image/*', 'when' => ['type' => ['file']]],
            ],
            'textarea' => [
                ['key' => 'label', 'kind' => 'text', 'label' => __('label')],
                ['key' => 'name', 'kind' => 'text', 'label' => __('name')],
                ['key' => 'placeholder', 'kind' => 'text', 'label' => __('placeholder')],
                ['key' => 'value', 'kind' => 'text', 'label' => __('value')],
                // How tall it opens — the element's own count of lines.
                ['key' => 'rows', 'kind' => 'text', 'label' => __('rows'), 'placeholder' => '4', 'row' => 'shape'],
                ['key' => 'minlength', 'kind' => 'text', 'label' => __('minlength'), 'placeholder' => '10', 'row' => 'shape'],
                ['key' => 'maxlength', 'kind' => 'text', 'label' => __('maxlength'), 'placeholder' => '500', 'row' => 'shape'],
                ['key' => 'required', 'kind' => 'flag', 'label' => __('required'), 'row' => 'flags'],
                ['key' => 'disabled', 'kind' => 'flag', 'label' => __('disabled'), 'row' => 'flags'],
            ],
            'select' => [
                ['key' => 'label', 'kind' => 'text', 'label' => __('label')],
                ['key' => 'name', 'kind' => 'text', 'label' => __('name')],
                // The same rows the choice groups have: what is sent, what
                // is read, and a row can be closed on its own.
                ['key' => 'options', 'kind' => 'pairs', 'label' => __('options')],
                ['key' => 'required', 'kind' => 'flag', 'label' => __('required'), 'row' => 'flags'],
                ['key' => 'disabled', 'kind' => 'flag', 'label' => __('disabled'), 'row' => 'flags'],
                // Several at once — the element's own way (held ⌘/Ctrl), so
                // offered but not dressed up.
                ['key' => 'multiple', 'kind' => 'flag', 'label' => __('multiple'), 'row' => 'flags'],
            ],
            // **Any of several** — one input per choice, ticked in any
            // number. One choice alone is the ordinary "I agree" box, which
            // is why a lone yes-or-no is not a kind of input any more.
            'checkbox' => [
                // The same shape as a radio: no label of the group's own —
                // every choice is its own label, and a heading is a Markdown
                // block's job. One name, value-and-label rows, required for
                // the group (written only where one box can honour it).
                ['key' => 'name', 'kind' => 'text', 'label' => __('name')],
                ['key' => 'options', 'kind' => 'pairs', 'label' => __('options')],
                ['key' => 'required', 'kind' => 'flag', 'label' => __('required')],
            ],
            // **One of several** — one per choice, which is the only way a
            // radio means anything. Its own kind, as a select is: a radio
            // inside `input` sat one element deep in a type made for one.
            'radio' => [
                // No label of the group's own: **every choice is its own
                // label already**, and a heading over them is a Markdown
                // block's job. One name for the group; each choice is a
                // value-and-label pair of its own.
                ['key' => 'name', 'kind' => 'text', 'label' => __('name')],
                ['key' => 'options', 'kind' => 'pairs', 'label' => __('options')],
                // **Required is the group's**: the element treats one marked
                // radio as "one of these", so asking per row would mislead.
                // Disabled stays on the row — a single choice can be closed.
                ['key' => 'required', 'kind' => 'flag', 'label' => __('required')],
            ],
            // A window onto somebody else's page: a YouTube video, a map, a
            // calendar. Their share buttons hand out the src.
            // **Nothing to fill in**: a div is the blank page of the types,
            // and everything it says is written on the Code pill.
            'div' => [],
            'embed' => [
                ['key' => 'url', 'kind' => 'text', 'label' => __('src'), 'placeholder' => 'https://www.youtube.com/embed/…'],
                ['key' => 'title', 'kind' => 'text', 'label' => __('title')],
                // A bare iframe is a 300×150 hole; a size is part of meaning it.
                ['key' => 'width', 'kind' => 'text', 'label' => __('width'), 'placeholder' => '560', 'row' => 'size'],
                ['key' => 'height', 'kind' => 'text', 'label' => __('height'), 'placeholder' => '315', 'row' => 'size'],
            ],
            default => [],
        };
    }

    /**
     * How a block looks. **The block's own appearance, not its spacing** —
     * what stands between one block and the next is the component's, and
     * lives there alone. This is the thing itself: its colour, its size, the
     * weight of its words, the round of its corners.
     *
     * One list, as with the contents: the editor draws the card from it and
     * `Site\PublicSite` writes the same keys into a style attribute.
     *
     * A field marked `box` is written on the block's box rather than on the
     * element, because that is the only thing that can answer for it.
     *
     * @return array<int, array{key: string, css: ?string, kind: string, label: string, box?: bool, on?: string, letter?: string, sides?: bool, slider?: array<string, mixed>, choices?: array<string, string>, placeholder?: string}>
     */
    public function styleFields(): array
    {
        return [
            // Shown as an icon in the top row, the way a writing tool shows
            // them: the letter, and the paint, each wearing what it is set to.
            ['key' => 'color', 'words' => true, 'css' => 'color', 'kind' => 'colour', 'icon' => 'text', 'label' => __('color')],
            // **`background`, not `background-color`**: it takes a gradient
            // as readily as a colour, and a palette entry may be either.
            ['key' => 'background', 'css' => 'background', 'kind' => 'colour', 'icon' => 'fill', 'image' => true, 'label' => __('background')],
            // How a picture background spreads. **Asked only while one is
            // chosen** — a colour has no spreading to speak of.
            ['key' => 'background-size', 'css' => 'background-size', 'kind' => 'choice', 'needsImage' => 'background', 'label' => __('background-size'), 'choices' => [
                '' => __('As it comes'),
                'cover' => __('Fill the box, crop the rest'),
                'contain' => __('Fit inside, leave room'),
            ]],
            ['key' => 'background-position', 'css' => 'background-position', 'kind' => 'choice', 'needsImage' => 'background', 'label' => __('background-position'), 'choices' => [
                '' => __('As it comes'),
                'center' => __('Middle'),
                'top' => __('Top'),
                'bottom' => __('Bottom'),
                'left' => __('Left'),
                'right' => __('Right'),
            ]],
            ['key' => 'background-repeat', 'css' => 'background-repeat', 'kind' => 'choice', 'needsImage' => 'background', 'label' => __('background-repeat'), 'choices' => [
                '' => __('As it comes'),
                'no-repeat' => __('Once'),
                'repeat' => __('Tiled'),
            ]],
            // Judged by looking, like the corners — and anything the slider
            // cannot say is still typed into the box beside it.
            ['key' => 'line-height', 'words' => true, 'css' => 'line-height', 'kind' => 'size', 'label' => __('line-height'), 'placeholder' => '1.7',
                'slider' => ['max' => 3, 'step' => 0.05, 'unit' => '']],
            ['key' => 'letter-spacing', 'words' => true, 'css' => 'letter-spacing', 'kind' => 'size', 'label' => __('letter-spacing'), 'placeholder' => '0.05em',
                'slider' => ['max' => 0.5, 'step' => 0.01, 'unit' => 'em']],
            ['key' => 'text-transform', 'words' => true, 'css' => 'text-transform', 'kind' => 'choice', 'label' => __('text-transform'), 'choices' => [
                '' => __('As written'),
                'uppercase' => __('ALL CAPITALS'),
                'capitalize' => __('First Letters Up'),
                'lowercase' => __('all small'),
                'none' => __('Undo an inherited one'),
            ]],
            ['key' => 'font-size', 'words' => true, 'css' => 'font-size', 'kind' => 'size', 'label' => __('font-size'), 'placeholder' => '1.125rem',
                'slider' => ['max' => 4, 'step' => 0.0625, 'unit' => 'rem']],
            // **The four a writer already knows**, pressed on and off rather
            // than chosen from a list. Underline and strike write the same
            // CSS property, and are joined when both are on.
            ['key' => 'bold', 'words' => true, 'css' => 'font-weight', 'kind' => 'switch', 'on' => '700', 'label' => __('Bold'), 'letter' => 'B'],
            ['key' => 'italic', 'words' => true, 'css' => 'font-style', 'kind' => 'switch', 'on' => 'italic', 'label' => __('Italic'), 'letter' => 'I'],
            ['key' => 'underline', 'words' => true, 'css' => 'text-decoration', 'kind' => 'switch', 'on' => 'underline', 'label' => __('Underline'), 'letter' => 'U'],
            ['key' => 'strike', 'words' => true, 'css' => 'text-decoration', 'kind' => 'switch', 'on' => 'line-through', 'label' => __('Strikethrough'), 'letter' => 'S'],
            // How much room the element itself takes. `100%` is the whole
            // of its box — the full-width switch, spelled as what it is.
            ['key' => 'width', 'css' => 'width', 'kind' => 'size', 'label' => __('width'), 'placeholder' => '100%', 'row' => 'dims'],
            ['key' => 'height', 'css' => 'height', 'kind' => 'size', 'label' => __('height'), 'placeholder' => '20rem', 'row' => 'dims'],
            // "At least this tall" — the hero band's word, safer than height
            // because the words can still grow.
            ['key' => 'min-height', 'css' => 'min-height', 'kind' => 'size', 'label' => __('min-height'), 'placeholder' => '60vh', 'row' => 'dims'],
            // **How a picture fills a box that is not its shape** — squaring
            // thumbnails is impossible without it. Only where there is a
            // picture to fit.
            ['key' => 'object-fit', 'css' => 'object-fit', 'kind' => 'choice', 'label' => __('object-fit'), 'only' => ['image', 'video'], 'choices' => [
                '' => __('As it comes'),
                'cover' => __('Fill the box, crop the rest'),
                'contain' => __('Fit inside, leave room'),
                'fill' => __('Stretch to the box'),
            ]],
            ['key' => 'aspect-ratio', 'css' => 'aspect-ratio', 'kind' => 'size', 'label' => __('aspect-ratio'), 'placeholder' => '16 / 9',
                'only' => ['image', 'video', 'embed', 'div']],
            // **Four sides, in a box.** Padding is read as a shape — this
            // much above, that much beside — so it is edited as one, the way
            // a browser's own inspector shows it.
            ['key' => 'padding', 'css' => 'padding', 'kind' => 'size', 'label' => __('padding'), 'placeholder' => '0.75rem 1.25rem', 'sides' => true],
            // **A size you can feel out.** Roundness is judged by looking,
            // not by typing a number, so it carries a slider as well — the
            // box beside it is still what is stored, and takes anything.
            ['key' => 'border-radius', 'css' => 'border-radius', 'kind' => 'size', 'label' => __('border-radius'), 'placeholder' => '0.5rem',
                'slider' => ['max' => 3, 'step' => 0.125, 'unit' => 'rem']],
            // **A border is three answers**: how thick, what colour, and
            // which sides. They are written out together, because a side
            // with no thickness is not a border at all.
            ['key' => 'border-width', 'css' => null, 'kind' => 'size', 'label' => __('border'), 'placeholder' => '1px',
                'slider' => ['max' => 8, 'step' => 1, 'unit' => 'px']],
            // Its icon stands in the border's own row: a colour is read
            // beside the thing it paints, not in a list of its own.
            ['key' => 'border-color', 'css' => null, 'kind' => 'colour', 'icon' => 'line', 'with' => 'border-width', 'label' => __('border-color')],
            ['key' => 'border-sides', 'css' => null, 'kind' => 'sides-set', 'label' => __('sides')],
            ['key' => 'opacity', 'css' => 'opacity', 'kind' => 'size', 'label' => __('opacity'), 'placeholder' => '1',
                'slider' => ['max' => 1, 'step' => 0.05, 'unit' => '']],
            // What happens where the inside runs past the box — hidden is
            // how a rounded corner actually cuts.
            ['key' => 'overflow', 'css' => 'overflow', 'kind' => 'choice', 'label' => __('overflow'), 'choices' => [
                '' => __('As it comes'),
                'hidden' => __('Cut it off'),
                'auto' => __('Scroll when it must'),
                'scroll' => __('Scroll always'),
            ]],
            ['key' => 'filter', 'css' => 'filter', 'kind' => 'choice', 'label' => __('filter'), 'choices' => [
                '' => __('As it comes'),
                'grayscale(1)' => __('Black and white'),
                'blur(4px)' => __('Blurred'),
                'brightness(0.7)' => __('Darkened'),
                'brightness(1.2)' => __('Brightened'),
                'none' => __('Undo an inherited one'),
            ]],
            // Hover written in the stylesheet lands softly with this on.
            ['key' => 'transition', 'css' => 'transition', 'kind' => 'choice', 'label' => __('transition'), 'choices' => [
                '' => __('As it comes'),
                'all 0.15s ease' => __('Quick'),
                'all 0.3s ease' => __('Gentle'),
                'none' => __('None'),
            ]],
            // **The pointer's state, not the element's.** A hover cannot be
            // said in a style attribute, so these never join one: the
            // renderer collects them into the document's own `<style>`,
            // addressed by a machine class of the part's — which is also why
            // `state` marks them: the CSS pill speaks only for the attribute,
            // and must not empty what it cannot say.
            ['key' => 'hover-background', 'css' => null, 'state' => 'hover', 'kind' => 'colour', 'label' => __('hover background')],
            ['key' => 'hover-color', 'words' => true, 'css' => null, 'state' => 'hover', 'kind' => 'colour', 'label' => __('hover color')],
            ['key' => 'hover-opacity', 'css' => null, 'state' => 'hover', 'kind' => 'size', 'label' => __('hover opacity'), 'placeholder' => '0.85',
                'slider' => ['max' => 1, 'step' => 0.05, 'unit' => '']],
            ['key' => 'box-shadow', 'css' => 'box-shadow', 'kind' => 'choice', 'label' => __('box-shadow'), 'choices' => [
                '' => __('As it comes'),
                '0 1px 2px rgb(0 0 0 / .06)' => __('Soft'),
                '0 4px 12px rgb(0 0 0 / .10)' => __('Lifted'),
                '0 12px 30px rgb(0 0 0 / .18)' => __('Deep'),
                'none' => __('No shadow'),
            ]],
            // **On the box, not the element.** Where a button sits is not
            // the button's business: it is where it stands in its own box,
            // and the box is the only thing that can answer for that.
            // Pressed rather than chosen from a list: three places, and
            // pressing the lit one again is "as it comes" back.
            ['key' => 'align', 'css' => 'text-align', 'kind' => 'choice', 'box' => true, 'pills' => true, 'label' => __('text-align'), 'choices' => [
                '' => __('As it comes'),
                'left' => __('Left'),
                'center' => __('Middle'),
                'right' => __('Right'),
            ]],
        ];
    }

    /**
     * What of a style is kept. **Nothing but what reads as what it says.**
     *
     * A style attribute is not a place to relay whatever was stored: a
     * colour is a colour, a size is a size, and anything else is dropped
     * rather than written out.
     *
     * @param  array<string, mixed>  $style
     * @return array<string, string>
     */
    public function sanitizeStyle(array $style): array
    {
        $kept = [];

        // **What the fields cannot say, written by hand.** The controls make
        // most of it; anything else is declarations typed on the CSS pill,
        // kept as they stand and written after ours, so a hand overrules a
        // press. Nothing but `<` is refused: whoever opens this screen can
        // already write Blade.
        $css = trim((string) ($style['css'] ?? ''));

        if ($css !== '' && ! str_contains($css, '<')) {
            $kept['css'] = rtrim($css, "; \n\t");
        }

        foreach ($this->styleFields() as $field) {
            $value = trim((string) ($style[$field['key']] ?? ''));

            if ($value === '') {
                continue;
            }

            $keep = match ($field['kind']) {
                // A name from the site's palette, #abc, #aabbcc, or a colour
                // word. **The name is preferred** — it is resolved when the
                // page is rendered, so changing the palette changes the site.
                'colour' => $this->colourReads($field['key'], $value),
                'choice' => isset($field['choices'][$value]),
                // A switch is on or it is not there at all.
                'switch' => $value === $field['on'],
                'sides-set' => $this->sidesRead($value),
                // Lengths, the words a border is written with, and the
                // functions CSS writes them with — `clamp(…)`, `calc(…)`.
                // **Never a semicolon**: one field is one value, not a way to
                // write a second property into somebody's style attribute.
                default => ! str_contains($value, ';') && preg_match('/^[0-9a-z.%#,()\/ -]+$/i', $value) === 1,
            };

            if ($keep) {
                $kept[$field['key']] = $value;
            }
        }

        return $kept;
    }

    /**
     * The class attribute, whole — **one card, one attribute**.
     *
     * Words only: nothing that could close the attribute or open a tag
     * ever reaches the page, and runs of whitespace fold to one space.
     */
    public function sanitizeClass(string $class): string
    {
        $class = trim((string) preg_replace('/\s+/', ' ', $class));

        // **The machine's classes are the renderer's to give, never stored**
        // — read back from generated code they would double up, and pin a
        // part to a hash it no longer has.
        $class = trim((string) preg_replace('/(?:^| )bw-[0-9a-f]{8}(?:-in)?(?= |$)/', '', $class));

        return preg_match('/^[^"\'<>{}]*$/', $class) === 1 ? $class : '';
    }

    /** The types with no words of their own to dress. */
    public const WORDLESS = ['image', 'video', 'audio', 'embed'];

    /**
     * The style fields that bear on this type.
     *
     * **A picture has no words**, so bold, italic, text colour and size are
     * not questions worth putting to one — where it sits, its padding, its
     * border and its shadow still are.
     *
     * @return array<int, array<string, mixed>>
     */
    public function styleFieldsFor(string $type): array
    {
        return array_values(array_filter(
            $this->styleFields(),
            fn ($field) => (! in_array($type, self::WORDLESS, true) || ! ($field['words'] ?? false))
                && (! isset($field['only']) || in_array($type, $field['only'], true)),
        ));
    }

    /** The types that carry the label naming them. */
    public const FIELDS = ['input', 'textarea', 'select'];

    /** The sides of a box, named. */
    public const SIDES = ['top', 'right', 'bottom', 'left'];

    /** Is this a list of sides, and nothing else? */
    private function sidesRead(string $value): bool
    {
        $sides = preg_split('/\s+/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return $sides !== []
            && $sides === array_unique($sides)
            && array_diff($sides, self::SIDES) === [];
    }

    /**
     * The border, written out. **One rule, in one place**, because three
     * fields make it and none of them means anything alone.
     *
     * @param  array<string, string>  $style
     * @return array<string, string>
     */
    public function borderDeclarations(array $style, callable $colour): array
    {
        $width = trim((string) ($style['border-width'] ?? ''));
        $ink = trim((string) ($style['border-color'] ?? ''));

        if ($width === '' && $ink === '') {
            return [];
        }

        // A border with no thickness said is a hairline, which is what
        // anybody drawing one means by leaving it empty.
        $line = ($width === '' ? '1px' : $width).' solid'.($ink === '' ? '' : ' '.$colour($ink));

        $sides = preg_split('/\s+/', trim((string) ($style['border-sides'] ?? '')), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($sides === [] || count($sides) === 4) {
            return ['border' => $line];
        }

        $out = [];

        foreach (self::SIDES as $side) {
            if (in_array($side, $sides, true)) {
                $out['border-'.$side] = $line;
            }
        }

        return $out;
    }

    /**
     * Does this colour read as one, for the field it is in?
     *
     * **A gradient is a background and nothing else** — there is no such
     * thing as gradient text without a trick, and a field that quietly did
     * nothing would be worse than one that refuses.
     */
    private function colourReads(string $key, string $value): bool
    {
        // **A background may be a picture.** One url(...) and nothing else —
        // never a second declaration smuggled in behind it.
        if ($key === 'background' && preg_match('/^url\\((\'|")?[^\'");]+\\1?\\)$/', $value) === 1) {
            return true;
        }

        $palette = app(\Bladewright\Support\Palette::class);

        if ($palette->isGradient($value) && $key !== 'background') {
            return false;
        }

        if ($palette->isName($value)) {
            return true;
        }

        return preg_match('/^#[0-9a-f]{3,8}$/i', $value) === 1
            || preg_match('/^[a-z]+$/i', $value) === 1;
    }

    /**
     * Save what a block says. **Only the keys its type owns** — whatever
     * else arrives is dropped rather than stored.
     *
     * @param  array<string, mixed>  $data
     */
    public function saveContent(Block $block, array $data): Block
    {
        $kept = [];

        // **The hand-written markup, when there is any.** It is Blade, kept
        // exactly as typed; empty means the fields still make the block.
        $kept['markup'] = trim((string) ($data['markup'] ?? ''));

        // How it looks. Every type has one; nothing else reaches the page.
        $kept['style'] = $this->sanitizeStyle((array) ($data['style'] ?? []));

        // The words of whatever stylesheet the site loads.
        $kept['class'] = $this->sanitizeClass((string) ($data['class'] ?? ''));

        foreach ($this->fieldsFor($block->type) as $field) {
            $value = $data[$field['key']] ?? null;

            $kept[$field['key']] = match ($field['kind']) {
                // A flag is on, or it is not there at all.
                'flag' => $value ? '1' : '',
                'options' => array_values(array_filter(array_map('trim', is_array($value) ? $value : explode("\n", (string) $value)), fn ($v) => $v !== '')),
                // **Nothing but one of the choices** ever reaches the page.
                'choice' => isset($field['choices'][(string) $value])
                    ? (string) $value
                    : (string) array_key_first($field['choices']),
                // One row, one choice: its value, its words, and its own
                // disabled. A row left wholly empty is no row.
                'pairs' => array_values(array_filter(
                    array_map(fn ($row) => [
                        'value' => trim((string) ($row['value'] ?? '')),
                        'label' => trim((string) ($row['label'] ?? '')),
                        'disabled' => ! empty($row['disabled']) ? '1' : '',
                    ], is_array($value) ? $value : []),
                    fn ($row) => $row['value'] !== '' || $row['label'] !== '',
                )),
                default => (string) $value,
            };
        }

        $block->forceFill(['data' => $kept])->save();

        return $block;
    }

    public function delete(Block $block): void
    {
        $block->delete();
    }

    private function assertNameIsFree(string $name): string
    {
        $name = trim($name);

        if ($name === '') {
            throw new InvalidArgumentException(__('A name cannot be empty.'));
        }

        if (mb_strlen($name) > 100) {
            throw new InvalidArgumentException(__('A name can be at most 100 characters.'));
        }

        if (Block::query()->where('name', $name)->exists()) {
            throw new InvalidArgumentException(__('[:name] is already a block.', ['name' => $name]));
        }

        return $name;
    }
}
