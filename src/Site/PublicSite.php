<?php

namespace Bladewright\Site;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Bladewright\Content\Markdown;
use Bladewright\Media\MediaLibrary;
use Bladewright\Models\Block;
use Bladewright\Models\Layout;
use Bladewright\Models\Page;
use Bladewright\Models\Structure;
use Bladewright\Models\StructureChild;

/**
 * The four-layer world, served.
 *
 * A page is a row of components; a component is an arrangement of blocks; a
 * layout wraps the lot. Everything is resolved **by uuid at render time**,
 * so editing a block changes every page that shows it the moment it is next
 * requested, and renaming anything changes nothing at all.
 *
 * **The new world answers first.** Where it has a published page for a path,
 * that page is served; everywhere else the request falls through to the old
 * world, which still carries the site while the two coexist.
 */
class PublicSite
{
    public function __construct(
        private readonly Markdown $markdown,
        private readonly MediaLibrary $media,
    ) {}

    /** While true, every block is stamped with its uuid for the editor. */
    private bool $stamping = false;

    /**
     * The parts already fetched this render, by kind and uuid.
     *
     * **One render's memory, and no longer.** A fresh instance answers every
     * request, so this can never hand back yesterday's reading of a row.
     *
     * @var array<string, array<string, \Bladewright\Models\Block|\Bladewright\Models\Structure|null>>
     */
    private array $known = [];

    /**
     * The editor's unsaved arrangement, worn over the stored one.
     *
     * **The page in the database does not move until Save** — the editor
     * hands its draft in here, and this instance renders as if it were so.
     *
     * @var array<int, string>|null ordered component uuids for the page
     */
    private ?array $draftPage = null;

    /** @var array<string, array<int, array{kind: string, uuid: string}>> rows per component uuid */
    private array $draftComponents = [];

    /**
     * A part's unsaved DATA, worn over what is stored — **what the open
     * panel says right now**. Data, never pre-rendered HTML: rendering here
     * keeps the stamps, the draft arrangement, and the sanitizing.
     *
     * @var array<string, array{kind: string, data: array<string, mixed>}> per part uuid
     */
    private array $draftParts = [];

    /**
     * What the path gave up: `news/{slug}` on `/news/hello` says `hello`.
     *
     * **Handed to whoever draws the page** — the page's own Blade reads it
     * as `$slug`, and a live component is given it the way any Livewire
     * component is given what it needs.
     *
     * @var array<string, string>
     */
    private array $given = [];

    /** Render as though the path had said these words. */
    public function given(array $given): static
    {
        $this->given = $given;

        return $this;
    }

    /**
     * Render as if this arrangement were saved.
     *
     * @param  array<int, string>|null  $pageRows
     * @param  array<string, array<int, array{kind: string, uuid: string}>>  $componentRows
     * @param  array<string, array{kind: string, data: array<string, mixed>}>  $partData
     */
    public function draft(?array $pageRows, array $componentRows = [], array $partData = []): static
    {
        $this->draftPage = $pageRows;
        $this->draftComponents = $componentRows;
        $this->draftParts = $partData;

        return $this;
    }

    /**
     * The page as the editor's preview needs it: **every block wearing its
     * uuid**, on a wrapper that takes part in no layout (`display:contents`),
     * so the preview can say what was pressed. **Never the public page** —
     * only the admin's preview route asks for this.
     */
    public function pageForEditing(Page $page): string
    {
        $this->stamping = true;

        try {
            return $this->page($page);
        } finally {
            $this->stamping = false;
        }
    }

    /**
     * The response for this path, or null when the new world has nothing
     * to say and the old world should answer.
     */
    public function respond(string $path): ?Response
    {
        $url = $path === '/' ? '' : trim($path, '/');

        // **A page may be a shape rather than a place**: `news/{slug}`
        // answers every path of that shape, and what stood in the opening
        // comes back with it.
        $found = app(PageRoutes::class)->match($url);

        if ($found === null) {
            return null;
        }

        [$page, $given] = $found;

        if (! $page->is_published) {
            return null;
        }

        $this->given = $given;

        if ($page->published_from !== null && $page->published_from->isFuture()) {
            return null;
        }

        // **Gone on purpose.** A campaign that has run its course says so,
        // and search engines drop it sooner than a 404.
        if ($page->published_until !== null && $page->published_until->isPast()) {
            abort(410);
        }

        return new Response($this->page($page));
    }

    /**
     * The whole document for one page — **unless somebody has written its
     * markup by hand**, in which case that is the page, whole: DOCTYPE to
     * closing tag, the same bargain every layer makes. From that moment
     * neither the arrangement nor the layout reaches it, until the markup
     * is emptied again.
     */
    public function page(Page $page): string
    {
        $markup = trim((string) (($page->data ?? [])['markup'] ?? ''));

        if ($markup !== '') {
            try {
                return static::runBlade($markup, $this->given + ['page' => $page]);
            } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {
                // Said on purpose — a page that decides it is not there.
                throw $e;
            } catch (\Throwable $e) {
                return '<!-- '.e($e->getMessage()).' -->';
            }
        }

        return $this->assembledDocument($page);
    }

    /**
     * The whole document the four layers make. **The starting point for the
     * Code pill.**
     */
    public function assembledDocument(Page $page): string
    {
        $rows = $this->draftPage === null
            ? $page->children()->get()
            : collect($this->draftPage)->values()->map(fn ($uuid, $index) => new \Bladewright\Models\PageChild([
                'child_uuid' => $uuid,
                'position' => $index + 1,
            ]));

        $inner = $this->childrenOf($rows);

        $layout = $page->layout_uuid === null
            ? null
            : Layout::query()->where('uuid', $page->layout_uuid)->first();

        if ($layout === null) {
            // **Bare, as it was warned it would be.** A minimal document
            // rather than an invalid one.
            return $this->bareDocument($page, $inner);
        }

        // The layout's frame is Blade of the site's own; the page slots in,
        // and the header and footer it wears go with it. **The page itself
        // rides along** for whatever asks about it — `@bwmeta` does.
        return static::runBlade($layout->content, $this->bands($layout, $inner) + $this->given + ['page' => $page]);
    }

    /**
     * What a frame is given to render: its three bands.
     *
     * `{{ $header }}` and `{{ $footer }}` are the components it wears, and
     * `{{ $slot }}` is the page itself. **Only the middle one is the page's**
     * — the other two are the same on every page wearing the frame.
     *
     * @return array<string, \Illuminate\Support\HtmlString>
     */
    public function bands(Layout $layout, string $inner = ''): array
    {
        // **The typeface is the frame's word**, and every render of the
        // frame passes through here — the pages and the layout screen's own
        // preview alike. `@bwstyles` prints it after the framework's link,
        // so the frame's say wins the tie.
        $font = trim((string) $layout->font_family);

        if ($font !== '' && ! str_contains($font, ';') && ! str_contains($font, '}')) {
            app(\Bladewright\Support\CollectedCss::class)->rule('body{font-family:'.$font.'}');
        }

        return [
            'header' => new HtmlString($this->band($layout, 'header')),
            'slot' => new HtmlString($inner),
            'footer' => new HtmlString($this->band($layout, 'footer')),
        ];
    }

    private function band(Layout $layout, string $band): string
    {
        $uuid = $layout->{$band.'_uuid'};

        if ($uuid === null) {
            return '';
        }

        $component = Structure::query()->where('uuid', $uuid)->first();

        return $component === null ? '' : $this->structure($component);
    }

    /** @param  \Illuminate\Support\Collection<int, \Illuminate\Database\Eloquent\Model>  $children */
    private function childrenOf($children): string
    {
        $out = [];

        // **One question a kind, not one a child.** Walking a page asked the
        // database for every block and every component by name, one at a
        // time — a page of forty blocks cost fifty-eight round trips. The
        // whole level is fetched in a breath, and the walk reads from that.
        $this->remember($children);

        foreach ($children as $child) {
            // A page's children are components; a component's rows say which
            // kind they hold.
            $kind = $child->child_kind ?? StructureChild::KIND_COMPONENT;

            $rendered = $this->renderReference($kind, $child->child_uuid);

            // **The editing preview also says where each one stands.** The
            // slot rides on the page's own rows, and a block inside a
            // component carries its place in that component — which is how
            // the + buttons know what may be placed around, and where.
            if ($this->stamping && $rendered !== '' && $child instanceof \Bladewright\Models\PageChild) {
                $rendered = (string) preg_replace('/data-bw-component=/', 'data-bw-slot="'.(int) $child->position.'" data-bw-component=', $rendered, 1);
            }

            if ($this->stamping && $rendered !== '' && $child instanceof StructureChild && $kind === StructureChild::KIND_BLOCK) {
                $rendered = (string) preg_replace('/data-bw-block=/', 'data-bw-at="'.(int) $child->position.'" data-bw-block=', $rendered, 1);
            }

            $out[] = $rendered;
        }

        return implode("\n", array_filter($out));
    }

    /**
     * The parts of one level, fetched together and kept for the walk.
     *
     * **Only what has not been seen**, so a component standing on two pages
     * is asked for once, and a level of forty blocks is one question rather
     * than forty. The instance lives for one render, so nothing here is ever
     * a stale reading of a row.
     *
     * @param  iterable<int, object>  $children
     */
    private function remember($children): void
    {
        $wanted = [StructureChild::KIND_BLOCK => [], StructureChild::KIND_COMPONENT => []];

        foreach ($children as $child) {
            $kind = $child->child_kind ?? StructureChild::KIND_COMPONENT;
            $uuid = $child->child_uuid;

            if (! isset($this->known[$kind]) || ! array_key_exists($uuid, $this->known[$kind])) {
                $wanted[$kind][$uuid] = true;
            }
        }

        foreach ($wanted as $kind => $uuids) {
            if ($uuids === []) {
                continue;
            }

            // A component is asked for with its own list in the same breath,
            // so the level below is already in hand when the walk reaches it.
            $rows = $kind === StructureChild::KIND_BLOCK
                ? Block::query()->whereIn('uuid', array_keys($uuids))->get()
                : Structure::query()->with('children')->whereIn('uuid', array_keys($uuids))->get();

            // **A uuid that found nothing is remembered too**, or a pointer
            // at something deleted would be asked after on every render.
            foreach (array_keys($uuids) as $uuid) {
                $this->known[$kind][$uuid] = null;
            }

            foreach ($rows as $row) {
                $this->known[$kind][$row->uuid] = $row;
            }

            // **Down a floor, still in one breath.** The children just read
            // name the next level, so it is fetched now rather than one at a
            // time when the walk arrives. It ends on its own: nothing already
            // known is asked for twice.
            if ($kind === StructureChild::KIND_COMPONENT) {
                $below = $rows->flatMap(fn ($row) => $row->children)->all();

                if ($below !== []) {
                    $this->remember($below);
                }
            }
        }
    }

    private function renderReference(string $kind, string $uuid): string
    {
        if (! array_key_exists($uuid, $this->known[$kind] ?? [])) {
            $this->known[$kind][$uuid] = $kind === StructureChild::KIND_BLOCK
                ? Block::query()->where('uuid', $uuid)->first()
                : Structure::query()->where('uuid', $uuid)->first();
        }

        $row = $this->known[$kind][$uuid];

        if ($row === null) {
            return '';
        }

        return $kind === StructureChild::KIND_BLOCK ? $this->block($row) : $this->structure($row);
    }

    /**
     * A component: its semantic tag, its arrangement, its children —
     * **unless somebody has written its markup by hand**, in which case that
     * is the component, the same bargain a block makes.
     *
     * **Spacing lives here and nowhere else** — padding and gap out of the
     * component's own data, as inline flex styles so no framework is
     * presumed. `container` wraps the children in an inner grid.
     */
    public function structure(Structure $structure): string
    {
        // The open panel's unsaved dress, worn as a ghost — its children
        // still render through this instance, drafts and stamps intact.
        if (($this->draftParts[$structure->uuid]['kind'] ?? null) === 'component') {
            $structure = clone $structure;
            $structure->data = $this->draftParts[$structure->uuid]['data'];
        }

        $out = $this->servedStructure($structure);

        // The editing preview's stamp, the same as a block's: what was
        // pressed has to be sayable, one layer out.
        if ($this->stamping && trim($out) !== '') {
            return '<div data-bw-component="'.e($structure->uuid).'" data-bw-name="'.e($structure->name).'" style="display:contents">'."\n".$this->indent($out)."\n".'</div>';
        }

        return $out;
    }

    private function servedStructure(Structure $structure): string
    {
        $markup = trim((string) (($structure->data ?? [])['markup'] ?? ''));

        if ($markup !== '') {
            try {
                return static::runBlade($markup, $this->given);
            } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {
                throw $e;
            } catch (\Throwable $e) {
                return '<!-- '.e($e->getMessage()).' -->';
            }
        }

        return $this->assembled($structure);
    }

    /** What the arrangement makes of it. **The starting point for the Code pill.** */
    public function assembled(Structure $structure): string
    {
        $rows = isset($this->draftComponents[$structure->uuid])
            ? collect($this->draftComponents[$structure->uuid])->values()->map(fn ($row, $index) => new StructureChild([
                'child_kind' => $row['kind'],
                'child_uuid' => $row['uuid'],
                'position' => $index + 1,
            ]))
            : $structure->children;

        $inner = $this->childrenOf($rows);

        if ($structure->layout !== 'stack') {
            // **Small screens stack** unless the arrangement says otherwise.
            // A media rule cannot be said inline, so the wrapper takes a
            // machine class and the document's head carries the rule — with
            // the one `!important` that lets a stylesheet outrank the
            // wrapper's own style attribute.
            $collapse = (bool) (($structure->data ?? [])['collapse'] ?? true);
            $state = '';

            if ($collapse) {
                $state = self::stateClass($structure->uuid).'-in';
                app(\Bladewright\Support\CollectedCss::class)->rule(
                    '@media (max-width:40rem){.'.$state.'{'.($structure->layout === 'grid'
                        ? 'grid-template-columns:1fr !important'
                        : 'flex-direction:column !important;align-items:stretch !important').'}}',
                );
            }

            $inner = '<div'.($state === '' ? '' : ' class="'.$state.'"').' style="'.e($this->arrangementStyle($structure)).'">'."\n".$this->indent($inner)."\n".'</div>';
        }

        // **The container stands at section level**, the way it does
        // everywhere: the tag is the full-width band, and the width holds
        // the words to the middle of it.
        $width = trim((string) (($structure->data ?? [])['width'] ?? ''));

        if ($width !== '') {
            $inner = '<div style="'.e('max-width:'.$width.';margin-inline:auto').'">'."\n".$this->indent($inner)."\n".'</div>';
        }

        $tag = $structure->type === 'field' ? 'div' : $structure->type;

        // **The tag wears its look, over one standing declaration**:
        // flow-root keeps the children's margins inside it — without it a
        // heading's own margin escapes the tag, and a coloured band opens
        // with a strip of whatever lies behind it.
        $look = $this->lookCss((array) (($structure->data ?? [])['style'] ?? []));
        $style = 'display:flow-root'.($look === '' ? '' : ';'.$look);

        // The words of whatever stylesheet the site loads, on the same tag.
        $class = app(\Bladewright\Blocks\BlockManager::class)
            ->sanitizeClass((string) (($structure->data ?? [])['class'] ?? ''));

        // The hover, on the tag the Style card paints.
        $hover = $this->hoverCss((array) (($structure->data ?? [])['style'] ?? []));

        if ($hover !== '') {
            $state = self::stateClass($structure->uuid);
            app(\Bladewright\Support\CollectedCss::class)->rule('.'.$state.':hover{'.$hover.'}');
            $class = trim($class.' '.$state);
        }

        return '<'.$tag.($class === '' ? '' : ' class="'.e($class).'"').' style="'.e($style).'">'."\n".$this->indent($inner)."\n".'</'.$tag.'>';
    }

    /**
     * The Style card's map, written out as declarations.
     *
     * **The same card the blocks wear**, on a component's own tag: names are
     * resolved through the palette at render time, the border's three fields
     * become one rule, and the hand-typed CSS comes last.
     *
     * @param  array<string, string|array<string, string>>  $styleMap
     */
    private function lookCss(array $styleMap): string
    {
        $blocks = app(\Bladewright\Blocks\BlockManager::class);
        $style = $blocks->sanitizeStyle($styleMap);

        if ($style === []) {
            return '';
        }

        $palette = app(\Bladewright\Support\Palette::class);
        $css = $blocks->borderDeclarations($style, fn (string $name) => $palette->resolve($name));

        foreach ($blocks->styleFields() as $field) {
            if ($field['css'] === null || ! isset($style[$field['key']])) {
                continue;
            }

            $value = $field['kind'] === 'colour'
                ? $palette->resolve($style[$field['key']])
                : $style[$field['key']];

            $css[$field['css']] = isset($css[$field['css']])
                ? $css[$field['css']].' '.$value
                : $value;
        }

        $written = [];

        foreach ($css as $property => $value) {
            $written[] = $property.':'.$value;
        }

        if (trim((string) ($style['css'] ?? '')) !== '') {
            $written[] = trim($style['css']);
        }

        return implode(';', $written);
    }

    /**
     * One block: one element. **The Markdown block is the one exception.**
     *
     * A block's markup is generated from its fields — unless somebody has
     * written it by hand on the Code pill, in which case **that is the block**:
     * Blade of the site's own, run when the page is asked for, so a block can
     * show what is worked out at that moment rather than only what was typed
     * beforehand.
     *
     * **A page never dies of it.** Blade that throws leaves a comment where
     * the block stood; the editor says why, which is where it gets fixed.
     */
    public function block(Block $block): string
    {
        // The open panel's unsaved words, worn as a ghost of the block.
        if (($this->draftParts[$block->uuid]['kind'] ?? null) === 'block') {
            $block = clone $block;
            $block->data = $this->draftParts[$block->uuid]['data'];
        }

        $out = $this->served($block);

        // The editing preview's stamp — around authored and generated alike,
        // and around nothing when the block puts nothing out.
        if ($this->stamping && trim($out) !== '') {
            return '<div data-bw-block="'.e($block->uuid).'" data-bw-name="'.e($block->name).'" style="display:contents">'."\n".$this->indent($out)."\n".'</div>';
        }

        return $out;
    }

    private function served(Block $block): string
    {
        $markup = trim((string) (($block->data ?? [])['markup'] ?? ''));

        if ($markup !== '') {
            try {
                return static::runBlade($markup);
            } catch (\Throwable $e) {
                return '<!-- '.e($e->getMessage()).' -->';
            }
        }

        return $this->generated($block);
    }

    /**
     * What the type makes of its fields. **The starting point for the Code
     * pill**, and what is shown there is exactly what lands on the page.
     *
     * **Every block stands in a box of its own.** A component lays its
     * contents out — flex, grid, gaps — and a bare `<button>` in that
     * arrangement would be stretched across the whole width by it. The box is
     * where the block sits, and it is right here where it can be seen and
     * written over.
     */
    public function generated(Block $block): string
    {
        // **A div is its own box** — wrapping it would be a box in a box.
        if ($block->type === 'div') {
            return '<div'.$this->classAttribute($block).$this->styleAttribute($block, 'element').'>'."\n".'</div>';
        }

        // Trimmed: markdown ends on a newline, and a blank line before the
        // closing tag is only noise in something meant to be read.
        $element = trim($this->element($block));

        if ($element === '') {
            return '';
        }

        // **The look lands on the thing the block is.** A button's colour is
        // the button's; **where it sits is the box's**, since nothing can
        // place itself. Markdown has no one element, so it wears the lot on
        // its box.
        $box = $this->classAttribute($block, 'box').$this->styleAttribute($block, 'box');

        return '<div'.$box.">\n".$this->indent($element)."\n</div>";
    }

    /**
     * ` style="…"`, or nothing at all. **Only what reads as what it says**
     * reaches it — `BlockManager` keeps the list and the rule.
     */
    private function styleAttribute(Block $block, string $where): string
    {
        $blocks = app(\Bladewright\Blocks\BlockManager::class);
        $style = $blocks->sanitizeStyle((array) (($block->data ?? [])['style'] ?? []));

        if ($style === []) {
            return '';
        }

        $css = [];

        $palette = app(\Bladewright\Support\Palette::class);

        // **The border is three fields and one rule**, so it is written out
        // in one piece rather than a property at a time.
        if (($where === 'box') === $this->wearsItOnTheBox($block)) {
            $css = $blocks->borderDeclarations($style, fn (string $name) => $palette->resolve($name));
        }

        foreach ($blocks->styleFieldsFor($block->type) as $field) {
            if ($field['css'] === null) {
                continue;
            }

            // A block of several elements wears the lot on its box.
            $onBox = ($field['box'] ?? false) || $this->wearsItOnTheBox($block);

            if (($where === 'box') !== $onBox) {
                continue;
            }

            if (isset($style[$field['key']])) {
                // **A name becomes its value here, at render time**, so
                // changing the palette changes every page at once.
                $value = $field['kind'] === 'colour'
                    ? $palette->resolve($style[$field['key']])
                    : $style[$field['key']];

                // Underline and strike are two switches over one property:
                // when both are on, they stand side by side.
                $css[$field['css']] = isset($css[$field['css']])
                    ? $css[$field['css']].' '.$value
                    : $value;
            }
        }

        $written = [];

        foreach ($css as $property => $value) {
            $written[] = $property.':'.$value;
        }

        // **Written by hand, last** — CSS takes the last word, so what
        // somebody typed overrules what the controls made.
        $onBox = $block->type === 'markdown';

        if (($where === 'box') === $onBox && trim((string) ($style['css'] ?? '')) !== '') {
            $written[] = trim($style['css']);
        }

        return $written === [] ? '' : ' style="'.e(implode(';', $written)).'"';
    }

    /**
     * ` class="…"`, or nothing at all. **It stands where the look stands** —
     * on the element for most, on the box for a block of several elements —
     * because the class and the style dress the same thing.
     */
    private function classAttribute(Block $block, string $where = 'element'): string
    {
        if (($where === 'box') !== $this->wearsItOnTheBox($block)) {
            return '';
        }

        $class = app(\Bladewright\Blocks\BlockManager::class)
            ->sanitizeClass((string) (($block->data ?? [])['class'] ?? ''));

        // **The machine class stands where the look stands** — the hover
        // paints the same element the Style card does.
        $hover = $this->hoverCss((array) (($block->data ?? [])['style'] ?? []));

        if ($hover !== '') {
            $state = self::stateClass($block->uuid);
            app(\Bladewright\Support\CollectedCss::class)->rule('.'.$state.':hover{'.$hover.'}');
            $class = trim($class.' '.$state);
        }

        if ($class === '') {
            return '';
        }

        return ' class="'.e($class).'"';
    }

    /**
     * Does this block wear its whole look on its box?
     *
     * **A block of several elements does** — Markdown, and the choice groups.
     * There is no one element to paint, and the box holds them all: colour
     * and size reach the words by inheritance, a border wraps the group.
     */
    private function wearsItOnTheBox(Block $block): bool
    {
        return in_array($block->type, ['markdown', 'radio', 'checkbox'], true);
    }

    /**
     * One step in, for HTML meant to be read.
     *
     * **Anything holding a `<pre>` is left exactly as it is**: whitespace in
     * there is the content, and a tidier source would change what the page
     * says.
     */
    private function indent(string $html): string
    {
        if (str_contains($html, '<pre')) {
            return $html;
        }

        return (string) preg_replace('/^(?!$)/m', '    ', $html);
    }

    /**
     * The one element the type is.
     *
     * **The tag stands even before it is filled in.** An empty block used to
     * come out as nothing at all, which read on the Code pill as though it
     * were broken; now the shape is there from the start and the fields fill
     * it. A `src` or an `href` with nothing in it is left off rather than
     * written empty — an empty one points the browser back at the page.
     */
    private function element(Block $block): string
    {
        $data = $block->data ?? [];
        // **The block's own look**, on the element itself — all but what
        // only the box can answer for. A hidden field wears none at all:
        // there is nothing to see of it.
        $hidden = $block->type === 'input' && trim((string) ($data['type'] ?? '')) === 'hidden';
        $style = $hidden ? '' : $this->classAttribute($block).$this->styleAttribute($block, 'element');
        // **A field left empty is a field not filled in**, so the default
        // stands — the editor seeds every key, so nothing is ever missing.
        $text = fn (string $key, string $default = '') => trim((string) ($data[$key] ?? '')) ?: $default;

        return match ($block->type) {
            // **h1 belongs to Markdown** (the four-layer rule): `#` comes out
            // as the heading it says, with no offset pushing it down. The old
            // world keeps its own offset; this world writes what it means.
            'markdown' => $this->markdown->render($text('body'), headingOffset: 0),
            'image' => $this->picture($text, $style),
            'video' => $this->player('video', $text, $style),
            'audio' => $this->player('audio', $text, $style),
            // **A button is a `<button>`.** A plain link is a link, and
            // Markdown already writes those.
            'button' => $this->button($text('label'), $text('type', 'button'), $text('url'), $style),
            // A hidden field wears no label: nobody sees it to be told.
            'input' => $text('type') === 'hidden'
                ? $this->input($text, $style)
                : $this->named($text, $this->input($text, $style)),
            // No words of the group's own: every choice is its own label,
            // and carries its own required and disabled.
            'radio' => $this->choices('radio', $text('name'), (array) ($data['options'] ?? []), $text('required') !== '', $text('disabled') !== '', $style),
            'checkbox' => $this->choices('checkbox', $text('name'), (array) ($data['options'] ?? []), $text('required') !== '', $text('disabled') !== '', $style),
            // The words stand above the box: a tall box beside a word reads
            // wrong, and no stylesheet may be presumed.
            'textarea' => $this->named($text, $this->textarea($text, $style), above: true),
            'select' => $this->named($text, $this->select(
                $text('name'),
                (array) ($data['options'] ?? []),
                $text('required') !== '',
                $text('disabled') !== '',
                $text('multiple') !== '',
                $style,
            )),
            'embed' => '<iframe'.$this->src($text('url'), false)
                .($text('width') !== '' ? ' width="'.e($text('width')).'"' : '')
                .($text('height') !== '' ? ' height="'.e($text('height')).'"' : '')
                .' title="'.e($text('title')).'" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen'.$style.'></iframe>',
            default => '',
        };
    }

    /**
     * Run some of the site's own Blade. **It throws; callers decide.**
     *
     * @param  array<string, mixed>  $data
     */
    public static function runBlade(string $blade, array $data = []): string
    {
        return static::apart(fn () => Blade::render($blade, $data));
    }

    /**
     * Run a render **without joining, or disturbing, the one around it.**
     *
     * Every part of a page is Blade run inside somebody else's Blade, and a
     * part that fails is turned into a comment so it can never take the page
     * down. But a render that throws leaves the view factory mid-stride: its
     * half-open components on the stack, its count of renders in flight one
     * too high, and its output buffers open. The page then came apart much
     * later and somewhere else, with `Undefined array key 0` from a close
     * whose open had been swept away.
     *
     * So the factory's state is set aside for the length of the render and
     * put back after, whatever happens, and any buffer the render left open
     * is closed. **Laravel offers no way to ask for this**, so it is taken by
     * reflection, and taken narrowly: named properties, every one restored.
     */
    public static function apart(callable $work): string
    {
        $factory = app('view');
        $depth = ob_get_level();

        $fresh = [
            'componentStack' => [],
            'componentData' => [],
            'currentComponentData' => [],
            'slots' => [],
            'slotStack' => [],
            'renderCount' => 0,
        ];

        $kept = [];

        foreach ($fresh as $name => $empty) {
            if (! property_exists($factory, $name)) {
                // A Laravel that keeps these elsewhere: nothing to set
                // aside, and the render goes ahead as it is.
                return (string) $work();
            }

            $held = new \ReflectionProperty($factory, $name);
            $kept[] = [$held, $held->getValue($factory)];
            $held->setValue($factory, $empty);
        }

        try {
            return (string) $work();
        } finally {
            // What the render opened and did not close is closed here, or
            // the page around it would be written into the wrong buffer.
            while (ob_get_level() > $depth) {
                ob_end_clean();
            }

            foreach ($kept as [$held, $was]) {
                $held->setValue($factory, $was);
            }
        }
    }

    /**
     * A field, with the words that name it.
     *
     * **The label is part of the field**, not a block beside it: one is no
     * use without the other. It wraps the control, the way the choices of a
     * radio are wrapped — **no ids are minted to tie the two**, and pressing
     * the words reaches the box all the same.
     */
    private function named(callable $text, string $control, bool $above = false): string
    {
        $words = $text('label');

        if ($words === '') {
            return $control;
        }

        // **The one structural tag the renderer writes**: a `<br>` where the
        // words must stand above the box, because a label breaks no line of
        // its own and no stylesheet may be presumed.
        return '<label>'.e($words).($above ? '<br>' : '')."\n".$this->indent($control)."\n".'</label>';
    }

    /**
     * A picture — wrapped in a link when it is given somewhere to go.
     *
     * **The link is the one wrapper a block may put around its element**:
     * an image that is pressed is the oldest thing on the web, and sending
     * somebody to Markdown for it would be pedantry.
     */
    private function picture(callable $text, string $style): string
    {
        $img = '<img'.$this->src($text('source'))
            .' alt="'.e($text('alt')).'"'
            .($text('width') !== '' ? ' width="'.e($text('width')).'"' : '')
            .($text('height') !== '' ? ' height="'.e($text('height')).'"' : '')
            .' loading="lazy"'.$style.'>';

        return $text('href') === '' ? $img : '<a href="'.e($text('href')).'">'.$img.'</a>';
    }

    /**
     * Something that plays. **Its flags are the element's own words** —
     * there or not there — and a poster only means anything on a video.
     *
     * A browser refuses to autoplay with sound, so autoplay without muted
     * would quietly do nothing; it is written as asked all the same, and
     * the pairing is the editor's to choose.
     */
    private function player(string $tag, callable $text, string $style): string
    {
        $out = '<'.$tag.$this->src($text('source'));

        if ($tag === 'video') {
            if (($poster = $this->source($text('poster'))) !== '') {
                $out .= ' poster="'.e($poster).'"';
            }

            foreach (['width', 'height'] as $attribute) {
                if ($text($attribute) !== '') {
                    $out .= ' '.$attribute.'="'.e($text($attribute)).'"';
                }
            }
        }

        if ($text('preload') !== '') {
            $out .= ' preload="'.e($text('preload')).'"';
        }

        foreach (['controls', 'autoplay', 'loop', 'muted', 'playsinline'] as $flag) {
            if ($text($flag) !== '') {
                $out .= ' '.$flag;
            }
        }

        return $out.$style.'></'.$tag.'>';
    }

    /**
     * A box for longer words. **Its value is its inside**, not an attribute
     * — that is the element — and only what was answered is written.
     */
    private function textarea(callable $text, string $style): string
    {
        $out = '<textarea name="'.e($text('name')).'"';

        foreach (['placeholder', 'rows', 'minlength', 'maxlength'] as $attribute) {
            if ($text($attribute) !== '') {
                $out .= ' '.$attribute.'="'.e($text($attribute)).'"';
            }
        }

        foreach (['required', 'disabled'] as $flag) {
            if ($text($flag) !== '') {
                $out .= ' '.$flag;
            }
        }

        return $out.$style.'>'.e($text('value')).'</textarea>';
    }

    /**
     * A field somebody fills in.
     *
     * **Only what was answered is written.** An empty box is not `max=""` —
     * an attribute nobody asked for changes how a browser behaves, so it is
     * left off entirely.
     */
    private function input(callable $text, string $style): string
    {
        $out = '<input type="'.e($text('type', 'text')).'" name="'.e($text('name')).'"';

        foreach (['placeholder', 'value', 'minlength', 'maxlength', 'pattern', 'title', 'min', 'max', 'step', 'accept'] as $attribute) {
            $said = $text($attribute);

            if ($said !== '') {
                $out .= ' '.$attribute.'="'.e($said).'"';
            }
        }

        // **They are there or they are not.** A boolean attribute says
        // everything by standing, and `required="0"` would be required all
        // the same.
        foreach (['required', 'disabled'] as $flag) {
            if ($text($flag) !== '') {
                $out .= ' '.$flag;
            }
        }

        return $out.$style.'>';
    }

    /**
     * ` src="…"`, or nothing at all.
     *
     * **An empty `src` is not empty to a browser** — it resolves to the page
     * itself and fetches it again — so an unfilled one is left off.
     */
    private function src(string $value, bool $throughTheLibrary = true): string
    {
        $url = $throughTheLibrary ? $this->source($value) : $value;

        return $url === '' ? '' : ' src="'.e($url).'"';
    }

    /**
     * A button. **`link` is a kind of button, not a kind of link**: the
     * element stays a `<button>` and the URL is walked to on the click.
     *
     * The URL is escaped twice over — once for the JavaScript string it sits
     * in, once for the attribute holding it — so **a quote in a URL cannot
     * become code.**
     */
    private function button(string $label, string $type, string $url, string $style = ''): string
    {
        if ($type === 'link') {
            $to = e(addcslashes($url, "\\'"));

            return '<button type="button" onclick="location.href=\''.$to.'\''.'"'.$style.'>'.e($label).'</button>';
        }

        return '<button type="'.e($type).'"'.$style.'>'.e($label).'</button>';
    }

    /**
     * Several inputs sharing one name, one to a choice — dots picking one,
     * squares picking any.
     *
     * **The words are pressable**: each input sits inside its own label, so
     * the writing beside the mark is as good a target as the mark. For a
     * radio, `required` on the first speaks for the group. **For a lone
     * checkbox it means "this must be ticked"; across several it is not
     * written at all** — to a browser each box is its own question, so
     * marking them all would demand every one, and "at least one of these"
     * is not something the element can say. Ticking several sends the name
     * several times, so it wears `[]` for the frameworks that read that as
     * a list.
     *
     * @param  array<int, mixed>  $options
     */
    private function choices(string $type, string $name, array $options, bool $required, bool $disabled, string $style): string
    {
        // **The tag stands before it is filled in**, here as everywhere: a
        // group with no choices yet shows one bare mark, so the Code pill
        // reads as a shape waiting for its options rather than as broken.
        if ($options === []) {
            return '<input type="'.$type.'" name="'.e($name).'"'.($disabled ? ' disabled' : '').$style.'>';
        }

        $many = $type === 'checkbox' && count($options) > 1;
        $out = [];

        foreach (array_values($options) as $index => $option) {
            // A choice is a value-and-label pair; a bare string is both at
            // once, and either half stands in for a missing other.
            $value = is_array($option) ? trim((string) ($option['value'] ?? '')) : (string) $option;
            $words = is_array($option) ? trim((string) ($option['label'] ?? '')) : (string) $option;
            $value = $value !== '' ? $value : $words;
            $words = $words !== '' ? $words : $value;

            // **Required is the group's** — on the first, which the element
            // reads as "one of these". Disabled is each row's own.
            $rowDisabled = $disabled || (is_array($option) && ! empty($option['disabled']));

            $out[] = '<label><input type="'.$type.'" name="'.e($name).($many ? '[]' : '').'" value="'.e($value).'"'
                .($required && ($type === 'checkbox' ? ! $many : $index === 0) ? ' required' : '')
                .($rowDisabled ? ' disabled' : '')
                .$style.'> '.e($words).'</label>';
        }

        return implode("\n", $out);
    }

    /**
     * One dropdown, its choices as value-and-label rows.
     *
     * **`multiple` sends the name several times**, so it wears `[]` the way
     * a checkbox group does — and the value is written only when it differs
     * from the words, since an option's words are its value by default.
     *
     * @param  array<int, mixed>  $options
     */
    private function select(string $name, array $options, bool $required, bool $disabled, bool $multiple, string $style = ''): string
    {
        $items = '';

        foreach ($options as $option) {
            $value = is_array($option) ? trim((string) ($option['value'] ?? '')) : (string) $option;
            $words = is_array($option) ? trim((string) ($option['label'] ?? '')) : (string) $option;
            $words = $words !== '' ? $words : $value;

            $items .= '<option'
                .($value !== '' && $value !== $words ? ' value="'.e($value).'"' : '')
                .(is_array($option) && ! empty($option['disabled']) ? ' disabled' : '')
                .'>'.e($words).'</option>'."\n";
        }

        // One option to a line: this is read and written on the Code pill.
        return '<select name="'.e($name).($multiple ? '[]' : '').'"'
            .($multiple ? ' multiple' : '')
            .($required ? ' required' : '')
            .($disabled ? ' disabled' : '')
            .$style.'>'
            .($items === '' ? '' : "\n".$this->indent(rtrim($items, "\n"))."\n")
            .'</select>';
    }

    /** A media path becomes its URL; anything else is taken as one already. */
    private function source(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if ($this->media->owns($value)) {
            return $this->media->find($value)?->url() ?? '';
        }

        return $value;
    }

    /**
     * The inner wrapper's style, from the arrangement's own words.
     *
     * A grid's columns read three ways — `auto` breathes with the screen, a
     * count divides it evenly, and a template like `1fr 2fr` says the ratio
     * outright. **Everything is checked again here**: a ghost's data arrives
     * unsaved, so the renderer trusts nothing it did not read as CSS.
     */
    private function arrangementStyle(Structure $structure): string
    {
        $data = $structure->data ?? [];

        $size = function (string $key) use ($data): string {
            $value = trim((string) ($data[$key] ?? ''));

            return $value !== '' && preg_match('/^[0-9a-z.% ]+$/i', $value) === 1 ? $value : '';
        };

        $gap = $size('gap') !== '' ? $size('gap') : '1.5rem';

        if ($structure->layout === 'row') {
            $justify = trim((string) ($data['justify'] ?? ''));
            $align = trim((string) ($data['align'] ?? ''));

            return 'display:flex'
                .';flex-wrap:'.((($data['wrap'] ?? true) == true) ? 'wrap' : 'nowrap')
                .';gap:'.$gap
                .(in_array($justify, Structure::JUSTIFIES, true) ? ';justify-content:'.$justify : '')
                .(in_array($align, Structure::ALIGNS, true) ? ';align-items:'.$align : '');
        }

        $columns = $size('columns');

        $template = match (true) {
            $columns === '' || $columns === 'auto' => 'repeat(auto-fit,minmax(16rem,1fr))',
            ctype_digit($columns) => 'repeat('.max(1, min((int) $columns, 12)).',minmax(0,1fr))',
            default => $columns,
        };

        return 'display:grid;grid-template-columns:'.$template.';gap:'.$gap;
    }

    /**
     * The part's own class, from its uuid — **the renderer's to give**:
     * deterministic, so the same part is the same class on every render,
     * and never stored, so nothing pins a part to a hash.
     */
    public static function stateClass(string $uuid): string
    {
        return 'bw-'.substr(md5($uuid), 0, 8);
    }

    /**
     * The hover's declarations, or nothing. Palette names resolve here the
     * way they do inline — and each carries `!important`, because a hover in
     * a stylesheet must outrank the rest of the look in a style attribute.
     */
    private function hoverCss(array $styleMap): string
    {
        $style = app(\Bladewright\Blocks\BlockManager::class)->sanitizeStyle($styleMap);
        $palette = app(\Bladewright\Support\Palette::class);

        $written = [];

        foreach (['hover-background' => 'background', 'hover-color' => 'color'] as $key => $property) {
            if (isset($style[$key])) {
                $written[] = $property.':'.$palette->resolve($style[$key]).' !important';
            }
        }

        if (isset($style['hover-opacity'])) {
            $written[] = 'opacity:'.$style['hover-opacity'].' !important';
        }

        return implode(';', $written);
    }

    private function bareDocument(Page $page, string $inner): string
    {
        $meta = \Bladewright\Support\Meta::tags($page);
        $analytics = app(\Bladewright\Support\Analytics::class)->scriptTags();
        $meta .= $analytics === '' ? '' : "\n    ".$analytics;

        // No frame, no `@bwstyles` — the bare head prints the render's own
        // gatherings itself.
        $collected = app(\Bladewright\Support\CollectedCss::class)->styleTag();
        $meta .= $collected === '' ? '' : "\n    ".$collected;
        $lang = e(str_replace('_', '-', $page->locale ?: app()->getLocale()));

        return <<<HTML
<!DOCTYPE html>
<html lang="{$lang}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {$meta}
</head>
<body>
{$inner}
</body>
</html>
HTML;
    }
}
