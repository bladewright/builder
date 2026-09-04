<?php

use Livewire\Component;
use Bladewright\Access\Abilities;
use Bladewright\Blocks\BlockManager;
use Bladewright\Models\Block;
use Bladewright\Models\StructureChild;
use Bladewright\Support\Toasts;

/*
 * The blocks, listed. **The row is the block** — press anywhere on it and
 * the editor opens. Making one is a moment, not a place, so it happens in a
 * small window on top of the list.
 */
new class extends Component
{
    use Toasts;

    public string $search = '';

    public bool $creating = false;

    public string $newName = '';

    public string $newType = 'markdown';

    public function startCreating(): void
    {
        $this->authorize(Abilities::gate(Abilities::EDIT_CONTENT));

        $this->creating = true;
    }

    public function cancelCreating(): void
    {
        $this->creating = false;
        $this->newName = '';
        $this->newType = 'markdown';
    }

    public function create(): void
    {
        $this->authorize(Abilities::gate(Abilities::EDIT_CONTENT));

        try {
            $block = app(BlockManager::class)->create($this->newName, $this->newType);
        } catch (\InvalidArgumentException $e) {
            $this->addError('newName', $e->getMessage());

            return;
        }

        $this->redirect(route('bladewright.admin.blocks.edit', $block), navigate: false);
    }

    public function blocks()
    {
        return Block::query()
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
            // **Most recently edited, always.** What you were last working on
            // is what you are coming back to.
            ->orderByDesc('updated_at')
            ->get();
    }

    /** How many components show each block, in one query. @return array<string, int> */
    public function shownIn(): array
    {
        return StructureChild::query()
            ->where('child_kind', StructureChild::KIND_BLOCK)
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
                {{ __('Create a block') }}
            </button>
        @endcan
    </div>

    @if ($creating)
        {{-- **Making a block is a moment, not a place.** Two answers and it leaves. --}}
        <div data-bw-modal="new-block" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/50" data-bw-modal-close wire:click="cancelCreating"></div>

            <div class="relative z-10 w-full max-w-md rounded-xl border border-gray-200 bg-white p-5 shadow-xl dark:border-gray-800 dark:bg-gray-900">
                <h2 class="m-0 text-sm font-semibold">{{ __('Create a block') }}</h2>

                <label class="mt-4 block text-[0.8125rem] font-medium text-gray-600 dark:text-gray-400">{{ __('Name') }}</label>
                <input type="text" autofocus
                       class="mt-1.5 w-full rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-[0.8125rem] shadow-xs transition focus:border-bw-accent focus:ring-2 focus:ring-bw-accent/20 focus:outline-none dark:border-gray-700 dark:bg-gray-950"
                       wire:model="newName" wire:keydown.enter="create" placeholder="{{ __('intro') }}">
                @error('newName') <div class="mt-1 text-[0.8125rem] text-red-600 dark:text-red-400">{{ $message }}</div> @enderror

                <label class="mt-4 block text-[0.8125rem] font-medium text-gray-600 dark:text-gray-400">{{ __('What kind') }}</label>
                <select class="mt-1.5 w-full rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-[0.8125rem] shadow-xs transition focus:border-bw-accent focus:ring-2 focus:ring-bw-accent/20 focus:outline-none dark:border-gray-700 dark:bg-gray-950"
                        wire:model.live="newType">
                    @foreach (\Bladewright\Models\Block::TYPES as $type)
                        <option value="{{ $type }}">{{ $type }}</option>
                    @endforeach
                </select>

                <div class="mt-5 flex items-center justify-end gap-2">
                    <button type="button" data-bw-modal-close wire:click="cancelCreating"
                            class="inline-flex cursor-pointer items-center gap-1 rounded-md border border-gray-300 bg-white px-2.5 py-1.5 text-[0.8125rem] font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">{{ __('Cancel') }}</button>
                    <button type="button" wire:click="create"
                            class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg bg-linear-to-tl from-bw-accent-2 to-bw-accent px-3 py-1.5 text-[0.8125rem] font-semibold text-white shadow-xs transition hover:brightness-110 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-bw-accent">{{ __('Create') }}</button>
                </div>
            </div>
        </div>
    @endif

    @php($blocks = $this->blocks())

    @if ($blocks->isEmpty())
        <p class="px-4 py-12 text-center text-gray-500 dark:text-gray-400">
            {{ $search === '' ? __('No blocks yet.') : __('Nothing matches.') }}
        </p>
    @else
        @php($shownIn = $this->shownIn())

        {{-- **The row is what is pressed, so it reaches the edge.** --}}
        <div class="-mx-6 mt-4 overflow-x-auto">
        <table class="w-full border-collapse text-sm">
            <thead>
                <tr>
                    <th class="border-b border-gray-200 px-6 pb-2 text-left text-xs font-medium text-gray-500 dark:border-gray-800 dark:text-gray-400">{{ __('Blocks') }}</th>
                    <th class="border-b border-gray-200 px-6 pb-2 text-left text-xs font-medium text-gray-500 dark:border-gray-800 dark:text-gray-400">{{ __('Kind') }}</th>
                    <th class="border-b border-gray-200 px-6 pb-2 text-left text-xs font-medium text-gray-500 dark:border-gray-800 dark:text-gray-400">{{ __('Shown in') }}</th>
                    <th class="border-b border-gray-200 px-6 pb-2 text-left text-xs font-medium text-gray-500 dark:border-gray-800 dark:text-gray-400">{{ __('Updated') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($blocks as $block)
                    <tr wire:key="block-{{ $block->uuid }}" data-bw-row tabindex="0"
                        data-bw-row-href="{{ route('bladewright.admin.blocks.edit', $block) }}"
                        class="cursor-pointer transition hover:bg-gray-50 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-bw-accent dark:hover:bg-gray-800/50">
                        <td class="border-b border-gray-100 px-6 py-3 align-middle font-semibold dark:border-gray-800">{{ $block->name }}</td>
                        <td class="border-b border-gray-100 px-6 py-3 align-middle dark:border-gray-800">
                            <span class="inline-block rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-600 dark:bg-gray-800 dark:text-gray-300">{{ $block->type }}</span>
                        </td>
                        <td class="border-b border-gray-100 px-6 py-3 align-middle text-[0.8125rem] text-gray-500 dark:border-gray-800 dark:text-gray-400">
                            {{ ($n = $shownIn[$block->uuid] ?? 0) === 0 ? __('Nowhere yet') : __(':n component(s)', ['n' => $n]) }}
                        </td>
                        <td class="border-b border-gray-100 px-6 py-3 align-middle font-mono text-[0.8125rem] text-gray-500 dark:border-gray-800 dark:text-gray-400">
                            {{ optional($block->updated_at)->format('Y-m-d H:i') ?? '—' }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    @endif
</div>
