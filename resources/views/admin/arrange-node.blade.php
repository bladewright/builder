{{-- One place in the arrangement, in the column where it is arranged.

     **What this screen owns can be dragged**; what stands below that belongs
     to its own component's screen, and is here to be read, not moved. --}}
<li wire:key="arrange-{{ $node['path'] ?? $node['name'].'-'.$depth }}"
    @if ($node['movable'])
        draggable="true" data-bw-path="{{ $node['path'] }}"
    @endif>
    <div @class([
        'flex items-center gap-2 rounded-lg border px-3 py-2',
        'cursor-grab border-gray-200 dark:border-gray-700' => $node['movable'],
        'border-transparent bg-gray-50 dark:bg-gray-800/50' => ! $node['movable'],
    ])>
        @if ($node['movable'])
            {{-- The grip says it can be picked up before anybody tries. --}}
            <svg class="h-4 w-4 shrink-0 text-gray-300 dark:text-gray-600" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <circle cx="9" cy="6" r="1.5"/><circle cx="15" cy="6" r="1.5"/>
                <circle cx="9" cy="12" r="1.5"/><circle cx="15" cy="12" r="1.5"/>
                <circle cx="9" cy="18" r="1.5"/><circle cx="15" cy="18" r="1.5"/>
            </svg>
        @endif

        <span class="shrink-0 rounded-full px-2 py-0.5 text-[0.6875rem] font-semibold {{ $node['kind'] === 'component' ? 'bg-bw-accent/10 text-bw-accent' : 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400' }}">{{ $node['type'] }}</span>

        @if ($node['press'] ?? null)
            {{-- The cards open beside this screen — no navigating away. --}}
            <button type="button" wire:click="{{ $node['press'] }}"
                    class="flex min-w-0 flex-1 cursor-pointer items-center gap-1.5 truncate border-0 bg-transparent p-0 text-left text-sm font-medium text-inherit hover:underline">
                <span class="min-w-0 truncate">{{ $node['name'] }}</span>
                @if ($node['inline'] ?? null)
                    <svg @class(['h-3.5 w-3.5 shrink-0 text-gray-400 transition-transform', 'rotate-90' => $node['inline_open'] ?? false]) viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
                @endif
            </button>
        @elseif ($node['url'])
            <a href="{{ $node['url'] }}" class="min-w-0 flex-1 truncate text-sm font-medium text-inherit hover:underline">{{ $node['name'] }}</a>
        @else
            <span class="min-w-0 flex-1 truncate text-sm font-medium">{{ $node['name'] }}</span>
        @endif

        @if ($node['remove'] ?? null)
            @can(\Bladewright\Access\Abilities::gate(\Bladewright\Access\Abilities::EDIT_CONTENT))
                <button type="button" wire:click="{{ $node['remove'] }}"
                        class="shrink-0 cursor-pointer rounded-md p-1 text-gray-400 transition hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950 dark:hover:text-red-400"
                        aria-label="{{ __('Take it out') }}">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/></svg>
                </button>
            @endcan
        @endif
    </div>

    @if (($node['inline_open'] ?? false) && ($inlineBlock = \Bladewright\Models\Block::query()->where('uuid', $node['inline'])->first()))
        {{-- **The words, open under the title** — the slim face: contents
             alone, whispering unsaved edits up to the page. --}}
        <div class="mt-1.5 mb-1 ml-6 border-l border-gray-200 pl-3 dark:border-gray-700">
            <livewire:bladewright::block-editor :block="$inlineBlock" :embedded="true" :slim="true" :key="$node['inline_key']" />
        </div>
    @endif

    @if ($node['children'] !== [])
        <ul class="m-0 mt-1 list-none space-y-1 border-l border-gray-200 p-0 pl-3 dark:border-gray-700">
            @foreach ($node['children'] as $child)
                @include('bladewright::admin.arrange-node', ['node' => $child, 'depth' => $depth + 1])
            @endforeach
        </ul>
    @elseif ($node['holds'] ?? false)
        {{-- The tag is there; nothing stands in it yet. --}}
        <div class="mt-1 ml-3 border-l border-gray-200 py-2 pl-3 text-[0.8125rem] text-gray-500 dark:border-gray-700 dark:text-gray-400">
            {{ __('Nothing in it yet.') }}
        </div>
    @endif
</li>
