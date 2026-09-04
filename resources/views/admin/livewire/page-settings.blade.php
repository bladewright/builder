<?php

use Livewire\Component;
use Bladewright\Access\Abilities;
use Bladewright\Blocks\SitePages;
use Bladewright\Models\Layout;
use Bladewright\Models\Page;
use Bladewright\Support\Toasts;

/*
 * A page's settings: its name, its URL, its frame — and what cannot be
 * undone, framed apart at the bottom.
 */
new class extends Component
{
    use Toasts;

    public Page $page;

    public string $name = '';

    public string $url = '';

    public string $layout = '';

    /** What the page says about itself to the machines. @var array<string, mixed> */
    public array $seo = ['title' => '', 'description' => '', 'image' => '', 'noindex' => false];

    /** Is the og:image being chosen right now? */
    public bool $pickingImage = false;

    public function mount(): void
    {
        $this->name = $this->page->name;
        $this->url = $this->page->url;
        $this->layout = $this->page->layout_uuid === null
            ? ''
            : (string) Layout::query()->where('uuid', $this->page->layout_uuid)->value('name');

        $stored = (array) (($this->page->data ?? [])['seo'] ?? []);

        $this->seo = [
            'title' => (string) ($stored['title'] ?? ''),
            'description' => (string) ($stored['description'] ?? ''),
            'image' => (string) ($stored['image'] ?? ''),
            'noindex' => ! empty($stored['noindex']),
        ];
    }

    public function saveSeo(): void
    {
        $this->authorize(Abilities::gate(Abilities::EDIT_CONTENT));

        app(SitePages::class)->saveSeo($this->page, $this->seo);

        $this->toast(__('Saved. The head follows on the next request.'));
    }

    public function pickImage(): void
    {
        $this->authorize(Abilities::gate(Abilities::EDIT_CONTENT));

        $this->pickingImage = true;
    }

    #[\Livewire\Attributes\On('bw-media-selected')]
    public function imageChosen(string $path): void
    {
        if (! $this->pickingImage) {
            return;
        }

        $this->seo['image'] = $path;
        $this->pickingImage = false;
    }

    public function clearImage(): void
    {
        $this->seo['image'] = '';
    }

    public function saveName(): void
    {
        $this->authorize(Abilities::gate(Abilities::EDIT_CONTENT));

        try {
            app(SitePages::class)->rename($this->page, $this->name);
        } catch (\InvalidArgumentException $e) {
            $this->toastError($e->getMessage());

            return;
        }

        $this->toast(__('The name has changed. The URL did not move.'));
    }

    public function saveUrl(): void
    {
        $this->authorize(Abilities::gate(Abilities::PUBLISH));

        // **The URL says so itself**, under the box it is about.
        if ($problem = app(SitePages::class)->urlProblem($this->url, $this->page)) {
            $this->addError('url', $problem);

            return;
        }

        app(SitePages::class)->changeUrl($this->page, $this->url);

        $this->url = $this->page->url;
        $this->resetErrorBag('url');
        $this->toast(__('The URL has changed.'));
    }

    public function saveLayout(): void
    {
        $this->authorize(Abilities::gate(Abilities::PUBLISH));

        try {
            app(SitePages::class)->changeLayout($this->page, $this->layout ?: null);
        } catch (\InvalidArgumentException $e) {
            $this->toastError($e->getMessage());

            return;
        }

        $this->toast($this->layout === ''
            ? __('The frame is off. The page stands bare.')
            : __('The frame is :name now.', ['name' => $this->layout]));
    }

    public function destroy(): void
    {
        $this->authorize(Abilities::gate(Abilities::PUBLISH));

        app(SitePages::class)->delete($this->page);

        $this->redirect(route('bladewright.admin.pages'), navigate: false);
    }
};
?>

<div>
    <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
        <h2 class="m-0 text-base font-semibold">{{ __('Page settings') }}</h2>

        <div class="flex flex-col gap-1.5 border-t border-gray-100 py-3 first-of-type:border-t-0 sm:flex-row sm:items-start sm:gap-4 dark:border-gray-800">
            <label class="w-40 shrink-0 pt-2 text-[0.8125rem] font-medium text-gray-600 dark:text-gray-400">{{ __('Name') }}</label>
            <div class="flex flex-1 flex-wrap items-start gap-2">
                <input type="text" class="min-w-0 flex-1 rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-[0.8125rem] shadow-xs transition focus:border-bw-accent focus:ring-2 focus:ring-bw-accent/20 focus:outline-none dark:border-gray-700 dark:bg-gray-950"
                       wire:model="name" wire:keydown.enter="saveName">
                <button type="button" wire:click="saveName"
                        class="inline-flex cursor-pointer items-center gap-1 rounded-md border border-gray-300 bg-white px-2.5 py-1.5 text-[0.8125rem] font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">{{ __('Change') }}</button>
                <div class="w-full text-[0.8125rem] text-gray-500 dark:text-gray-400">
                    {{ __('What this page is called on these screens. The URL does not change with it.') }}
                </div>
            </div>
        </div>

        <div class="flex flex-col gap-1.5 border-t border-gray-100 py-3 sm:flex-row sm:items-start sm:gap-4 dark:border-gray-800">
            <label class="w-40 shrink-0 pt-2 text-[0.8125rem] font-medium text-gray-600 dark:text-gray-400">{{ __('URL') }}</label>
            <div class="flex-1">
                <div class="flex flex-wrap items-start gap-2">
                    <div class="flex min-w-0 flex-1 items-stretch rounded-lg border bg-white shadow-xs transition focus-within:ring-2 dark:bg-gray-950 {{ $errors->has('url')
                        ? 'border-red-400 focus-within:border-red-400 focus-within:ring-red-400/20 dark:border-red-800'
                        : 'border-gray-300 focus-within:border-bw-accent focus-within:ring-bw-accent/20 dark:border-gray-700' }}">
                        <span class="flex select-none items-center border-r border-gray-200 px-3 font-mono text-sm text-gray-500 dark:border-gray-800 dark:text-gray-400">/</span>
                        <input type="text"
                               class="w-full rounded-r-lg border-0 bg-transparent px-3 py-2 font-mono text-sm focus:outline-none"
                               wire:model="url" wire:keydown.enter="saveUrl" placeholder="about">
                    </div>
                    <button type="button" wire:click="saveUrl"
                            class="inline-flex cursor-pointer items-center gap-1 rounded-md border border-gray-300 bg-white px-2.5 py-1.5 text-[0.8125rem] font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">{{ __('Change') }}</button>
                </div>
                @error('url')
                    <div class="mt-1 text-[0.8125rem] text-red-600 dark:text-red-400">{{ $message }}</div>
                @else
                    {{-- **A URL can be a shape.** Said here because there is
                         nowhere else a person would find it out. --}}
                    <div class="mt-1 text-[0.8125rem] text-gray-500 dark:text-gray-400">
                        {{ __('Empty is the front page.') }}
                        {!! __('A piece in braces stands for anything — :example answers every article, and what stood there reaches the page as :name.', [
                            'example' => '<span class="font-mono">news/{slug}</span>',
                            'name' => '<span class="font-mono">$slug</span>',
                        ]) !!}
                    </div>
                @enderror
            </div>
        </div>

        <div class="flex flex-col gap-1.5 border-t border-gray-100 py-3 sm:flex-row sm:items-start sm:gap-4 dark:border-gray-800">
            <label class="w-40 shrink-0 pt-2 text-[0.8125rem] font-medium text-gray-600 dark:text-gray-400">{{ __('Layout') }}</label>
            <div class="flex-1">
                <div class="flex flex-wrap items-start gap-2">
                    <select class="min-w-0 flex-1 rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-[0.8125rem] shadow-xs transition focus:border-bw-accent focus:ring-2 focus:ring-bw-accent/20 focus:outline-none dark:border-gray-700 dark:bg-gray-950"
                            wire:model="layout">
                        <option value="">{{ __('(none — the page stands bare)') }}</option>
                        @foreach (\Bladewright\Models\Layout::query()->orderBy('name')->pluck('name') as $layoutName)
                            <option value="{{ $layoutName }}">{{ $layoutName }}</option>
                        @endforeach
                    </select>
                    <button type="button" wire:click="saveLayout"
                            class="inline-flex cursor-pointer items-center gap-1 rounded-md border border-gray-300 bg-white px-2.5 py-1.5 text-[0.8125rem] font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">{{ __('Change') }}</button>
                </div>
                <div class="mt-1 text-[0.8125rem] text-gray-500 dark:text-gray-400">
                    {{ __('The frame around this page. Editing the layout reaches every page that wears it.') }}
                </div>
            </div>
        </div>
    </div>

    {{-- **The words are the page's, the place is the frame's.** What is
         written here lands in the head through the frame's @bwmeta. --}}
    <div class="mt-4 rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
        <h2 class="m-0 text-base font-semibold">{{ __('SEO') }}</h2>
        <p class="mt-1 text-[0.8125rem] text-gray-500 dark:text-gray-400">
            {{ __('What this page says about itself to search engines and link previews.') }}
        </p>

        <div class="mt-3 flex flex-col gap-1.5 border-t border-gray-100 py-3 sm:flex-row sm:items-start sm:gap-4 dark:border-gray-800">
            <label class="w-40 shrink-0 pt-2 font-mono text-[0.75rem] text-gray-600 dark:text-gray-400">title</label>
            <div class="flex-1">
                <input type="text" placeholder="{{ $page->name }}"
                       class="w-full rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-[0.8125rem] shadow-xs transition focus:border-bw-accent focus:ring-2 focus:ring-bw-accent/20 focus:outline-none dark:border-gray-700 dark:bg-gray-950"
                       wire:model="seo.title">
                <div class="mt-1 text-[0.8125rem] text-gray-500 dark:text-gray-400">{{ __('Empty falls back to the page\'s name.') }}</div>
            </div>
        </div>

        <div class="flex flex-col gap-1.5 border-t border-gray-100 py-3 sm:flex-row sm:items-start sm:gap-4 dark:border-gray-800">
            <label class="w-40 shrink-0 pt-2 font-mono text-[0.75rem] text-gray-600 dark:text-gray-400">description</label>
            <div class="flex-1">
                <textarea rows="3"
                          class="w-full resize-y rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-[0.8125rem] shadow-xs transition focus:border-bw-accent focus:ring-2 focus:ring-bw-accent/20 focus:outline-none dark:border-gray-700 dark:bg-gray-950"
                          wire:model="seo.description"></textarea>
                <div class="mt-1 text-[0.8125rem] text-gray-500 dark:text-gray-400">{{ __('The line under the title in search results. Empty writes nothing.') }}</div>
            </div>
        </div>

        <div class="flex flex-col gap-1.5 border-t border-gray-100 py-3 sm:flex-row sm:items-start sm:gap-4 dark:border-gray-800">
            <label class="w-40 shrink-0 pt-2 font-mono text-[0.75rem] text-gray-600 dark:text-gray-400">og:image</label>
            <div class="flex-1">
                @php($chosen = trim((string) ($seo['image'] ?? '')))
                @if ($chosen !== '' && ($file = app(\Bladewright\Media\MediaLibrary::class)->find($chosen)))
                    <img src="{{ $file->url() }}" alt="" class="mb-2 block max-h-32 rounded-lg">
                @elseif ($chosen !== '')
                    <div class="mb-2 font-mono text-[0.8125rem] text-gray-500 dark:text-gray-400">{{ $chosen }}</div>
                @endif

                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" wire:click="pickImage"
                            class="inline-flex cursor-pointer items-center gap-1 rounded-md border border-gray-300 bg-white px-2.5 py-1.5 text-[0.8125rem] font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">
                        {{ $chosen === '' ? __('Choose from the media') : __('Choose another') }}
                    </button>
                    @if ($chosen !== '')
                        <button type="button" wire:click="clearImage"
                                class="inline-flex cursor-pointer items-center gap-1 rounded-md border border-gray-300 bg-white px-2.5 py-1.5 text-[0.8125rem] font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">{{ __('Remove') }}</button>
                    @endif
                </div>
                <div class="mt-1 text-[0.8125rem] text-gray-500 dark:text-gray-400">{{ __('The picture a shared link shows.') }}</div>
            </div>
        </div>

        <div class="flex flex-col gap-1.5 border-t border-gray-100 py-3 sm:flex-row sm:items-start sm:gap-4 dark:border-gray-800">
            <label class="w-40 shrink-0 pt-2 font-mono text-[0.75rem] text-gray-600 dark:text-gray-400">noindex</label>
            <div class="flex-1 pt-2">
                <label class="flex cursor-pointer items-center gap-2 text-[0.8125rem] text-gray-700 dark:text-gray-200">
                    <input type="checkbox" wire:model="seo.noindex" class="rounded border-gray-300 text-bw-accent focus:ring-bw-accent/30 dark:border-gray-600 dark:bg-gray-950">
                    {{ __('Ask the search engines to pass this page by.') }}
                </label>
            </div>
        </div>

        <div class="mt-2 flex justify-end">
            <button type="button" wire:click="saveSeo"
                    class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg bg-linear-to-tl from-bw-accent-2 to-bw-accent px-3 py-1.5 text-[0.8125rem] font-semibold text-white shadow-xs transition hover:brightness-110">
                {{ __('Save') }}
            </button>
        </div>

        @if ($pickingImage)
            <div data-bw-modal="pick-og-image" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/50" data-bw-modal-close wire:click="$set('pickingImage', false)"></div>

                <div class="relative z-10 flex max-h-[85vh] w-full max-w-3xl flex-col overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xl dark:border-gray-800 dark:bg-gray-900">
                    <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3 dark:border-gray-800">
                        <span class="text-sm font-semibold">{{ __('Choose a file') }}</span>
                        <button type="button" data-bw-modal-close wire:click="$set('pickingImage', false)"
                                class="cursor-pointer rounded-md p-1 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200" aria-label="{{ __('Cancel') }}">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/>
                            </svg>
                        </button>
                    </div>

                    <div class="overflow-y-auto p-5">
                        <livewire:bladewright::media-library :picking="true" accept="image" wire:key="og-picker" />
                    </div>
                </div>
            </div>
        @endif
    </div>

    @can(\Bladewright\Access\Abilities::gate(\Bladewright\Access\Abilities::PUBLISH))
        <div class="mt-4 overflow-hidden rounded-xl border border-red-200 bg-white dark:border-red-900/60 dark:bg-gray-900">
            <h2 class="m-0 border-b border-red-200 bg-red-50/60 px-6 py-3 text-base font-semibold text-red-700 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-300">
                {{ __('Danger zone') }}
            </h2>

            <div class="flex flex-col gap-3 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <div class="text-sm font-semibold">{{ __('Delete this page') }}</div>
                    <p class="m-0 mt-1 text-[0.8125rem] text-gray-500 dark:text-gray-400">
                        {{ __('The page and its settings go; the components it showed stay. It cannot be undone.') }}
                    </p>
                </div>

                <button type="button" wire:click="destroy"
                        wire:confirm="{{ __('Delete this page? It cannot be undone.') }}"
                        class="shrink-0 cursor-pointer rounded-md border border-red-300 bg-white px-3 py-1.5 text-[0.8125rem] font-semibold text-red-700 transition hover:bg-red-50 dark:border-red-900 dark:bg-gray-900 dark:text-red-300 dark:hover:bg-red-950">
                    {{ __('Delete this page') }}
                </button>
            </div>
        </div>
    @endcan
</div>
