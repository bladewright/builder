<?php

use Livewire\Component;
use Bladewright\Access\Abilities;
use Bladewright\Blocks\BlockManager;
use Bladewright\Blocks\StructureManager;
use Bladewright\Models\Block;
use Bladewright\Models\Structure;
use Bladewright\Models\StructureChild;
use Bladewright\Site\PublicSite;
use Bladewright\Support\Toasts;

/*
 * Arranging a component: **the preview on the left, the arrangement on the
 * right** — the same posture as a page.
 *
 * What it holds are references: blocks, and components inside components.
 * **Spacing lives here and nowhere else** (padding and gap), which is why a
 * block never carries any.
 *
 * Three faces, on pills: what it looks like, how it is arranged, and the HTML
 * it comes out as — **and that last one can be written**, the same bargain a
 * block makes: write it and it becomes the component.
 */
new class extends Component
{
    use Toasts;
    use \Bladewright\Support\StyleCard;

    public Structure $component;

    /**
     * Standing inside another screen (the page editor's panel): **the cards
     * only** — the preview beside it is that screen's own.
     */
    public bool $embedded = false;

    /** What the picker would add: "block:intro" or "component:footer". */
    public string $adding = '';

    /** The tag's class attribute, whole — the Class card's one field. */
    public string $class = '';

    /** The component's own HTML, as it stands on the Code pill. */
    public string $markup = '';

    /**
     * Has anybody actually written that markup?
     *
     * **Until they have, the arrangement still makes the component**, and the
     * Code pill shows what it makes.
     */
    public bool $authored = false;

    /** The arrangement: the container's width, and how the contents stand. */
    public string $width = '';

    public string $gap = '';

    public string $layout = 'stack';

    public string $columns = '';

    public string $justify = '';

    public string $align = '';

    public bool $wrap = true;

    public bool $collapse = true;

    public function mount(): void
    {
        $data = $this->component->data ?? [];

        $this->width = (string) ($data['width'] ?? '');
        $this->gap = (string) ($data['gap'] ?? '');
        $this->layout = $this->component->layout;
        $this->columns = (string) ($data['columns'] ?? '');
        $this->justify = (string) ($data['justify'] ?? '');
        $this->align = (string) ($data['align'] ?? '');
        $this->wrap = (bool) ($data['wrap'] ?? true);
        $this->collapse = (bool) ($data['collapse'] ?? true);

        $this->seedStyleCard((array) ($data['style'] ?? []));

        $this->class = (string) ($data['class'] ?? '');

        $this->markup = (string) ($data['markup'] ?? '');
        $this->authored = $this->markup !== '';

        if (! $this->authored) {
            $this->markup = $this->generated();
        }
    }

    /** The whole list: a component's tag can wear all of it. */
    public function styleFields(): array
    {
        return app(\Bladewright\Blocks\BlockManager::class)->styleFields();
    }

    /**
     * What a card edit does to the code: regenerates it while nobody has
     * written any, and **rewrites only the tag's own style** while somebody
     * has — everything inside the written code stands.
     */
    private function reseed(): void
    {
        $this->seedCss();

        if (! $this->authored) {
            $this->markup = $this->generated();
        } else {
            $this->patchStyle();
        }

        $this->whisper();
    }

    /**
     * The component as the screen has it this moment — never saved.
     *
     * **One ghost for every asker**: the whisper, the preview and the Code
     * pill all dress the same clone, so an attribute added here reaches all
     * three at once.
     */
    private function ghost(?string $markup = null): Structure
    {
        $ghost = clone $this->component;
        $ghost->data = array_merge($this->component->data ?? [], [
            'markup' => $markup ?? $this->authoredMarkup(),
            'style' => $this->style,
            'class' => $this->class,
            'width' => $this->width,
            'gap' => $this->gap,
            'layout' => in_array($this->layout, Structure::LAYOUTS, true) ? $this->layout : 'stack',
            'columns' => $this->columns,
            'justify' => $this->justify,
            'align' => $this->align,
            'wrap' => $this->wrap,
            'collapse' => $this->collapse,
        ]);

        return $ghost;
    }

    /**
     * **The holder hears every unsaved edit — as data, never as HTML.**
     * The page renders the ghost inside its own draft context, stamps and
     * all; handing it markup would lose both.
     */
    private function whisper(): void
    {
        if ($this->embedded) {
            $this->dispatch('bw-part-drafted',
                uuid: $this->component->uuid,
                kind: 'component',
                data: $this->ghost()->data,
            );
        }
    }

    /** The Class card reaches the code the way every card does. */
    public function updatedClass(): void
    {
        $this->reseed();
    }

    /**
     * **The Arrangement card too**: every field of it reaches the code and
     * the preview the moment it is typed, saved or not.
     */
    public function updated(string $name): void
    {
        if (in_array($name, ['width', 'gap', 'layout', 'columns', 'justify', 'align', 'wrap', 'collapse'], true)) {
            $this->reseed();
        }
    }

    /** The tag's style attribute, set from the cards on written code. */
    private function patchStyle(): void
    {
        if (preg_match('/\{\{|\{!!|@[a-z]/i', $this->markup) === 1) {
            return;
        }

        $document = new \DOMDocument;
        $ok = @$document->loadHTML(
            '<meta charset="utf-8"><body>'.$this->markup.'</body>',
            LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_HTML_NODEFDTD,
        );

        if (! $ok) {
            return;
        }

        $fresh = new \DOMDocument;

        if (! @$fresh->loadHTML(
            '<meta charset="utf-8"><body>'.$this->generated().'</body>',
            LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_HTML_NODEFDTD,
        )) {
            return;
        }

        $old = $this->firstElement($document);
        $new = $this->firstElement($fresh);

        if ($old === null || $new === null) {
            return;
        }

        $new->getAttribute('style') === ''
            ? $old->removeAttribute('style')
            : $old->setAttribute('style', $new->getAttribute('style'));

        // The class is the tag's dress too, and follows the same way —
        // less the machine's own, which is the renderer's to give each serve.
        $class = app(BlockManager::class)->sanitizeClass($new->getAttribute('class'));

        $class === ''
            ? $old->removeAttribute('class')
            : $old->setAttribute('class', $class);

        $body = $document->getElementsByTagName('body')->item(0);
        $out = '';

        foreach ($body->childNodes as $node) {
            $out .= $document->saveHTML($node);
        }

        $this->markup = $out;
    }

    private function firstElement(\DOMDocument $document): ?\DOMElement
    {
        $body = $document->getElementsByTagName('body')->item(0);

        foreach ($body?->childNodes ?? [] as $node) {
            if ($node instanceof \DOMElement) {
                return $node;
            }
        }

        return null;
    }

    /** Typing in the Code pill makes the markup somebody's own. */
    public function updatedMarkup(): void
    {
        $this->authored = true;

        $this->readTagBack();

        // **Whatever was typed — Blade, broken, or plain** — the holder
        // hears it: an early bail above the whisper once left Blade edits
        // invisible until some other card was touched.
        $this->whisper();
    }

    /** The tag's style and class read back into the cards, when they can. */
    private function readTagBack(): void
    {
        if (preg_match('/\{\{|\{!!|@[a-z]/i', $this->markup) === 1) {
            return;
        }

        $document = new \DOMDocument;

        if (! @$document->loadHTML(
            '<meta charset="utf-8"><body>'.$this->markup.'</body>',
            LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_HTML_NODEFDTD,
        )) {
            return;
        }

        $element = $this->firstElement($document);

        if ($element !== null) {
            // Sanitized on the way back in: the machine's own classes in
            // generated code never reach the Class card.
            $this->class = app(BlockManager::class)->sanitizeClass($element->getAttribute('class'));
            $this->css = trim($element->getAttribute('style'));
            $this->updatedCss();
        }
    }

    /** What the arrangement makes of it — the Code pill's starting point. */
    public function generated(): string
    {
        // Markup empty on purpose: this IS the markup's starting point.
        return app(PublicSite::class)->assembled($this->ghost(markup: ''));
    }

    /**
     * What is stored: nothing while the arrangement still leads, and
     * nothing when the box was emptied — **never a render-and-compare**:
     * deciding authorship by whether the code happens to equal the
     * generated document made Save able to erase hand-written markup.
     */
    private function authoredMarkup(): string
    {
        return $this->authored && trim($this->markup) !== '' ? $this->markup : '';
    }

    /**
     * What the Blade in the Code pill went wrong with, if anything.
     *
     * **Said where it can be fixed.** On the site a broken component leaves a
     * comment behind rather than taking the page down.
     */
    public function codeError(): ?string
    {
        if (! $this->authored || trim($this->markup) === '') {
            return null;
        }

        try {
            PublicSite::runBlade($this->markup);
        } catch (\Throwable $e) {
            return $e->getMessage();
        }

        return null;
    }

    /**
     * **One screen, one Save.** The arrangement and the markup are both what
     * this component is, and they are settled together — the arrangement
     * first, since it is the one that can refuse.
     */
    /** **One Save on the whole desk**: the page's button reaches in here. */
    #[\Livewire\Attributes\On('bw-save-part')]
    public function saveWithTheHolder(): void
    {
        if ($this->embedded) {
            $this->save();
        }
    }

    public function save(): void
    {
        $this->saveArrangement();
        app(StructureManager::class)->saveStyle($this->component, $this->style);
        app(StructureManager::class)->saveClass($this->component, $this->class);
        $this->saveMarkup();

        // Whoever holds this panel refreshes their own preview on it.
        $this->dispatch('bw-component-saved');
    }

    /** Write the markup, and say how far it reached. */
    public function saveMarkup(): void
    {
        $this->authorize(Abilities::gate(Abilities::EDIT_CONTENT));

        app(StructureManager::class)->saveMarkup($this->component, $this->authoredMarkup());

        $this->component->refresh();

        $pages = app(\Bladewright\Blocks\SitePages::class)->pagesShowing($this->component);

        $this->toast($pages > 1
            ? __('Saved. It changed on :n pages at once.', ['n' => $pages])
            : __('Saved.'));
    }

    /**
     * The whole arrangement, as a tree: what stands inside, and inside that
     * again. **A block's words are one press away** — its name is a link.
     *
     * The top row is this component's own, so it carries a place to be
     * dragged to and a way out; **what is below belongs to its own
     * component's screen** and is here to be read.
     *
     * @return array<int, array<string, mixed>>
     */
    public function tree(): array
    {
        $inside = $this->component->children->values()
            ->map(fn ($child, $index) => ($node = $this->node($child->child_kind, $child->child_uuid)) === null
                ? null
                : ['path' => (string) $index, 'movable' => true, 'remove' => 'remove('.$child->id.')'] + $node)
            ->filter()
            ->values()
            ->all();

        // **The component's own tag stands over them.** What is being
        // arranged sits inside a `<section>`, and the tree says so rather
        // than leaving the row to be imagined.
        return [[
            'kind' => 'component',
            'name' => $this->component->name,
            'type' => $this->component->type,
            'url' => null,
            'path' => null,
            'movable' => false,
            'remove' => null,
            'holds' => true,
            'children' => $inside,
        ]];
    }

    /**
     * Put what was dragged where it was dropped. **Only this component's own
     * row moves**; the paths below it are not draggable.
     */
    public function moveTo(string $from, string $to): void
    {
        $this->authorize(Abilities::gate(Abilities::EDIT_CONTENT));

        if (! ctype_digit($from) || ! ctype_digit($to)) {
            return;
        }

        app(StructureManager::class)->reorderChild($this->component, (int) $from, (int) $to);

        $this->component->refresh();

        if (! $this->authored) {
            $this->markup = $this->generated();
        }

        $this->dispatch('bw-component-saved');
    }

    /** @return array<string, mixed>|null */
    private function node(string $kind, string $uuid): ?array
    {
        if ($kind === StructureChild::KIND_BLOCK) {
            $block = Block::query()->where('uuid', $uuid)->first();

            return $block === null ? null : [
                'kind' => 'block',
                'name' => $block->name,
                'type' => $block->type,
                'url' => route('bladewright.admin.blocks.edit', $block),
                'children' => [],
                'path' => null,
                'movable' => false,
                'remove' => null,
            ];
        }

        $structure = Structure::query()->where('uuid', $uuid)->first();

        if ($structure === null) {
            return null;
        }

        return [
            'kind' => 'component',
            'name' => $structure->name,
            'type' => $structure->type,
            'url' => route('bladewright.admin.components.edit', $structure),
            'path' => null,
            'movable' => false,
            'remove' => null,
            'children' => $structure->children
                ->map(fn ($child) => $this->node($child->child_kind, $child->child_uuid))
                ->filter()
                ->values()
                ->all(),
        ];
    }

    /**
     * The component as the site would serve it, **from what is on the screen
     * right now** — the markup unsaved, the rest as it stands.
     *
     * It comes back as a whole document for the frame: inside an iframe the
     * admin's own CSS cannot reach it, so the device widths mean something.
     */
    public function preview(): string
    {
        $inner = app(PublicSite::class)->structure($this->ghost());
        // Rendering just gathered the part's hover rules — the frame's
        // head prints them, the way a page's own head would.
        $collected = app(\Bladewright\Support\CollectedCss::class)->styleTag();
        $lang = e(str_replace('_', '-', app()->getLocale()));

        $stylesheet = e(route('bladewright.site.css', ['v' => app(\Bladewright\Support\SiteCss::class)->version()]));

        // **The preview wears what the site wears** — the declared framework
        // — or a class like `btn btn-primary` would look like nothing here
        // while meaning everything on the page.
        $framework = app(\Bladewright\Support\Framework::class)->linkTag();

        return <<<HTML
        <!DOCTYPE html>
        <html lang="{$lang}">
        <head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
        {$framework}
        <link rel="stylesheet" href="{$stylesheet}">
        {$collected}
        <style>
            /* Only enough to read it by — and **no padding of the frame's
               own**: a full-width band has to be seen reaching the edges,
               and any margin here would be a margin the page does not have.
               **And no ink of the frame's own**: the framework and the
               colour scheme decide it, or a dark preview drowns a
               hard-coded grey. */
            body { margin: 0; font-family: ui-sans-serif, system-ui, sans-serif; line-height: 1.7; }
            img, video { max-width: 100%; height: auto; }
        </style>
        </head>
        <body>{$inner}</body>
        </html>
        HTML;
    }

    /** What can be put in: every block, and every other component. */
    public function choices(): array
    {
        $out = [];

        foreach (Block::query()->orderBy('name')->get() as $block) {
            $out['block:'.$block->name] = __('Block').' — '.$block->name;
        }

        // The layout's own kinds are not on this shelf.
        foreach (Structure::query()->where('uuid', '!=', $this->component->uuid)->whereNotIn('type', \Bladewright\Models\Layout::BANDS_TYPES)->orderBy('name')->get() as $structure) {
            $out['component:'.$structure->name] = __('Component').' — '.$structure->name;
        }

        return $out;
    }

    public function add(): void
    {
        $this->authorize(Abilities::gate(Abilities::EDIT_CONTENT));

        [$kind, $name] = array_pad(explode(':', $this->adding, 2), 2, '');

        try {
            if ($kind === 'block' && ($block = app(BlockManager::class)->find($name))) {
                app(StructureManager::class)->insertBlock($this->component, $block);
            } elseif ($kind === 'component' && ($child = app(StructureManager::class)->find($name))) {
                app(StructureManager::class)->insertComponent($this->component, $child);
            } else {
                $this->toastError(__('Choose something to put in first.'));

                return;
            }
        } catch (\InvalidArgumentException $e) {
            $this->toastError($e->getMessage());

            return;
        }

        $this->adding = '';
        $this->component->refresh();

        if (! $this->authored) {
            $this->markup = $this->generated();
        }

        $this->dispatch('bw-component-saved');
        $this->toast(__('Placed.'));
    }

    /** Out of this component. **What it pointed at stays on the shelf.** */
    public function remove(int $childId): void
    {
        $this->authorize(Abilities::gate(Abilities::EDIT_CONTENT));

        app(StructureManager::class)->removeChild($this->component, $childId);

        $this->component->refresh();

        if (! $this->authored) {
            $this->markup = $this->generated();
        }

        $this->dispatch('bw-component-saved');
        $this->toast(__('Taken out. What it held is still on the shelf.'));
    }

    public function saveArrangement(): void
    {
        $this->authorize(Abilities::gate(Abilities::EDIT_CONTENT));

        try {
            app(StructureManager::class)->saveArrangement($this->component, [
                'width' => $this->width,
                'gap' => $this->gap,
                'columns' => $this->columns,
                'justify' => $this->justify,
                'align' => $this->align,
                'wrap' => $this->wrap,
                'collapse' => $this->collapse,
            ], $this->layout);
        } catch (\InvalidArgumentException $e) {
            $this->toastError($e->getMessage());

            return;
        }

        $this->component->refresh();

        if (! $this->authored) {
            $this->markup = $this->generated();
        }

        // **The reach is said with the result.**
        $pages = app(\Bladewright\Blocks\SitePages::class)->pagesShowing($this->component);

        $this->toast($pages > 1
            ? __('Saved. It changed on :n pages at once.', ['n' => $pages])
            : __('Saved.'));
    }
};
?>

<div @class(['flex flex-col gap-4 lg:flex-row lg:items-start' => ! $embedded])>
    {{-- **One card, the preview inside it**, with the widths at the right.
         Embedded in another screen, that screen's preview is the preview —
         only the cards come. --}}
    @unless ($embedded)
    {{-- **The preview follows.** The column beside it is long, and what is
         being changed has to stay in sight while it is changed. --}}
    <div class="min-w-0 flex-1 overflow-hidden rounded-xl border border-gray-200 bg-white lg:sticky lg:top-4 lg:self-start dark:border-gray-800 dark:bg-gray-900">
        <div class="flex flex-wrap items-center gap-3 border-b border-gray-100 px-3 py-2 dark:border-gray-800">
            {{-- Two faces: what it looks like, and the HTML it comes out as.
                 **The arrangement is not one of them** — it is the column
                 beside this, where it can be dragged. --}}
            <div class="inline-flex gap-1 rounded-lg bg-gray-200/70 p-0.5 dark:bg-gray-800">
                <button type="button" class="bw-pill inline-flex cursor-pointer items-center gap-1.5 rounded-md border-0 bg-transparent px-3 py-1 text-[0.8125rem]/5 font-medium text-gray-500 transition hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100"
                        data-bw-pills="component" data-bw-pill="preview"><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg>{{ __('Preview') }}</button>
                <button type="button" class="bw-pill inline-flex cursor-pointer items-center gap-1.5 rounded-md border-0 bg-transparent px-3 py-1 text-[0.8125rem]/5 font-medium text-gray-500 transition hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100"
                        data-bw-pills="component" data-bw-pill="code"><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m16 18 6-6-6-6"/><path d="m8 6-6 6 6 6"/></svg>{{ __('Code') }}</button>
            </div>

            <span class="hidden text-[0.75rem] text-gray-500 sm:inline dark:text-gray-400">{{ __('before it is saved') }}</span>

            <span class="flex-1"></span>

            <span class="flex" data-bw-pills="component" data-bw-panel="preview">
                @include('bladewright::admin.scheme-pills')
            </span>

            <div class="inline-flex gap-0.5 rounded-lg bg-gray-200/70 p-0.5 dark:bg-gray-800"
                 data-bw-pills="component" data-bw-panel="preview">
                <button type="button" class="bw-device bw-tip inline-flex h-7 cursor-pointer items-center justify-center rounded-md px-2 text-gray-500 transition dark:text-gray-400"
                        data-bw-device="desktop" data-tip="{{ __('Desktop') }}" aria-label="{{ __('Desktop') }}">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                        <rect x="3" y="4" width="18" height="12" rx="2"/><path stroke-linecap="round" d="M8 20h8m-4-4v4"/>
                    </svg>
                </button>
                <button type="button" class="bw-device bw-tip inline-flex h-7 cursor-pointer items-center justify-center rounded-md px-2 text-gray-500 transition dark:text-gray-400"
                        data-bw-device="tablet" data-tip="{{ __('Tablet') }}" aria-label="{{ __('Tablet') }}">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                        <rect x="5" y="3" width="14" height="18" rx="2"/><path stroke-linecap="round" d="M11 18h2"/>
                    </svg>
                </button>
                <button type="button" class="bw-device bw-tip inline-flex h-7 cursor-pointer items-center justify-center rounded-md px-2 text-gray-500 transition dark:text-gray-400"
                        data-bw-device="phone" data-tip="{{ __('Phone') }}" aria-label="{{ __('Phone') }}">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                        <rect x="7" y="2" width="10" height="20" rx="2.5"/><path stroke-linecap="round" d="M11 18.5h2"/>
                    </svg>
                </button>
            </div>

            <span class="bw-device-size font-mono text-[0.75rem] text-gray-400"
                  data-bw-pills="component" data-bw-panel="preview"></span>

            {{-- **Saving stands where the work is looked at**, and settles the
                 whole screen: the arrangement and the code together. --}}
            @can(\Bladewright\Access\Abilities::gate(\Bladewright\Access\Abilities::EDIT_CONTENT))
                <button type="button" wire:click="save"
                        class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg bg-linear-to-tl from-bw-accent-2 to-bw-accent px-3 py-1.5 text-[0.8125rem] font-semibold text-white shadow-xs transition hover:brightness-110">
                    {{ __('Save') }}
                </button>
            @endcan
        </div>

        {{-- **The component alone**, through the real renderer. srcdoc rather
             than a route, so what is shown follows what is being written
             rather than what was last saved. --}}
        <div class="bw-preview-stage bg-gray-100 dark:bg-gray-950"
             data-bw-pills="component" data-bw-panel="preview">
            <iframe class="block h-[65vh] w-full border-0 bg-white"
                    title="{{ __('Preview') }}"
                    srcdoc="{{ $this->preview() }}"></iframe>
        </div>

        {{-- **The component's own HTML.** It starts as what the arrangement
             makes; write it and this is the component — Blade of the site's
             own, run when the page is asked for. --}}
        <div class="p-4" data-bw-pills="component" data-bw-panel="code" hidden
             data-bw-code-doc="{{ $markup }}">
            <p class="m-0 mb-2 text-[0.8125rem] text-gray-500 dark:text-gray-400">
                {{ $authored
                    ? __('Written by hand. The arrangement no longer reaches the page; empty this out to hand it back.')
                    : __('What the arrangement makes. Change it and it becomes the component — Blade, so loops, values and queries all work.') }}
            </p>

            @if ($error = $this->codeError())
                <div class="mb-2 rounded-lg border border-red-200 bg-red-50 px-3 py-2 font-mono text-[0.8125rem] text-red-900 dark:border-red-900 dark:bg-red-950 dark:text-red-100">
                    {{ $error }}
                </div>
            @endif

            <div wire:ignore>
                <textarea rows="18" data-bw-code="html" spellcheck="false"
                          class="w-full resize-y rounded-lg border border-gray-200 bg-gray-100 p-4 font-mono text-[0.8125rem]/6 focus:outline-2 focus:outline-offset-1 focus:outline-bw-accent dark:border-gray-700 dark:bg-gray-800"
                          wire:model.live.debounce.500ms="markup">{{ $markup }}</textarea>
            </div>
        </div>
    </div>

    @endunless

    <div @class(['w-full space-y-4', 'shrink-0 lg:w-[26rem]' => ! $embedded])>

        {{-- **No Structure card in the panel**: the page's own Structure
             face holds the words, open under every title. The panel is the
             component itself — how it sits and what it wears. --}}
        @unless ($embedded)
        <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
            {{-- **What it is built of** — the tag and what stands in it. The
                 card below is how those sit; this one is what they are. --}}
            <h2 class="m-0 text-base font-semibold">{{ __('Structure') }}</h2>

            <p class="mt-1 text-[0.8125rem] text-gray-500 dark:text-gray-400">
                {{ __('Blocks and other components, top to bottom. Drag to change the order; their words are edited on their own screens.') }}
            </p>

            @if ($authored)
                {{-- **Said, not hidden.** What is on the Code pill is what the
                     page shows; this is still the way back. --}}
                <div class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-[0.8125rem] text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-100">
                    {{ __('The code is written by hand, so this arrangement is not what the page shows. Empty the Code pill to hand it back.') }}
                </div>
            @endif

            {{-- **Dragged into place**, inside the component's own tag. The
                 order is what the page shows, so it is changed by moving the
                 thing itself. --}}
            <ul class="m-0 mt-4 list-none space-y-1.5 p-0">
                @foreach ($this->tree() as $node)
                    @include('bladewright::admin.arrange-node', ['node' => $node, 'depth' => 0])
                @endforeach
            </ul>

            @can(\Bladewright\Access\Abilities::gate(\Bladewright\Access\Abilities::EDIT_CONTENT))
                @php($choices = $this->choices())
                <div class="mt-4 border-t border-gray-100 pt-4 dark:border-gray-800">
                    @if ($choices === [])
                        <p class="m-0 text-[0.8125rem] text-gray-500 dark:text-gray-400">{{ __('There is nothing to put in yet. Make a block first.') }}</p>
                    @else
                        <div class="flex items-center gap-2">
                            <select class="min-w-0 flex-1 rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-[0.8125rem] shadow-xs transition focus:border-bw-accent focus:ring-2 focus:ring-bw-accent/20 focus:outline-none dark:border-gray-700 dark:bg-gray-950"
                                    wire:model="adding">
                                <option value="">{{ __('Choose something…') }}</option>
                                @foreach ($choices as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <button type="button" wire:click="add"
                                    class="inline-flex shrink-0 cursor-pointer items-center gap-1.5 rounded-lg bg-linear-to-tl from-bw-accent-2 to-bw-accent px-3 py-1.5 text-[0.8125rem] font-semibold text-white shadow-xs transition hover:brightness-110">
                                {{ __('Put it in') }}
                            </button>
                        </div>
                    @endif
                </div>
            @endcan
        </div>
        @endunless

        @can(\Bladewright\Access\Abilities::gate(\Bladewright\Access\Abilities::EDIT_CONTENT))
            {{-- **How the contents stand.** Stacked is the block-level
                 default; a grid divides the width, a row lines them up —
                 and each layout then asks only its own questions. --}}
            <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
                <h2 class="m-0 text-base font-semibold">{{ __('Arrangement') }}</h2>

                {{-- **The container stands at section level**: the tag is
                     the full-width band, and this holds the words to the
                     middle of it. Empty reaches the edges. --}}
                <div class="mt-4 flex items-start gap-3">
                    <label class="w-24 shrink-0 pt-2 font-mono text-[0.75rem] text-gray-600 dark:text-gray-400">container</label>
                    <input type="text" placeholder="64rem"
                           class="min-w-0 flex-1 rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 font-mono text-[0.75rem] shadow-xs transition focus:border-bw-accent focus:ring-2 focus:ring-bw-accent/20 focus:outline-none dark:border-gray-700 dark:bg-gray-950"
                           wire:model.live.debounce.500ms="width">
                </div>

                <div class="mt-4 flex items-start gap-3">
                    <label class="w-24 shrink-0 pt-2 font-mono text-[0.75rem] text-gray-600 dark:text-gray-400">layout</label>
                    <div class="inline-flex flex-1 gap-1 rounded-lg bg-gray-200/70 p-0.5 dark:bg-gray-800">
                        @foreach (['stack' => __('Stacked'), 'grid' => __('Grid'), 'row' => __('Row')] as $value => $label)
                            <button type="button" wire:click="$set('layout', '{{ $value }}')"
                                    @class([
                                        'flex-1 cursor-pointer rounded-md border-0 px-3 py-1 text-[0.8125rem]/5 font-medium transition',
                                        'bg-white text-gray-900 shadow-xs dark:bg-gray-950 dark:text-gray-100' => $layout === $value,
                                        'bg-transparent text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100' => $layout !== $value,
                                    ])>{{ $label }}</button>
                        @endforeach
                    </div>
                </div>

                @if ($layout === 'grid')
                    {{-- `auto` breathes with the screen; `3` divides it
                         evenly; `1fr 2fr` says the ratio outright. --}}
                    <div class="mt-4 flex items-start gap-3">
                        <label class="w-24 shrink-0 pt-2 font-mono text-[0.75rem] text-gray-600 dark:text-gray-400">columns</label>
                        <div class="min-w-0 flex-1">
                            <input type="text" placeholder="auto"
                                   class="w-full rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 font-mono text-[0.75rem] shadow-xs transition focus:border-bw-accent focus:ring-2 focus:ring-bw-accent/20 focus:outline-none dark:border-gray-700 dark:bg-gray-950"
                                   wire:model.live.debounce.500ms="columns">
                            <p class="mt-1 mb-0 text-[0.6875rem] text-gray-500 dark:text-gray-400">{{ __('auto — as many as fit · 3 — three even columns · 1fr 2fr — this ratio') }}</p>
                        </div>
                    </div>
                @endif

                @if ($layout === 'row')
                    <div class="mt-4 flex items-start gap-3">
                        <label class="w-24 shrink-0 pt-2 font-mono text-[0.75rem] text-gray-600 dark:text-gray-400">justify</label>
                        <select wire:model.live="justify"
                                class="min-w-0 flex-1 rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-[0.8125rem] shadow-xs transition focus:border-bw-accent focus:ring-2 focus:ring-bw-accent/20 focus:outline-none dark:border-gray-700 dark:bg-gray-950">
                            <option value="">{{ __('As they come') }}</option>
                            <option value="start">{{ __('To the start') }}</option>
                            <option value="center">{{ __('To the middle') }}</option>
                            <option value="end">{{ __('To the end') }}</option>
                            <option value="space-between">{{ __('Spread apart') }}</option>
                        </select>
                    </div>

                    <div class="mt-4 flex items-start gap-3">
                        <label class="w-24 shrink-0 pt-2 font-mono text-[0.75rem] text-gray-600 dark:text-gray-400">align</label>
                        <select wire:model.live="align"
                                class="min-w-0 flex-1 rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-[0.8125rem] shadow-xs transition focus:border-bw-accent focus:ring-2 focus:ring-bw-accent/20 focus:outline-none dark:border-gray-700 dark:bg-gray-950">
                            <option value="">{{ __('Stretch to match') }}</option>
                            <option value="start">{{ __('Top') }}</option>
                            <option value="center">{{ __('Middle') }}</option>
                            <option value="end">{{ __('Bottom') }}</option>
                        </select>
                    </div>

                    <label class="mt-4 flex cursor-pointer items-center gap-2 text-[0.8125rem] text-gray-700 dark:text-gray-200">
                        <input type="checkbox" wire:model.live="wrap" class="rounded border-gray-300 text-bw-accent focus:ring-bw-accent/30 dark:border-gray-600 dark:bg-gray-950">
                        {{ __('Wrap onto the next line when the room runs out') }}
                    </label>
                @endif

                @if ($layout !== 'stack')
                    <label class="mt-4 flex cursor-pointer items-center gap-2 text-[0.8125rem] text-gray-700 dark:text-gray-200">
                        <input type="checkbox" wire:model.live="collapse" class="rounded border-gray-300 text-bw-accent focus:ring-bw-accent/30 dark:border-gray-600 dark:bg-gray-950">
                        {{ __('Stack on small screens (below 40rem)') }}
                    </label>

                    <div class="mt-4 flex items-start gap-3">
                        <label class="w-24 shrink-0 pt-2 font-mono text-[0.75rem] text-gray-600 dark:text-gray-400">gap</label>
                        <input type="text" placeholder="1.5rem"
                               class="min-w-0 flex-1 rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 font-mono text-[0.75rem] shadow-xs transition focus:border-bw-accent focus:ring-2 focus:ring-bw-accent/20 focus:outline-none dark:border-gray-700 dark:bg-gray-950"
                               wire:model.live.debounce.500ms="gap">
                    </div>
                @endif
            </div>

            @include('bladewright::admin.class-card')

            {{-- **The same card the blocks wear**, on the component's whole
                 tag: the band of colour a page is mostly made of. --}}
            @include('bladewright::admin.style-card')
        @endcan
    </div>
</div>
