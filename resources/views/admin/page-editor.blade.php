<x-bladewright::layout :title="$page->name" :subtitle="'/'.$page->url">
    <x-slot:actions>
        {{-- The page as a visitor would meet it, in a tab of its own. --}}
        <x-bladewright::icon-link :href="route('bladewright.admin.pages.preview', $page)" :tip="__('Open in another tab')" target="_blank">
            {{-- lucide: external-link --}}
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
            </svg>
        </x-bladewright::icon-link>

        {{-- **Letting the world see it comes first**, then how the page is set up. --}}
        <button type="button" data-bw-modal-open="publish" data-tip="{{ __('Publishing') }}" aria-label="{{ __('Publishing') }}"
                class="bw-tip inline-flex cursor-pointer items-center justify-center rounded-lg border border-gray-300 bg-white p-2 text-gray-600 transition hover:bg-gray-50 hover:text-gray-900 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-gray-100">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 19V5m0 0-5.5 5.5M12 5l5.5 5.5"/>
                <path stroke-linecap="round" d="M4.5 20.5h15"/>
            </svg>
        </button>

        <x-bladewright::icon-link :href="route('bladewright.admin.pages.settings', $page)" :tip="__('Page settings')">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.3 4.3a1.5 1.5 0 0 1 3.4 0l.2 1a7.6 7.6 0 0 1 1.7 1l1-.4a1.5 1.5 0 0 1 1.9.7l.6 1a1.5 1.5 0 0 1-.4 2l-.8.6a7.6 7.6 0 0 1 0 2l.8.6a1.5 1.5 0 0 1 .4 2l-.6 1a1.5 1.5 0 0 1-1.9.7l-1-.4a7.6 7.6 0 0 1-1.7 1l-.2 1a1.5 1.5 0 0 1-3.4 0l-.2-1a7.6 7.6 0 0 1-1.7-1l-1 .4a1.5 1.5 0 0 1-1.9-.7l-.6-1a1.5 1.5 0 0 1 .4-2l.8-.6a7.6 7.6 0 0 1 0-2l-.8-.6a1.5 1.5 0 0 1-.4-2l.6-1a1.5 1.5 0 0 1 1.9-.7l1 .4c.5-.4 1.1-.8 1.7-1l.2-1Z"/>
                <circle cx="12" cy="12" r="2.6"/>
            </svg>
        </x-bladewright::icon-link>
    </x-slot:actions>

    <livewire:bladewright::page-editor :page="$page" :key="'page-'.$page->id" />
</x-bladewright::layout>
