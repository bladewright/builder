<?php

use Livewire\Component;
use Bladewright\Access\Abilities;
use Bladewright\Blocks\LayoutManager;
use Bladewright\Blocks\SitePages;
use Bladewright\Models\Layout;
use Bladewright\Support\Toasts;

/*
 * A layout's settings: its name, its reach, and what cannot be undone.
 */
new class extends Component
{
    use Toasts;

    public Layout $layout;

    public string $name = '';

    public function mount(): void
    {
        $this->name = $this->layout->name;
    }

    public function saveName(): void
    {
        $this->authorize(Abilities::gate(Abilities::EDIT_CONTENT));

        try {
            app(LayoutManager::class)->rename($this->layout, $this->name);
        } catch (\InvalidArgumentException $e) {
            $this->toastError($e->getMessage());

            return;
        }

        $this->toast(__('The name has changed.'));
    }

    public function pagesWearing(): int
    {
        return app(SitePages::class)->pagesWearing($this->layout);
    }

    /** **The pages stay.** What goes is the frame around them. */
    public function destroy(): void
    {
        $this->authorize(Abilities::gate(Abilities::EDIT_CONTENT));

        app(SitePages::class)->forgetLayout($this->layout);
        app(LayoutManager::class)->delete($this->layout);

        $this->redirect(route('bladewright.admin.layouts'), navigate: false);
    }
};
?>

<div>
    <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
        <h2 class="m-0 text-base font-semibold">{{ __('Layout settings') }}</h2>

        <div class="flex flex-col gap-1.5 border-t border-gray-100 py-3 first-of-type:border-t-0 sm:flex-row sm:items-start sm:gap-4 dark:border-gray-800">
            <label class="w-40 shrink-0 pt-2 text-[0.8125rem] font-medium text-gray-600 dark:text-gray-400">{{ __('Name') }}</label>
            <div class="flex flex-1 flex-wrap items-start gap-2">
                <input type="text" class="min-w-0 flex-1 rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-[0.8125rem] shadow-xs transition focus:border-bw-accent focus:ring-2 focus:ring-bw-accent/20 focus:outline-none dark:border-gray-700 dark:bg-gray-950"
                       wire:model="name" wire:keydown.enter="saveName">
                <button type="button" wire:click="saveName"
                        class="inline-flex cursor-pointer items-center gap-1 rounded-md border border-gray-300 bg-white px-2.5 py-1.5 text-[0.8125rem] font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">{{ __('Change') }}</button>
                <div class="w-full text-[0.8125rem] text-gray-500 dark:text-gray-400">
                    {{ __('Every page wearing this frame follows the new name along.') }}
                </div>
            </div>
        </div>

        <div class="flex flex-col gap-1.5 border-t border-gray-100 py-3 sm:flex-row sm:items-start sm:gap-4 dark:border-gray-800">
            <span class="w-40 shrink-0 pt-2 text-[0.8125rem] font-medium text-gray-600 dark:text-gray-400">{{ __('Born from') }}</span>
            <div class="flex-1 pt-2 text-[0.8125rem] text-gray-500 dark:text-gray-400">
                {{ $layout->type === 'sidebar' ? __('navigation down the side') : __('navigation across the top') }}
                — {{ __('the recipe\'s job ended at birth; the frame is the site\'s own now.') }}
            </div>
        </div>

        <div class="flex flex-col gap-1.5 border-t border-gray-100 py-3 sm:flex-row sm:items-start sm:gap-4 dark:border-gray-800">
            <span class="w-40 shrink-0 pt-2 text-[0.8125rem] font-medium text-gray-600 dark:text-gray-400">{{ __('Worn by') }}</span>
            <div class="flex-1 pt-2 text-[0.8125rem] text-gray-500 dark:text-gray-400">
                @php($pages = $this->pagesWearing())
                {{ $pages === 0 ? __('No page wears it yet.') : __(':n page(s) — editing it reaches all of them.', ['n' => $pages]) }}
            </div>
        </div>
    </div>

    @can(\Bladewright\Access\Abilities::gate(\Bladewright\Access\Abilities::EDIT_CONTENT))
        <div class="mt-4 overflow-hidden rounded-xl border border-red-200 bg-white dark:border-red-900/60 dark:bg-gray-900">
            <h2 class="m-0 border-b border-red-200 bg-red-50/60 px-6 py-3 text-base font-semibold text-red-700 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-300">
                {{ __('Danger zone') }}
            </h2>

            <div class="flex flex-col gap-3 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <div class="text-sm font-semibold">{{ __('Delete this layout') }}</div>
                    <p class="m-0 mt-1 text-[0.8125rem] text-gray-500 dark:text-gray-400">
                        @php($pages = $this->pagesWearing())
                        {{ $pages === 0
                            ? __('The frame goes, and it cannot be undone.')
                            : __('Worn by :n page(s). They stay, and stand bare until another frame is chosen. It cannot be undone.', ['n' => $pages]) }}
                    </p>
                </div>

                <button type="button" wire:click="destroy"
                        wire:confirm="{{ __('Delete this layout? It cannot be undone.') }}"
                        class="shrink-0 cursor-pointer rounded-md border border-red-300 bg-white px-3 py-1.5 text-[0.8125rem] font-semibold text-red-700 transition hover:bg-red-50 dark:border-red-900 dark:bg-gray-900 dark:text-red-300 dark:hover:bg-red-950">
                    {{ __('Delete this layout') }}
                </button>
            </div>
        </div>
    @endcan
</div>
