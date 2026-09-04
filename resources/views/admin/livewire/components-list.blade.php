<?php

use Livewire\Component;
use Bladewright\Access\Abilities;
use Bladewright\Blocks\StructureManager;
use Bladewright\Models\PageChild;
use Bladewright\Models\Structure;
use Bladewright\Support\Toasts;

/*
 * The components, listed. **The row is the component** — press anywhere on
 * it and the editor opens.
 */
new class extends Component
{
    use Toasts;

    public string $search = '';

    public bool $creating = false;

    public string $newName = '';

    public string $newType = 'section';

    public string $newLayout = 'stack';

    public function startCreating(): void
    {
        $this->authorize(Abilities::gate(Abilities::EDIT_CONTENT));

        $this->creating = true;
    }

    public function cancelCreating(): void
    {
        $this->creating = false;
        $this->newName = '';
        $this->newType = 'section';
        $this->newLayout = 'stack';
    }

    public function create(): void
    {
        $this->authorize(Abilities::gate(Abilities::EDIT_CONTENT));

        try {
            $component = app(StructureManager::class)->create($this->newName, $this->newType, $this->newLayout);
        } catch (\InvalidArgumentException $e) {
            $this->addError('newName', $e->getMessage());

            return;
        }

        $this->redirect(route('bladewright.admin.components.edit', $component), navigate: false);
    }

    public function components()
    {
        return Structure::query()
            ->withCount('children')
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
            ->orderByDesc('updated_at')
            ->get();
    }

    /** How many pages show each component, in one query. @return array<string, int> */
    public function shownOn(): array
    {
        return PageChild::query()
            ->selectRaw('child_uuid, count(*) as places')
            ->groupBy('child_uuid')
            ->pluck('places', 'child_uuid')
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
                {{ __('Create a component') }}
            </button>
        @endcan
    </div>

    @if ($creating)
        <div data-bw-modal="new-component" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/50" data-bw-modal-close wire:click="cancelCreating"></div>

            <div class="relative z-10 w-full max-w-md rounded-xl border border-gray-200 bg-white p-5 shadow-xl dark:border-gray-800 dark:bg-gray-900">
                <h2 class="m-0 text-sm font-semibold">{{ __('Create a component') }}</h2>

                <label class="mt-4 block text-[0.8125rem] font-medium text-gray-600 dark:text-gray-400">{{ __('Name') }}</label>
                <input type="text" autofocus
                       class="mt-1.5 w-full rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-[0.8125rem] shadow-xs transition focus:border-bw-accent focus:ring-2 focus:ring-bw-accent/20 focus:outline-none dark:border-gray-700 dark:bg-gray-950"
                       wire:model="newName" wire:keydown.enter="create" placeholder="{{ __('hero') }}">
                @error('newName') <div class="mt-1 text-[0.8125rem] text-red-600 dark:text-red-400">{{ $message }}</div> @enderror

                <label class="mt-4 block text-[0.8125rem] font-medium text-gray-600 dark:text-gray-400">{{ __('What kind') }}</label>
                <select class="mt-1.5 w-full rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-[0.8125rem] shadow-xs transition focus:border-bw-accent focus:ring-2 focus:ring-bw-accent/20 focus:outline-none dark:border-gray-700 dark:bg-gray-950"
                        wire:model="newType">
                    @foreach (\Bladewright\Models\Structure::TYPES as $type)
                        <option value="{{ $type }}">{{ $type }}</option>
                    @endforeach
                </select>

                <label class="mt-4 block text-[0.8125rem] font-medium text-gray-600 dark:text-gray-400">{{ __('How the contents stand') }}</label>
                <select class="mt-1.5 w-full rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-[0.8125rem] shadow-xs transition focus:border-bw-accent focus:ring-2 focus:ring-bw-accent/20 focus:outline-none dark:border-gray-700 dark:bg-gray-950"
                        wire:model="newLayout">
                    <option value="stack">{{ __('Stacked — one under the other') }}</option>
                    <option value="grid">{{ __('Grid — side by side in columns') }}</option>
                    <option value="row">{{ __('Row — lined up in a line') }}</option>
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

    @php($components = $this->components())

    @if ($components->isEmpty())
        <p class="px-4 py-12 text-center text-gray-500 dark:text-gray-400">
            {{ $search === '' ? __('No components yet.') : __('Nothing matches.') }}
        </p>
    @else
        @php($shownOn = $this->shownOn())

        <div class="-mx-6 mt-4 overflow-x-auto">
        <table class="w-full border-collapse text-sm">
            <thead>
                <tr>
                    <th class="border-b border-gray-200 px-6 pb-2 text-left text-xs font-medium text-gray-500 dark:border-gray-800 dark:text-gray-400">{{ __('Components') }}</th>
                    <th class="border-b border-gray-200 px-6 pb-2 text-left text-xs font-medium text-gray-500 dark:border-gray-800 dark:text-gray-400">{{ __('Kind') }}</th>
                    <th class="border-b border-gray-200 px-6 pb-2 text-left text-xs font-medium text-gray-500 dark:border-gray-800 dark:text-gray-400">{{ __('Holds') }}</th>
                    <th class="border-b border-gray-200 px-6 pb-2 text-left text-xs font-medium text-gray-500 dark:border-gray-800 dark:text-gray-400">{{ __('Shown on') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($components as $component)
                    <tr wire:key="component-{{ $component->uuid }}" data-bw-row tabindex="0"
                        data-bw-row-href="{{ route('bladewright.admin.components.edit', $component) }}"
                        class="cursor-pointer transition hover:bg-gray-50 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-bw-accent dark:hover:bg-gray-800/50">
                        <td class="border-b border-gray-100 px-6 py-3 align-middle font-semibold dark:border-gray-800">{{ $component->name }}</td>
                        <td class="border-b border-gray-100 px-6 py-3 align-middle dark:border-gray-800">
                            <span class="inline-block rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-600 dark:bg-gray-800 dark:text-gray-300">{{ $component->type }}</span>
                            @if ($component->layout !== 'stack')
                                <span class="ml-1 inline-block rounded-full bg-bw-accent/10 px-2 py-0.5 text-xs font-semibold text-bw-accent">{{ $component->layout === 'grid' ? __('grid') : __('row') }}</span>
                            @endif
                        </td>
                        <td class="border-b border-gray-100 px-6 py-3 align-middle text-[0.8125rem] text-gray-500 dark:border-gray-800 dark:text-gray-400">
                            {{ $component->children_count === 0 ? __('Empty') : __(':n thing(s)', ['n' => $component->children_count]) }}
                        </td>
                        <td class="border-b border-gray-100 px-6 py-3 align-middle text-[0.8125rem] text-gray-500 dark:border-gray-800 dark:text-gray-400">
                            {{ ($n = $shownOn[$component->uuid] ?? 0) === 0 ? __('No page yet') : __(':n page(s)', ['n' => $n]) }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    @endif
</div>
