<x-bladewright::layout :title="$block->name" :subtitle="__('Block settings')">
    <x-slot:actions>
        <a href="{{ route('bladewright.admin.blocks.edit', $block) }}"
           class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-[0.8125rem] font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">
            {{ __('Back to editing') }}
        </a>
    </x-slot:actions>

    <div class="w-full">
        <livewire:bladewright::block-settings :block="$block" :key="'block-settings-'.$block->id" />
    </div>
</x-bladewright::layout>
