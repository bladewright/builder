<?php

use Livewire\Component;
use Bladewright\Access\Abilities;
use Bladewright\Blocks\BlockManager;
use Bladewright\Blocks\StructureManager;
use Bladewright\Models\Block;
use Bladewright\Support\Toasts;

/*
 * A block's settings: its name, its reach, and what cannot be undone.
 *
 * **The dangerous things live in the settings, not under the editor** —
 * that is where anybody looks for them, and it keeps what is being written
 * apart from what would throw the writing away.
 */
new class extends Component
{
    use Toasts;

    public Block $block;

    public string $name = '';

    public function mount(): void
    {
        $this->name = $this->block->name;
    }

    /**
     * Change what it is called. **Everything that shows it holds the uuid**,
     * so the new word breaks nothing.
     */
    public function saveName(): void
    {
        $this->authorize(Abilities::gate(Abilities::EDIT_CONTENT));

        try {
            app(BlockManager::class)->rename($this->block, $this->name);
        } catch (\InvalidArgumentException $e) {
            $this->toastError($e->getMessage());

            return;
        }

        $this->toast(__('The name has changed.'));
    }

    public function placesShowing(): int
    {
        return app(StructureManager::class)->placesShowing($this->block);
    }

    /** Delete it for good. It disappears from every component that showed it. */
    public function destroy(): void
    {
        $this->authorize(Abilities::gate(Abilities::EDIT_CONTENT));

        app(StructureManager::class)->forgetBlock($this->block);
        app(BlockManager::class)->delete($this->block);

        $this->redirect(route('bladewright.admin.blocks'), navigate: false);
    }
};
?>

<div>
    <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
        <h2 class="m-0 text-base font-semibold">{{ __('Block settings') }}</h2>

        <div class="flex flex-col gap-1.5 border-t border-gray-100 py-3 first-of-type:border-t-0 sm:flex-row sm:items-start sm:gap-4 dark:border-gray-800">
            <label class="w-40 shrink-0 pt-2 text-[0.8125rem] font-medium text-gray-600 dark:text-gray-400">{{ __('Name') }}</label>
            <div class="flex flex-1 flex-wrap items-start gap-2">
                <input type="text" class="min-w-0 flex-1 rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-[0.8125rem] shadow-xs transition focus:border-bw-accent focus:ring-2 focus:ring-bw-accent/20 focus:outline-none dark:border-gray-700 dark:bg-gray-950"
                       wire:model="name" wire:keydown.enter="saveName">
                <button type="button" wire:click="saveName"
                        class="inline-flex cursor-pointer items-center gap-1 rounded-md border border-gray-300 bg-white px-2.5 py-1.5 text-[0.8125rem] font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">{{ __('Change') }}</button>
                <div class="w-full text-[0.8125rem] text-gray-500 dark:text-gray-400">
                    {{ __('Everything that shows this block follows the new name along.') }}
                </div>
            </div>
        </div>

        <div class="flex flex-col gap-1.5 border-t border-gray-100 py-3 sm:flex-row sm:items-start sm:gap-4 dark:border-gray-800">
            <span class="w-40 shrink-0 pt-2 text-[0.8125rem] font-medium text-gray-600 dark:text-gray-400">{{ __('Kind') }}</span>
            <div class="flex-1 pt-2 text-[0.8125rem] text-gray-500 dark:text-gray-400">{{ $block->type }}</div>
        </div>

        <div class="flex flex-col gap-1.5 border-t border-gray-100 py-3 sm:flex-row sm:items-start sm:gap-4 dark:border-gray-800">
            <span class="w-40 shrink-0 pt-2 text-[0.8125rem] font-medium text-gray-600 dark:text-gray-400">{{ __('Shown in') }}</span>
            <div class="flex-1 pt-2 text-[0.8125rem] text-gray-500 dark:text-gray-400">
                @php($places = $this->placesShowing())
                {{ $places === 0 ? __('No component shows it yet.') : __(':n component(s) — editing it reaches all of them.', ['n' => $places]) }}
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
                    <div class="text-sm font-semibold">{{ __('Delete this block') }}</div>
                    <p class="m-0 mt-1 text-[0.8125rem] text-gray-500 dark:text-gray-400">
                        @php($places = $this->placesShowing())
                        {{ $places === 0
                            ? __('Its content goes with it, and it cannot be undone.')
                            : __('Shown in :n component(s). It disappears from all of them, and it cannot be undone.', ['n' => $places]) }}
                    </p>
                </div>

                <button type="button" wire:click="destroy"
                        wire:confirm="{{ __('Delete this block? It cannot be undone.') }}"
                        class="shrink-0 cursor-pointer rounded-md border border-red-300 bg-white px-3 py-1.5 text-[0.8125rem] font-semibold text-red-700 transition hover:bg-red-50 dark:border-red-900 dark:bg-gray-900 dark:text-red-300 dark:hover:bg-red-950">
                    {{ __('Delete this block') }}
                </button>
            </div>
        </div>
    @endcan
</div>
