<x-bladewright::layout :title="__('Take the site as files')" :subtitle="__('Every published page, written out as plain HTML')">
    <x-slot:actions>
        <a href="{{ route('bladewright.admin.settings') }}"
           class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-[0.8125rem] font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">
            {{ __('Back to settings') }}
        </a>
    </x-slot:actions>

    <div class="w-full max-w-3xl">
        <livewire:bladewright::site-export />
    </div>
</x-bladewright::layout>
