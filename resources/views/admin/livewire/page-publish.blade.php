<?php

use Livewire\Component;
use Bladewright\Access\Abilities;
use Bladewright\Blocks\SitePages;
use Bladewright\Models\Page;
use Bladewright\Support\Toasts;

/*
 * Publishing a page. **Its own window, and the only place it happens.**
 */
new class extends Component
{
    use Toasts;

    public Page $page;

    public string $from = '';

    public string $until = '';

    public function mount(): void
    {
        $this->from = $this->page->published_from?->format('Y-m-d\TH:i') ?? '';
        $this->until = $this->page->published_until?->format('Y-m-d\TH:i') ?? '';
    }

    public function publish(): void
    {
        $this->authorize(Abilities::gate(Abilities::PUBLISH));

        try {
            app(SitePages::class)->publish($this->page, $this->from ?: null, $this->until ?: null);
        } catch (\InvalidArgumentException $e) {
            $this->toastError($e->getMessage());

            return;
        }

        $this->page->refresh();
        $this->toast(__('It is on the site.'));
    }

    public function unpublish(): void
    {
        $this->authorize(Abilities::gate(Abilities::PUBLISH));

        app(SitePages::class)->unpublish($this->page);

        $this->page->refresh();
        $this->toast(__('Unpublished. The URL stays reserved.'));
    }
};
?>

<div>
    <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h2 class="m-0 text-base font-semibold">{{ __('Publishing') }}</h2>

            <div class="flex flex-wrap items-center gap-2">
                <button type="button" data-bw-modal-close
                        class="order-last cursor-pointer rounded-md p-1 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800 dark:hover:text-gray-200"
                        aria-label="{{ __('Close') }}">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/>
                    </svg>
                </button>

                {{-- **State is said as a result.** --}}
                @if ($page->is_published)
                    <span class="inline-block rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold whitespace-nowrap text-green-700 dark:bg-green-900/40 dark:text-green-300">{{ __('On the site') }}</span>
                @else
                    <span class="inline-block rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold whitespace-nowrap text-gray-600 dark:bg-gray-800 dark:text-gray-300">{{ __('Not on the site') }}</span>
                @endif
            </div>
        </div>

        <div class="flex flex-col gap-1.5 border-t border-gray-100 py-3 first-of-type:border-t-0 sm:flex-row sm:items-start sm:gap-4 dark:border-gray-800">
            <span class="w-40 shrink-0 pt-2 text-[0.8125rem] font-medium text-gray-600 dark:text-gray-400">{{ __('The URL') }}</span>
            <div class="flex-1 pt-2 font-mono text-[0.8125rem] text-gray-500 dark:text-gray-400">/{{ $page->url }}</div>
        </div>

        {{-- **When**, for what does not go live the moment it is finished. --}}
        <div class="flex flex-col gap-1.5 border-t border-gray-100 py-3 sm:flex-row sm:items-start sm:gap-4 dark:border-gray-800">
            <span class="w-40 shrink-0 pt-2 text-[0.8125rem] font-medium text-gray-600 dark:text-gray-400">{{ __('From, and until') }}</span>
            <div class="flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <input type="datetime-local" class="w-full max-w-xs rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-[0.8125rem] shadow-xs transition focus:border-bw-accent focus:ring-2 focus:ring-bw-accent/20 focus:outline-none dark:border-gray-700 dark:bg-gray-950" wire:model="from">
                    <span class="text-[0.8125rem] text-gray-500 dark:text-gray-400">〜</span>
                    <input type="datetime-local" class="w-full max-w-xs rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-[0.8125rem] shadow-xs transition focus:border-bw-accent focus:ring-2 focus:ring-bw-accent/20 focus:outline-none dark:border-gray-700 dark:bg-gray-950" wire:model="until">
                </div>
                <div class="mt-1 text-[0.8125rem] text-gray-500 dark:text-gray-400">
                    {{ __('Leave them empty for at once, with no end. They take hold when you publish.') }}
                </div>
            </div>
        </div>

        @can(\Bladewright\Access\Abilities::gate(\Bladewright\Access\Abilities::PUBLISH))
            <div class="flex items-center justify-end gap-2 border-t border-gray-100 pt-4 dark:border-gray-800">
                @if ($page->is_published)
                    <button type="button" wire:click="unpublish"
                            class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-[0.8125rem] font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">
                        {{ __('Unpublish') }}
                    </button>
                @endif
                <button type="button" wire:click="publish"
                        class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg bg-linear-to-tl from-bw-accent-2 to-bw-accent px-3 py-1.5 text-[0.8125rem] font-semibold text-white shadow-xs transition hover:brightness-110">
                    {{ $page->is_published ? __('Publish these times') : __('Publish') }}
                </button>
            </div>
        @endcan
    </div>
</div>
