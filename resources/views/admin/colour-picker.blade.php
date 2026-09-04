{{-- The site's colours, opened under whatever asked for them.

     **The palette first**, since a name follows it wherever it is used; a
     colour written in below belongs to this block alone. --}}
@php($field = collect($this->styleFields())->firstWhere('key', $colouring))

<div class="mt-2 rounded-lg border border-gray-200 p-2 dark:border-gray-700">
    <div class="flex items-center justify-between gap-2">
        <span class="text-[0.75rem] font-medium text-gray-600 dark:text-gray-400">{{ $field['label'] }}</span>
        <button type="button" wire:click="$set('colouring', null)"
                class="cursor-pointer rounded-md p-0.5 text-gray-400 transition hover:text-gray-700 dark:hover:text-gray-200"
                aria-label="{{ __('Close') }}">
            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/></svg>
        </button>
    </div>

    {{-- **The site's colours first**, since a name follows the
         palette wherever it is used; a colour written in below
         belongs to this block alone. --}}
    <div class="mt-2 flex flex-wrap gap-1.5">
        <button type="button" wire:click="paint('{{ $colouring }}', '')"
                class="bw-tip h-7 w-7 cursor-pointer rounded-md border border-gray-300 bg-white dark:border-gray-600 dark:bg-gray-900"
                data-tip="{{ __('nothing') }}" aria-label="{{ __('nothing') }}">
            <svg class="mx-auto h-3.5 w-3.5 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="M5 19 19 5"/></svg>
        </button>

        @foreach ($this->palette() as $name => $value)
            <button type="button" wire:click="paint('{{ $colouring }}', '{{ $name }}')"
                    @class(['bw-tip h-7 w-7 cursor-pointer rounded-md border transition', 'border-bw-accent ring-2 ring-bw-accent/30' => ($style[$colouring] ?? '') === $name, 'border-gray-300 dark:border-gray-600' => ($style[$colouring] ?? '') !== $name])
                    style="background:{{ $value }}"
                    data-tip="{{ $name }}" aria-label="{{ $name }}"></button>
        @endforeach
    </div>

    <div class="mt-2 flex items-center gap-2">
        <input type="text" placeholder="#3538cd"
               class="min-w-0 flex-1 rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 font-mono text-[0.75rem] shadow-xs transition focus:border-bw-accent focus:ring-2 focus:ring-bw-accent/20 focus:outline-none dark:border-gray-700 dark:bg-gray-950"
               wire:model.live.debounce.500ms="style.{{ $colouring }}">

        @if ($field['image'] ?? false)
            {{-- **A background may be a picture.** --}}
            <button type="button" wire:click="pickStyleImage('{{ $colouring }}')"
                    class="inline-flex shrink-0 cursor-pointer items-center gap-1 rounded-md border border-gray-300 bg-white px-2.5 py-1.5 text-[0.75rem] font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">
                {{ __('An image') }}
            </button>
        @endif
    </div>
</div>
