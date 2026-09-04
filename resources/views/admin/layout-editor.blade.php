<x-bladewright::layout :title="$part->name" :subtitle="$part->type">
    <x-slot:actions>
        <x-bladewright::icon-link :href="route('bladewright.admin.layouts.settings', $part)" :tip="__('Layout settings')">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.3 4.3a1.5 1.5 0 0 1 3.4 0l.2 1a7.6 7.6 0 0 1 1.7 1l1-.4a1.5 1.5 0 0 1 1.9.7l.6 1a1.5 1.5 0 0 1-.4 2l-.8.6a7.6 7.6 0 0 1 0 2l.8.6a1.5 1.5 0 0 1 .4 2l-.6 1a1.5 1.5 0 0 1-1.9.7l-1-.4a7.6 7.6 0 0 1-1.7 1l-.2 1a1.5 1.5 0 0 1-3.4 0l-.2-1a7.6 7.6 0 0 1-1.7-1l-1 .4a1.5 1.5 0 0 1-1.9-.7l-.6-1a1.5 1.5 0 0 1 .4-2l.8-.6a7.6 7.6 0 0 1 0-2l-.8-.6a1.5 1.5 0 0 1-.4-2l.6-1a1.5 1.5 0 0 1 1.9-.7l1 .4c.5-.4 1.1-.8 1.7-1l.2-1Z"/>
                <circle cx="12" cy="12" r="2.6"/>
            </svg>
        </x-bladewright::icon-link>
    </x-slot:actions>

    <livewire:bladewright::layout-editor :layout="$part" :key="'layout-'.$part->id" />
</x-bladewright::layout>
