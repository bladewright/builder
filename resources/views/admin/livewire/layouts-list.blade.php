<?php

use Livewire\Component;
use Bladewright\Access\Abilities;
use Bladewright\Blocks\LayoutManager;
use Bladewright\Models\Layout;
use Bladewright\Models\Page;
use Bladewright\Support\Toasts;

/*
 * The layouts, listed. **The row is the layout** — press anywhere on it and
 * the frame opens for editing.
 */
new class extends Component
{
    use Toasts;

    public string $search = '';

    public bool $creating = false;

    public string $newName = '';

    public string $newType = 'header';

    public function startCreating(): void
    {
        $this->authorize(Abilities::gate(Abilities::EDIT_CONTENT));

        $this->creating = true;
    }

    public function cancelCreating(): void
    {
        $this->creating = false;
        $this->newName = '';
        $this->newType = 'header';
    }

    public function create(): void
    {
        $this->authorize(Abilities::gate(Abilities::EDIT_CONTENT));

        try {
            $layout = app(LayoutManager::class)->create($this->newName, $this->newType);
        } catch (\InvalidArgumentException $e) {
            $this->addError('newName', $e->getMessage());

            return;
        }

        $this->redirect(route('bladewright.admin.layouts.edit', $layout), navigate: false);
    }

    public function layouts()
    {
        return Layout::query()
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
            ->orderByDesc('updated_at')
            ->get();
    }

    /** How many pages wear each frame, in one query. @return array<string, int> */
    public function wornBy(): array
    {
        return Page::query()
            ->selectRaw('layout_uuid, count(*) as pages')
            ->whereNotNull('layout_uuid')
            ->groupBy('layout_uuid')
            ->pluck('pages', 'layout_uuid')
            ->all();
    }
};
?>

<div class="mt-4 rounded-xl border border-gray-200 bg-white p-6 first:mt-0 dark:border-gray-800 dark:bg-gray-900">
    <div class="flex flex-wrap items-center gap-2">
        <input
            type="search"
            class="min-w-56 flex-1 rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-[0.8125rem] shadow-xs transition focus:border-bw-accent focus:ring-2 focus:ring-bw-accent/20 focus:outline-none dark:border-gray-700 dark:bg-gray-950"
            wire:model.live.debounce.300ms="search"
            placeholder="{{ __('Filter by name') }}"
        >

        <span class="flex-1"></span>

        @can(\Bladewright\Access\Abilities::gate(\Bladewright\Access\Abilities::EDIT_CONTENT))
            <button type="button" class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg bg-linear-to-tl from-bw-accent-2 to-bw-accent px-3 py-1.5 text-[0.8125rem] font-semibold text-white shadow-xs transition hover:brightness-110 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-bw-accent" wire:click="startCreating">
                {{ __('Create a layout') }}
            </button>
        @endcan
    </div>

    @if ($creating)
        <div data-bw-modal="new-layout" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/50" data-bw-modal-close wire:click="cancelCreating"></div>

            <div class="relative z-10 w-full max-w-md rounded-xl border border-gray-200 bg-white p-5 shadow-xl dark:border-gray-800 dark:bg-gray-900">
                <h2 class="m-0 text-sm font-semibold">{{ __('Create a layout') }}</h2>

                <label class="mt-4 block text-[0.8125rem] font-medium text-gray-600 dark:text-gray-400">{{ __('Name') }}</label>
                <input type="text" autofocus
                       class="mt-1.5 w-full rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-[0.8125rem] shadow-xs transition focus:border-bw-accent focus:ring-2 focus:ring-bw-accent/20 focus:outline-none dark:border-gray-700 dark:bg-gray-950"
                       wire:model="newName" wire:keydown.enter="create" placeholder="{{ __('site') }}">
                @error('newName') <div class="mt-1 text-[0.8125rem] text-red-600 dark:text-red-400">{{ $message }}</div> @enderror

                {{-- **The recipe's whole job ends at birth**, and the CSS is
                     not part of it any more: the frame is born speaking the
                     site's framework, declared at install. --}}
                <label class="mt-4 block text-[0.8125rem] font-medium text-gray-600 dark:text-gray-400">{{ __('Where the navigation stands') }}</label>
                <select class="mt-1.5 w-full cursor-pointer rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-[0.8125rem] shadow-xs transition focus:border-bw-accent focus:ring-2 focus:ring-bw-accent/20 focus:outline-none dark:border-gray-700 dark:bg-gray-950"
                        wire:model="newType">
                    <option value="header">{{ __('Across the top') }}</option>
                    <option value="sidebar">{{ __('Down the side') }}</option>
                </select>

                <div class="mt-5 flex items-center justify-end gap-2">
                    <button type="button" data-bw-modal-close wire:click="cancelCreating"
                            class="inline-flex cursor-pointer items-center gap-1 rounded-md border border-gray-300 bg-white px-2.5 py-1.5 text-[0.8125rem] font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">{{ __('Cancel') }}</button>
                    <button type="button" wire:click="create"
                            class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg bg-linear-to-tl from-bw-accent-2 to-bw-accent px-3 py-1.5 text-[0.8125rem] font-semibold text-white shadow-xs transition hover:brightness-110">{{ __('Create') }}</button>
                </div>
            </div>
        </div>
    @endif

    @php($layouts = $this->layouts())

    @if ($layouts->isEmpty())
        <p class="px-4 py-12 text-center text-gray-500 dark:text-gray-400">
            {{ $search === '' ? __('No layouts yet.') : __('Nothing matches.') }}
        </p>
    @else
        @php($wornBy = $this->wornBy())

        <div class="-mx-6 mt-4 overflow-x-auto">
        <table class="w-full border-collapse text-sm">
            <thead>
                <tr>
                    <th class="border-b border-gray-200 px-6 pb-2 text-left text-xs font-medium text-gray-500 dark:border-gray-800 dark:text-gray-400">{{ __('Layouts') }}</th>
                    <th class="border-b border-gray-200 px-6 pb-2 text-left text-xs font-medium text-gray-500 dark:border-gray-800 dark:text-gray-400">{{ __('Navigation') }}</th>
                    <th class="border-b border-gray-200 px-6 pb-2 text-left text-xs font-medium text-gray-500 dark:border-gray-800 dark:text-gray-400">{{ __('Worn by') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($layouts as $layout)
                    <tr wire:key="layout-{{ $layout->uuid }}" data-bw-row tabindex="0"
                        data-bw-row-href="{{ route('bladewright.admin.layouts.edit', $layout) }}"
                        class="cursor-pointer transition hover:bg-gray-50 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-bw-accent dark:hover:bg-gray-800/50">
                        <td class="border-b border-gray-100 px-6 py-3 align-middle font-semibold dark:border-gray-800">{{ $layout->name }}</td>
                        <td class="border-b border-gray-100 px-6 py-3 align-middle dark:border-gray-800">
                            <span class="inline-block rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                                {{ $layout->type === 'sidebar' ? __('Down the side') : __('Across the top') }}
                            </span>
                        </td>
                        <td class="border-b border-gray-100 px-6 py-3 align-middle text-[0.8125rem] text-gray-500 dark:border-gray-800 dark:text-gray-400">
                            {{ ($n = $wornBy[$layout->uuid] ?? 0) === 0 ? __('No page yet') : __(':n page(s)', ['n' => $n]) }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    @endif
</div>
