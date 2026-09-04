<?php

namespace Bladewright\Support;

use Bladewright\Blocks\BlockManager;

/**
 * The Style card's machinery, shared by whatever wears one.
 *
 * **One card, two editors**: a block dresses its element, a component
 * dresses its whole tag, and both press the same switches. The host
 * provides `styleFields()` (its slice of the list) and `reseed()` (what a
 * change does to its code); everything between the controls and the CSS
 * pill lives here, once.
 */
trait StyleCard
{
    /** How it looks: colour, size, weight, corners. @var array<string, string> */
    public array $style = [];

    /**
     * The whole look as CSS, for the pill that writes it directly.
     *
     * **The controls make it, and it makes the controls**: a property the
     * card knows goes back into its field, and anything else is kept as
     * typed and written after them, so a hand overrules a press.
     */
    public string $css = '';

    /** While the CSS box is being typed in, it is not written back into. */
    private bool $writingCss = false;

    /**
     * The four sides of the padding, as the box shows them.
     *
     * @var array<string, string>
     */
    public array $sides = ['top' => '', 'right' => '', 'bottom' => '', 'left' => ''];

    /**
     * Where a slider stands, per field.
     *
     * @var array<string, float>
     */
    public array $sliders = [];

    /** Which colour is being chosen, if any — nothing is open by default. */
    public ?string $colouring = null;

    /** Which style field is choosing a picture, if any. */
    public ?string $stylePicking = null;

    /** Open the media for this field — a background may be a picture. */
    public function pickStyleImage(string $key): void
    {
        $this->stylePicking = $key;
        $this->colouring = null;
    }

    /**
     * **The one listener for a chosen file.** Two on the same event and one
     * of them silently loses, so the host's own picking (a block's src
     * field) is handed on from here.
     */
    #[\Livewire\Attributes\On('bw-media-selected')]
    public function styleImageChosen(string $path): void
    {
        if ($this->stylePicking === null) {
            if (method_exists($this, 'mediaChosen')) {
                $this->mediaChosen($path);
            }

            return;
        }

        // The URL, not the path: what lands in the CSS has to be what the
        // browser fetches.
        $url = app(\Bladewright\Media\MediaLibrary::class)->find($path)?->url() ?? $path;

        $this->style[$this->stylePicking] = "url('".$url."')";
        $this->stylePicking = null;

        $this->reseed();
    }

    /** Fill the card from what is stored, and stand everything where it says. */
    public function seedStyleCard(array $stored): void
    {
        foreach ($this->styleFields() as $field) {
            $this->style[$field['key']] = (string) ($stored[$field['key']] ?? '');
        }

        $this->style['css'] = (string) ($stored['css'] ?? '');

        $this->seedSliders();
        $this->seedSides();
        $this->seedCss();
    }

    public function updatedCss(): void
    {
        $this->writingCss = true;

        $known = [];

        foreach ($this->styleFields() as $field) {
            if ($field['css'] !== null) {
                $known[$field['css']] = $field['key'];
            }
        }

        // Everything the card can hold is emptied first: what is not in the
        // box any more is not on the block any more. **Except the states** —
        // a hover never stands in the style attribute, so the CSS pill has
        // no say over it.
        foreach ($this->styleFields() as $field) {
            if (! isset($field['state'])) {
                $this->style[$field['key']] = '';
            }
        }

        $rest = [];

        foreach (explode(';', $this->css) as $declaration) {
            if (trim($declaration) === '') {
                continue;
            }

            [$property, $value] = array_pad(explode(':', $declaration, 2), 2, '');
            $property = trim($property);
            $value = trim($value);

            if ($value !== '' && isset($known[$property])) {
                $this->style[$known[$property]] = $value;

                continue;
            }

            $rest[] = trim($declaration);
        }

        $this->style['css'] = implode('; ', $rest);

        $this->seedSliders();
        $this->seedSides();
        $this->reseed();
    }

    /** The look as it would be written out, one declaration to a line. */
    private function seedCss(): void
    {
        if ($this->writingCss) {
            return;
        }

        $lines = [];

        foreach ($this->styleFields() as $field) {
            $value = trim((string) ($this->style[$field['key']] ?? ''));

            if ($field['css'] !== null && $value !== '') {
                $lines[] = $field['css'].': '.$value.';';
            }
        }

        // The border is three fields and one rule, as it is on the page.
        foreach (app(BlockManager::class)->borderDeclarations($this->style, fn (string $name) => $name) as $property => $value) {
            $lines[] = $property.': '.$value.';';
        }

        $hand = trim((string) ($this->style['css'] ?? ''));

        if ($hand !== '') {
            $lines[] = rtrim($hand, ';').';';
        }

        $this->css = implode("\n", $lines);
    }

    /** Open the colours for this field, or shut them again. */
    public function openColour(string $key): void
    {
        $this->colouring = $this->colouring === $key ? null : $key;
    }

    /** Take one from the palette, and shut it. */
    public function paint(string $key, string $value): void
    {
        $this->style[$key] = $value;
        $this->colouring = null;

        $this->reseed();
    }

    /** The site's colours, offered by name. @return array<string, string> */
    public function palette(): array
    {
        return app(\Bladewright\Support\Palette::class)->all();
    }

    /** Writing a side writes the whole value. */
    public function updatedSides(): void
    {
        $sides = [];

        foreach (['top', 'right', 'bottom', 'left'] as $side) {
            $sides[$side] = trim((string) ($this->sides[$side] ?? ''));
        }

        // Nothing on any side is no padding at all, not four zeroes.
        if (implode('', $sides) === '') {
            $this->style['padding'] = '';
            $this->reseed();

            return;
        }

        $at = fn (string $side) => $sides[$side] === '' ? '0' : $sides[$side];

        $this->style['padding'] = match (true) {
            $at('top') === $at('bottom') && $at('left') === $at('right') && $at('top') === $at('left') => $at('top'),
            $at('top') === $at('bottom') && $at('left') === $at('right') => $at('top').' '.$at('right'),
            default => $at('top').' '.$at('right').' '.$at('bottom').' '.$at('left'),
        };

        $this->reseed();
    }

    /** The box follows what is written, when it can be taken apart. */
    private function seedSides(): void
    {
        $parts = preg_split('/\s+/', trim((string) ($this->style['padding'] ?? '')), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (! $this->paddingFitsTheBox()) {
            return;
        }

        [$top, $right, $bottom, $left] = match (count($parts)) {
            1 => [$parts[0], $parts[0], $parts[0], $parts[0]],
            2 => [$parts[0], $parts[1], $parts[0], $parts[1]],
            3 => [$parts[0], $parts[1], $parts[2], $parts[1]],
            4 => [$parts[0], $parts[1], $parts[2], $parts[3]],
            default => ['', '', '', ''],
        };

        $this->sides = ['top' => $top, 'right' => $right, 'bottom' => $bottom, 'left' => $left];
    }

    /**
     * Can the padding be shown as four sides?
     *
     * **Nothing is ever silently rewritten**: something the box cannot take
     * apart is typed into a plain box instead, exactly as it stands.
     */
    public function paddingFitsTheBox(): bool
    {
        $value = trim((string) ($this->style['padding'] ?? ''));

        if ($value === '') {
            return true;
        }

        if (str_contains($value, '(') || str_contains($value, ',')) {
            return false;
        }

        return count(preg_split('/\s+/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: []) <= 4;
    }

    /** Dragging writes the value out in the slider's own unit. */
    public function updatedSliders(): void
    {
        foreach ($this->styleFields() as $field) {
            if (! isset($field['slider'], $this->sliders[$field['key']])) {
                continue;
            }

            $at = (float) $this->sliders[$field['key']];

            // **Nothing at the far left**, so a block with no rounding says so
            // rather than saying `0rem`.
            $this->style[$field['key']] = $at <= 0 ? '' : rtrim(rtrim(number_format($at, 3, '.', ''), '0'), '.').$field['slider']['unit'];
        }

        $this->reseed();
    }

    /** The handle follows what is written in the box beside it. */
    private function seedSliders(): void
    {
        foreach ($this->styleFields() as $field) {
            if (! isset($field['slider'])) {
                continue;
            }

            $value = trim((string) ($this->style[$field['key']] ?? ''));
            $unit = $field['slider']['unit'];

            $this->sliders[$field['key']] = preg_match('/^([0-9.]+)'.preg_quote($unit, '/').'$/', $value, $found) === 1
                ? (float) $found[1]
                : 0.0;
        }
    }

    /**
     * Turn one side of a border on, or off again.
     *
     * **Nothing chosen means every side** — a border drawn without saying
     * where is a border all the way round, which is what anybody means by it.
     */
    public function toggleSide(string $key, string $side): void
    {
        $sides = preg_split('/\s+/', trim((string) ($this->style[$key] ?? '')), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($sides === []) {
            $sides = BlockManager::SIDES;
        }

        $sides = in_array($side, $sides, true)
            ? array_values(array_diff($sides, [$side]))
            : [...$sides, $side];

        // All four again is the same as saying nothing.
        $ordered = array_values(array_filter(BlockManager::SIDES, fn ($one) => in_array($one, $sides, true)));

        $this->style[$key] = count($ordered) === 4 || $ordered === [] ? '' : implode(' ', $ordered);

        $this->reseed();
    }

    /**
     * Choose one of a field's choices — or, pressing the lit one again, none
     * of them. **A press undoes itself**, the way the switches do.
     */
    public function choose(string $key, string $value): void
    {
        $this->style[$key] = ($this->style[$key] ?? '') === $value ? '' : $value;

        $this->reseed();
    }

    /** Is this side of the border drawn? @return bool */
    public function sideIsOn(string $key, string $side): bool
    {
        $sides = preg_split('/\s+/', trim((string) ($this->style[$key] ?? '')), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return $sides === [] || in_array($side, $sides, true);
    }

    /** Press a switch on, or off again. **Nothing is stored while it is off.** */
    public function toggle(string $key): void
    {
        foreach ($this->styleFields() as $field) {
            if ($field['key'] === $key && ($field['kind'] ?? '') === 'switch') {
                $this->style[$key] = ($this->style[$key] ?? '') === $field['on'] ? '' : $field['on'];
            }
        }

        $this->reseed();
    }

    /** Changing how it looks moves the block under it, as the fields do. */
    public function updatedStyle(): void
    {
        $this->seedSliders();
        $this->seedSides();
        $this->reseed();
    }
}
