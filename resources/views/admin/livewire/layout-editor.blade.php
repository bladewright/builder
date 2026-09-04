<?php

use Livewire\Component;
use Bladewright\Access\Abilities;
use Bladewright\Blocks\LayoutManager;
use Bladewright\Blocks\SitePages;
use Bladewright\Blocks\StructureManager;
use Bladewright\Models\Layout;
use Bladewright\Models\Structure;
use Bladewright\Site\PublicSite;
use Bladewright\Support\Toasts;

/*
 * Writing the frame a page is worn in.
 *
 * **The whole document is here**, and it is the site's own Blade — the same
 * text the renderer runs when a page is asked for, with `{{ $slot }}` where
 * the page's own content goes. The preview beside it is that text through
 * the real renderer before anything is saved: **what you see is what the
 * site will serve.**
 */
new class extends Component
{
    use Toasts;

    public Layout $layout;

    /** The frame, as it stands on the screen. */
    public string $content = '';

    public function mount(): void
    {
        $this->content = (string) $this->layout->content;
        $this->fontFamily = (string) $this->layout->font_family;

        foreach (Layout::BANDS as $band) {
            $this->bands[$band] = app(LayoutManager::class)->worn($this->layout, $band)?->name ?? '';
        }
    }

    /** The typeface every page in this frame reads in — '' is the framework's own. */
    public string $fontFamily = '';

    /** Settled the moment it is typed, the way the bands are. */
    public function updatedFontFamily(): void
    {
        $this->authorize(Abilities::gate(Abilities::EDIT_CONTENT));

        try {
            app(LayoutManager::class)->saveTypeface($this->layout, $this->fontFamily);
        } catch (\InvalidArgumentException $e) {
            $this->toastError($e->getMessage());

            return;
        }

        $pages = $this->pagesWearing();

        $this->toast($pages > 1
            ? __('Saved. It changed on :n pages at once.', ['n' => $pages])
            : __('Saved.'));
    }

    /** Which component each band wears, by name — '' is none. */
    public array $bands = [];

    /**
     * The three bands, as the same tree the other screens show.
     *
     * **The middle one is the page's own**, and nothing else may stand
     * there — it is a row like the others so the frame can be read at a
     * glance, but nothing is offered for it.
     *
     * @return array<int, array<string, mixed>>
     */
    public function rows(): array
    {
        return [
            $this->band('header'),
            [
                'kind' => 'page',
                'type' => 'main',
                'name' => __('The page itself'),
                'url' => null,
                'path' => null,
                'movable' => false,
                'remove' => null,
                'children' => [],
                'band' => 'main',
                'worn' => null,
            ],
            $this->band('footer'),
        ];
    }

    /** @return array<string, mixed> */
    private function band(string $band): array
    {
        $component = app(LayoutManager::class)->worn($this->layout, $band);

        return [
            'kind' => $component === null ? 'empty' : 'component',
            'type' => $band,
            'name' => $component?->name ?? __('Nothing here yet.'),
            'url' => $component === null ? null : route('bladewright.admin.components.edit', $component),
            'path' => null,
            'movable' => false,
            'remove' => $component === null ? null : "bare('{$band}')",
            'children' => $component === null ? [] : $this->inside($component),
            'band' => $band,
            'worn' => $component,
        ];
    }

    /**
     * What is inside a worn component, to be read here and arranged there.
     *
     * @return array<int, array<string, mixed>>
     */
    private function inside(Structure $component): array
    {
        return $component->children->map(function ($child) {
            if ($child->child_kind === \Bladewright\Models\StructureChild::KIND_BLOCK) {
                $block = \Bladewright\Models\Block::query()->where('uuid', $child->child_uuid)->first();

                return $block === null ? null : [
                    'kind' => 'block',
                    'type' => $block->type,
                    'name' => $block->name,
                    'url' => route('bladewright.admin.blocks.edit', $block),
                    'path' => null,
                    'movable' => false,
                    'remove' => null,
                    'children' => [],
                ];
            }

            $inner = Structure::query()->where('uuid', $child->child_uuid)->first();

            return $inner === null ? null : [
                'kind' => 'component',
                'type' => $inner->type,
                'name' => $inner->name,
                'url' => route('bladewright.admin.components.edit', $inner),
                'path' => null,
                'movable' => false,
                'remove' => null,
                'children' => [],
            ];
        })->filter()->values()->all();
    }

    /** Take the band's component out. **It stays on the shelf.** */
    public function bare(string $band): void
    {
        $this->authorize(Abilities::gate(Abilities::EDIT_CONTENT));

        app(LayoutManager::class)->wear($this->layout, $band, null);

        $this->layout->refresh();
        $this->bands[$band] = '';

        $this->toast(__('Taken out. The component itself is still on the shelf.'));
    }

    /**
     * What can stand in this band: **only its own kind.** A band starts
     * from its tag, so the header band offers header components and
     * nothing else.
     *
     * @return array<int, string>
     */
    public function choices(string $band): array
    {
        return Structure::query()->where('type', $band)->orderBy('name')->pluck('name')->all();
    }

    /**
     * Put the chosen component into that band.
     *
     * **The same grammar as every other screen**: choose, put it in, take it
     * out with the ×. Nothing is swapped in place — that is not an operation
     * this system has anywhere.
     */
    public function wear(string $band): void
    {
        $this->authorize(Abilities::gate(Abilities::EDIT_CONTENT));

        $name = trim((string) ($this->bands[$band] ?? ''));
        $component = $name === '' ? null : app(StructureManager::class)->find($name);

        if ($component === null) {
            $this->toastError(__('Choose something to put in first.'));

            return;
        }

        try {
            app(LayoutManager::class)->wear($this->layout, $band, $component);
        } catch (\InvalidArgumentException $e) {
            $this->toastError($e->getMessage());

            return;
        }

        $this->layout->refresh();
        $this->bands[$band] = '';

        $pages = $this->pagesWearing();

        $this->toast($pages > 1
            ? __('Placed. It changed on :n pages at once.', ['n' => $pages])
            : __('Placed.'));
    }

    /** The bands that can be worn in, for the pickers. @return array<int, string> */
    public function wearable(): array
    {
        return Layout::BANDS;
    }

    /** Does the page's content still have somewhere to go? */    /** Does the page's content still have somewhere to go? */
    public function holdsThePage(): bool
    {
        return app(LayoutManager::class)->holdsThePage($this->content);
    }

    /**
     * What the frame went wrong with, if anything.
     *
     * **Said where it can be fixed.** A frame that throws would take every
     * page wearing it down with it, so it is caught here first.
     */
    public function frameError(): ?string
    {
        try {
            $this->asServed();
        } catch (\Throwable $e) {
            return $e->getMessage();
        }

        return null;
    }

    /** How many pages wear this frame. */
    public function pagesWearing(): int
    {
        return app(SitePages::class)->pagesWearing($this->layout);
    }

    public function save(): void
    {
        $this->authorize(Abilities::gate(Abilities::EDIT_CONTENT));

        app(LayoutManager::class)->saveFrame($this->layout, $this->content);

        // **The reach is said with the result.**
        $pages = $this->pagesWearing();

        $this->toast($pages > 1
            ? __('Saved. It changed on :n pages at once.', ['n' => $pages])
            : __('Saved.'));
    }

    /**
     * The frame as the site would serve it, **from what is on the screen
     * right now** — saved or not. (Not `rendered()`: Livewire calls a method
     * of that name itself, after every render.)
     *
     * The slot is left empty: what is being looked at is the frame, and a
     * page's own content is a different screen's business.
     */
    private function asServed(): string
    {
        return PublicSite::runBlade($this->content, app(PublicSite::class)->bands($this->layout));
    }

    /** The same, for the frame to show. It throws only where that is handled. */
    public function preview(): string
    {
        try {
            return $this->asServed();
        } catch (\Throwable $e) {
            return '';
        }
    }
};
?>

<div class="flex flex-col gap-4 lg:flex-row lg:items-start">
    {{-- The frame is the main thing, and it stands where the page does. --}}
    {{-- **The preview follows.** The column beside it is long, and what is
         being changed has to stay in sight while it is changed. --}}
    <div class="min-w-0 flex-1 overflow-hidden rounded-xl border border-gray-200 bg-white lg:sticky lg:top-4 lg:self-start dark:border-gray-800 dark:bg-gray-900">
        <div class="flex flex-wrap items-center gap-3 border-b border-gray-100 px-3 py-2 dark:border-gray-800">
            {{-- Switched in the browser and remembered there, as the device
                 widths are: no reload, and it opens where it was left. --}}
            <div class="inline-flex gap-1 rounded-lg bg-gray-200/70 p-0.5 dark:bg-gray-800">
                <button type="button" class="bw-pill inline-flex cursor-pointer items-center gap-1.5 rounded-md border-0 bg-transparent px-3 py-1 text-[0.8125rem]/5 font-medium text-gray-500 transition hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100"
                        data-bw-pills="layout" data-bw-pill="preview"><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg>{{ __('Preview') }}</button>
                <button type="button" class="bw-pill inline-flex cursor-pointer items-center gap-1.5 rounded-md border-0 bg-transparent px-3 py-1 text-[0.8125rem]/5 font-medium text-gray-500 transition hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100"
                        data-bw-pills="layout" data-bw-pill="code"><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m16 18 6-6-6-6"/><path d="m8 6-6 6 6 6"/></svg>{{ __('Code') }}</button>
            </div>

            <span class="hidden text-[0.75rem] text-gray-500 sm:inline dark:text-gray-400">{{ __('before it is saved') }}</span>

            <span class="flex-1"></span>

            <span data-bw-pills="layout" data-bw-panel="preview">
                @include('bladewright::admin.scheme-pills')
            </span>

            <div class="inline-flex gap-0.5 rounded-lg bg-gray-200/70 p-0.5 dark:bg-gray-800"
                 data-bw-pills="layout" data-bw-panel="preview">
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
                  data-bw-pills="layout" data-bw-panel="preview"></span>

            {{-- **Saving stands where the work is looked at.** --}}
            @can(\Bladewright\Access\Abilities::gate(\Bladewright\Access\Abilities::EDIT_CONTENT))
                <button type="button" wire:click="save"
                        class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg bg-linear-to-tl from-bw-accent-2 to-bw-accent px-3 py-1.5 text-[0.8125rem] font-semibold text-white shadow-xs transition hover:brightness-110">
                    {{ __('Save') }}
                </button>
            @endcan
        </div>

        @php($error = $this->frameError())

        <div class="bw-preview-stage bg-gray-100 dark:bg-gray-950"
             data-bw-pills="layout" data-bw-panel="preview">
            @if ($error !== null)
                {{-- **Nothing is pretended.** A frame that cannot run shows
                     what stopped it, on the pill where it can be fixed. --}}
                <div class="flex h-[60vh] items-center justify-center p-6">
                    <div class="max-w-lg rounded-lg border border-red-200 bg-white px-4 py-3 font-mono text-[0.8125rem] text-red-900 dark:border-red-900 dark:bg-gray-900 dark:text-red-100">
                        {{ $error }}
                    </div>
                </div>
            @else
                <iframe class="block h-[60vh] w-full border-0 bg-white"
                        title="{{ __('Preview') }}"
                        srcdoc="{{ $this->preview() }}"></iframe>
            @endif
        </div>

        {{-- **The frame itself.** Blade of the site's own, run when a page is
             asked for. The server's own copy rides along so the editor can
             catch up without the page being reloaded. --}}
        <div class="p-4" data-bw-pills="layout" data-bw-panel="code" hidden
             data-bw-code-doc="{{ $content }}">
            @if ($error !== null)
                <div class="mb-2 rounded-lg border border-red-200 bg-red-50 px-3 py-2 font-mono text-[0.8125rem] text-red-900 dark:border-red-900 dark:bg-red-950 dark:text-red-100">
                    {{ $error }}
                </div>
            @endif

            <div wire:ignore>
                <textarea rows="24" data-bw-code="html" spellcheck="false"
                          class="w-full resize-y rounded-lg border border-gray-200 bg-gray-100 p-4 font-mono text-[0.8125rem]/6 focus:outline-2 focus:outline-offset-1 focus:outline-bw-accent dark:border-gray-700 dark:bg-gray-800"
                          wire:model.live.debounce.500ms="content">{{ $content }}</textarea>
            </div>
        </div>
    </div>

    <div class="w-full shrink-0 space-y-4 lg:w-[26rem]">
        {{-- Written in pieces so Blade reads it as words rather than as a
             slot of this very screen's own. --}}
        @php($slotWord = '{'.'{ $slot }'.'}')
        {{-- Likewise: written whole, Blade would run it here. --}}
        @php($placeWord = '@'.'bwplace')

        {{-- **The three bands.** A header and a footer are components placed
             here; the middle band is the page's own and nobody may stand in
             it. --}}
        <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
            <h2 class="m-0 text-base font-semibold">{{ __('Structure') }}</h2>
            <p class="mt-1 text-[0.8125rem] text-gray-500 dark:text-gray-400">
                {{ __('The header and the footer are components — every page in this frame gets them. The middle is the page itself.') }}
            </p>

            {{-- **The same tree as the other screens.** A band is a row, what
                 is inside it is read below it, and the page's own band says
                 only that. --}}
            <ul class="m-0 mt-4 list-none space-y-1.5 p-0">
                @foreach ($this->rows() as $row)
                    @include('bladewright::admin.arrange-node', ['node' => $row, 'depth' => 0])

                    {{-- **An empty band offers the picker; a filled one offers
                         the ×.** Changing what stands here is taking out and
                         putting in — the grammar of every other screen. Each
                         band offers only its own kind. --}}
                    @if ($row['band'] !== 'main' && $row['worn'] === null)
                        @can(\Bladewright\Access\Abilities::gate(\Bladewright\Access\Abilities::EDIT_CONTENT))
                            @php($choices = $this->choices($row['band']))
                            @if ($choices !== [])
                                <li class="!mt-1 flex items-center gap-2 pl-3">
                                    <select class="min-w-0 flex-1 cursor-pointer rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-[0.8125rem] shadow-xs transition focus:border-bw-accent focus:ring-2 focus:ring-bw-accent/20 focus:outline-none dark:border-gray-700 dark:bg-gray-950"
                                            wire:model="bands.{{ $row['band'] }}">
                                        <option value="">{{ __('Choose a component…') }}</option>
                                        @foreach ($choices as $name)
                                            <option value="{{ $name }}">{{ $name }}</option>
                                        @endforeach
                                    </select>
                                    <button type="button" wire:click="wear('{{ $row['band'] }}')"
                                            class="inline-flex shrink-0 cursor-pointer items-center gap-1.5 rounded-lg bg-linear-to-tl from-bw-accent-2 to-bw-accent px-3 py-1.5 text-[0.8125rem] font-semibold text-white shadow-xs transition hover:brightness-110">
                                        {{ __('Put it in') }}
                                    </button>
                                </li>
                            @else
                                <li class="!mt-1 pl-3 text-[0.8125rem] text-gray-500 dark:text-gray-400">
                                    {{ __('No :type component on the shelf yet — make one under Components.', ['type' => $row['band']]) }}
                                </li>
                            @endif
                        @endcan
                    @endif
                @endforeach
            </ul>
        </div>

        {{-- **The typeface is the frame's word** — top-down, like the
             framework: block by block would be misery. Every page wearing
             the frame reads in it; empty leaves the framework's own. --}}
        @can(\Bladewright\Access\Abilities::gate(\Bladewright\Access\Abilities::EDIT_CONTENT))
        <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
            <h2 class="m-0 text-base font-semibold">{{ __('Typeface') }}</h2>

            <p class="m-0 mt-3 text-[0.8125rem] text-gray-500 dark:text-gray-400">
                {{ __('Every page in this frame reads in it. A stack, ending in a generic; empty keeps what the framework brings.') }}
            </p>

            <div class="mt-4 flex items-start gap-3">
                <label class="w-24 shrink-0 pt-2 font-mono text-[0.75rem] text-gray-600 dark:text-gray-400">font-family</label>
                <input type="text" placeholder="Noto Sans JP, sans-serif"
                       class="min-w-0 flex-1 rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 font-mono text-[0.75rem] shadow-xs transition focus:border-bw-accent focus:ring-2 focus:ring-bw-accent/20 focus:outline-none dark:border-gray-700 dark:bg-gray-950"
                       wire:model.live.debounce.700ms="fontFamily">
            </div>
        </div>
        @endcan

        <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
            <h2 class="m-0 text-base font-semibold">{{ __('This frame') }}</h2>

            <p class="m-0 mt-3 text-[0.8125rem] text-gray-500 dark:text-gray-400">
                {{ __('A whole document, in the site\'s own Blade. Where :slot stands, the page goes.', ['slot' => $slotWord]) }}
            </p>

            @unless ($this->holdsThePage())
                {{-- **Said, not refused.** Somebody may be halfway through. --}}
                <div class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-[0.8125rem] text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-100">
                    {{ __('There is no :slot in here, so a page worn in this frame would show nothing of its own.', ['slot' => $slotWord]) }}
                </div>
            @endunless

            <div class="mt-4 flex flex-col gap-1.5 border-t border-gray-100 py-3 sm:flex-row sm:items-start sm:gap-4 dark:border-gray-800">
                <span class="w-32 shrink-0 text-[0.8125rem] font-medium text-gray-600 dark:text-gray-400">{{ __('Born from') }}</span>
                <div class="flex-1 text-[0.8125rem] text-gray-500 dark:text-gray-400">
                    {{ $layout->type === 'sidebar' ? __('navigation down the side') : __('navigation across the top') }}
                </div>
            </div>

            <div class="flex flex-col gap-1.5 border-t border-gray-100 py-3 sm:flex-row sm:items-start sm:gap-4 dark:border-gray-800">
                <span class="w-32 shrink-0 text-[0.8125rem] font-medium text-gray-600 dark:text-gray-400">{{ __('Worn by') }}</span>
                <div class="flex-1 text-[0.8125rem] text-gray-500 dark:text-gray-400">
                    @php($pages = $this->pagesWearing())
                    {{ $pages === 0 ? __('No page wears it yet.') : __(':n page(s) — editing it reaches all of them.', ['n' => $pages]) }}
                </div>
            </div>
        </div>
    </div>
</div>
