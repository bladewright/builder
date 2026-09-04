<?php

namespace Bladewright\Blocks;

use InvalidArgumentException;
use Bladewright\Models\Layout;
use Bladewright\Models\Structure;

/**
 * Creating, copying, renaming and deleting layouts. **The rule lives here,
 * once**; the commands and the screens both come through.
 *
 * A layout is born from a recipe — **the site's framework × a type** — and
 * the recipe's whole job ends at birth: from then on the frame is ordinary
 * content of the site's, rewritten on the screens, and copying is what
 * carries a look from one to another.
 */
class LayoutManager
{
    public function find(string $name): ?Layout
    {
        return Layout::query()->where('name', trim($name))->first();
    }

    public function create(string $name, string $type = 'header'): Layout
    {
        $name = $this->assertNameIsFree($name);

        if (! in_array($type, Layout::TYPES, true)) {
            throw new InvalidArgumentException(__('[:type] is not a layout type. One of: :types.', [
                'type' => $type,
                'types' => implode(', ', Layout::TYPES),
            ]));
        }

        return Layout::create([
            'name' => $name,
            'type' => $type,
            // **The framework is the site's declaration**, not an argument:
            // a new frame is born speaking whatever the whole site speaks.
            'content' => $this->frame(app(\Bladewright\Support\Framework::class)->get(), $type),
        ]);
    }

    public function copy(Layout $layout, string $to): Layout
    {
        $to = $this->assertNameIsFree($to);

        return Layout::create([
            'name' => $to,
            'type' => $layout->type,
            'content' => $layout->content,
            // **What it wears travels with it** — references, not copies.
            'header_uuid' => $layout->header_uuid,
            'footer_uuid' => $layout->footer_uuid,
        ]);
    }

    /** Change what it is called. **Nothing that uses it notices** — they hold the uuid. */
    public function rename(Layout $layout, string $to): Layout
    {
        $to = $this->assertNameIsFree($to);

        $layout->forceFill(['name' => $to])->save();

        return $layout;
    }

    /**
     * Write the frame. **The whole document is the site's own** — Blade, run
     * when a page is asked for, with `{{ $slot }}` where the page goes.
     *
     * Nothing is refused here: a frame with no slot still saves, and the
     * screen says what it means, because the person editing is the one who
     * knows what they are in the middle of.
     */
    public function saveFrame(Layout $layout, string $content): Layout
    {
        $layout->forceFill(['content' => $content])->save();

        return $layout;
    }

    /**
     * The typeface every page in this frame reads in. **Top-down, like the
     * framework** — block by block would be misery. A stack, written the way
     * CSS reads one; empty leaves the framework's own standing.
     */
    public function saveTypeface(Layout $layout, string $fontFamily): Layout
    {
        $fontFamily = trim($fontFamily);

        if ($fontFamily !== ''
            && (str_contains($fontFamily, ';') || preg_match('/^[0-9a-z.%#,()\/ \'"-]+$/i', $fontFamily) !== 1)) {
            throw new InvalidArgumentException(__('[:value] does not read as a font stack (Noto Sans JP, sans-serif).', ['value' => $fontFamily]));
        }

        $layout->forceFill(['font_family' => $fontFamily === '' ? null : $fontFamily])->save();

        return $layout;
    }

    /**
     * The header or the footer this frame wears.
     *
     * **A band is a component** — the same component as anywhere else, so a
     * header is a logo and a nav arranged on their own screen, and one
     * header can be worn by every layout there is. Null takes it off.
     */
    public function wear(Layout $layout, string $band, ?Structure $component): Layout
    {
        if (! in_array($band, Layout::BANDS, true)) {
            throw new InvalidArgumentException(__('[:band] is not a band. One of: :bands.', [
                'band' => $band,
                'bands' => implode(', ', Layout::BANDS),
            ]));
        }

        // **A band starts from its tag**, so the header band takes a header
        // component and nothing else — a section here would be a band that
        // does not say what it is.
        if ($component !== null && $component->type !== $band) {
            throw new InvalidArgumentException(__('The :band band takes a :band component; [:name] is a :type.', [
                'band' => $band,
                'name' => $component->name,
                'type' => $component->type,
            ]));
        }

        $layout->forceFill([$band.'_uuid' => $component?->uuid])->save();

        return $layout;
    }

    /** The component a band wears, if it is still there. */
    public function worn(Layout $layout, string $band): ?Structure
    {
        $uuid = $layout->{$band.'_uuid'};

        return $uuid === null ? null : Structure::query()->where('uuid', $uuid)->first();
    }

    /** How many frames wear this component. **What editing it reaches.** */
    public function framesWearing(Structure $component): int
    {
        return Layout::query()
            ->where('header_uuid', $component->uuid)
            ->orWhere('footer_uuid', $component->uuid)
            ->count();
    }

    /** A deleted component leaves no frame wearing something that is gone. */
    public function forget(string $uuid): void
    {
        Layout::query()->where('header_uuid', $uuid)->update(['header_uuid' => null]);
        Layout::query()->where('footer_uuid', $uuid)->update(['footer_uuid' => null]);
    }

    /** Does the page's content have anywhere to go in this frame? */
    public function holdsThePage(string $content): bool
    {
        return str_contains($content, '$slot');
    }

    public function delete(Layout $layout): void
    {
        $layout->delete();
    }

    /**
     * The frame a layout is born with.
     *
     * A whole HTML document — header, nav, main with `{{ $slot }}`, footer,
     * and an `<aside>` when the type asks for one. **Presentable from the
     * first minute**: a bare skeleton would be handed to the person as their
     * site's face.
     *
     * The header and the footer are not written into it: they are
     * `{{ $header }}` and `{{ $footer }}`, **the components the frame
     * wears**, chosen on the layout's screen. What is left here is the
     * document itself — the head, the styles, and where the three bands sit.
     */
    private function frame(string $framework, string $type): string
    {
        return match ($framework) {
            'bootstrap' => $this->bootstrapFrame($type === 'sidebar'),
            'pico' => $this->picoFrame($type === 'sidebar'),
            default => $this->plainFrame($type === 'sidebar'),
        };
    }

    private function bootstrapFrame(bool $sidebar): string
    {
        $main = $sidebar
            ? <<<'HTML'
    <div class="container my-4 flex-grow-1">
        <div class="row g-4">
            <aside class="col-lg-3">
                <nav class="nav flex-column gap-1" aria-label="Section">
                    <a class="nav-link px-0" href="/">Home</a>
                </nav>
            </aside>
            <main class="col-lg-9">
{{ $slot }}
            </main>
        </div>
    </div>
HTML
            : <<<'HTML'
    {{-- No container of the frame's own: **a section brings its own** —
         the band runs to the edges, the words hold to the middle. --}}
    <main class="flex-grow-1">
{{ $slot }}
    </main>
HTML;

        return <<<HTML
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @bwframework
    @bwstyles
    @bwanalytics
    <style>
        /* Blocks put out plain HTML with no classes of their own. These few
           lines keep it sitting right in the bands. */
        header p, footer p { margin: 0; }
    </style>
    @bwmeta
</head>
<body class="d-flex flex-column min-vh-100">
    {{-- **The component brings its own tag.** What is placed here is a
         header component, and its tag is the band — the frame only says
         where the bands stand. --}}
    {{ \$header }}

{$main}

    {{ \$footer }}
</body>
</html>
HTML;
    }

    /**
     * **Pico dresses the bare tags**, which is exactly what blocks put out:
     * an input, a button, a table look right with no classes written at
     * all. The frame's own chrome stays minimal for the same reason.
     */
    private function picoFrame(bool $sidebar): string
    {
        $main = $sidebar
            ? <<<'HTML'
    <main style="display:grid;grid-template-columns:12rem 1fr;gap:2.5rem;align-items:start">
        <aside>
            <nav aria-label="Section"><ul><li><a href="/">Home</a></li></ul></nav>
        </aside>
        <div>
{{ $slot }}
        </div>
    </main>
HTML
            : <<<'HTML'
    {{-- No container of the frame's own: a section brings its own. --}}
    <main>
{{ $slot }}
    </main>
HTML;

        return <<<HTML
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @bwframework
    @bwstyles
    @bwanalytics
    <style>
        /* Pico centres its own <main> container; the bands run free. */
        body > main { padding-block: 2rem; }
        header p, footer p { margin: 0; }
    </style>
    @bwmeta
</head>
<body>
    {{-- The component brings its own tag; the frame only says where. --}}
    {{ \$header }}

{$main}

    {{ \$footer }}
</body>
</html>
HTML;
    }

    private function plainFrame(bool $sidebar): string
    {
        $main = $sidebar
            ? <<<'HTML'
    <div class="shell columns">
        <aside>
            <nav aria-label="Section"><a href="/">Home</a></nav>
        </aside>
        <main>
{{ $slot }}
        </main>
    </div>
HTML
            : <<<'HTML'
    {{-- No shell of the frame's own: a section brings its own container. --}}
    <main>
{{ $slot }}
    </main>
HTML;

        return <<<HTML
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @bwframework
    @bwmeta
    <style>
        /* The look lives in these tokens. Change them and the site follows. */
        :root {
            --ink: #1f2429;
            --faint: #667085;
            --rule: #e4e7ec;
            --accent: #3538cd;
            --wrap: 68rem;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; display: flex; flex-direction: column;
            font-family: ui-sans-serif, system-ui, sans-serif;
            color: var(--ink); line-height: 1.7;
        }
        a { color: var(--accent); text-decoration: none; }
        a:hover { text-decoration: underline; }
        .shell { width: min(var(--wrap), 100% - 2.5rem); margin-inline: auto; }
        main { flex: 1; padding-block: 2.5rem; }
        .columns { display: flex; gap: 2.5rem; flex: 1; padding-block: 2.5rem; }
        .columns aside { flex: 0 0 12rem; }
        .columns aside nav { display: flex; flex-direction: column; gap: .5rem; }
        .columns main { flex: 1; min-width: 0; padding-block: 0; }
        @media (max-width: 48rem) { .columns { flex-direction: column; } .columns aside { flex-basis: auto; } }
        /* Blocks put out plain HTML; these keep it sitting right in the bands.
           **Nothing else dresses a band here**: the header and the footer are
           components, and their look is their own Style card's. */
        header p, footer p { margin: 0; }
    </style>
    @bwstyles
    @bwanalytics
</head>
<body>
    {{-- The component brings its own tag; the frame only says where. --}}
    {{ \$header }}

{$main}

    {{ \$footer }}
</body>
</html>
HTML;
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

        if (Layout::query()->where('name', $name)->exists()) {
            throw new InvalidArgumentException(__('[:name] is already a layout.', ['name' => $name]));
        }

        return $name;
    }
}
