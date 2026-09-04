<x-bladewright::layout :title="__('Settings')" :subtitle="__('The doors to how this site behaves')">
    {{-- **A hall of doors, not a hall of desks.** The settings will keep
         growing; each one works in a room of its own. --}}
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
        @foreach ([
            ['route' => 'bladewright.admin.settings.colours', 'title' => __('Colours'), 'says' => __('The palette blocks are painted by — change a name here and every page follows'), 'icon' => 'palette'],
            ['route' => 'bladewright.admin.settings.stylesheet', 'title' => __('Stylesheet'), 'says' => __('One CSS file for the whole site: hover, media queries, shared classes'), 'icon' => 'braces'],
            ['route' => 'bladewright.admin.settings.application', 'title' => __('Application'), 'says' => __('The Laravel side: the site\'s name, language, timezone'), 'icon' => 'sliders'],
            ['route' => 'bladewright.admin.settings.analytics', 'title' => __('Analytics'), 'says' => __('Google Analytics from one measurement id — public pages only'), 'icon' => 'chart'],
        ] as $door)
            <a href="{{ route($door['route']) }}" data-bw-row tabindex="0"
               class="flex cursor-pointer items-center gap-4 border-b border-gray-100 px-5 py-4 transition last:border-b-0 hover:bg-gray-50 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-bw-accent dark:border-gray-800 dark:hover:bg-gray-800/50">
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        @if ($door['icon'] === 'palette') {{-- lucide: palette --}}
                            <circle cx="13.5" cy="6.5" r=".5" fill="currentColor"/><circle cx="17.5" cy="10.5" r=".5" fill="currentColor"/><circle cx="8.5" cy="7.5" r=".5" fill="currentColor"/><circle cx="6.5" cy="12.5" r=".5" fill="currentColor"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z"/>
                        @elseif ($door['icon'] === 'braces') {{-- lucide: braces --}}
                            <path d="M8 3H7a2 2 0 0 0-2 2v5a2 2 0 0 1-2 2 2 2 0 0 1 2 2v5c0 1.1.9 2 2 2h1"/><path d="M16 21h1a2 2 0 0 0 2-2v-5c0-1.1.9-2 2-2a2 2 0 0 1-2-2V5a2 2 0 0 0-2-2h-1"/>
                        @elseif ($door['icon'] === 'chart') {{-- lucide: chart-line --}}
                            <path d="M3 3v16a2 2 0 0 0 2 2h16"/><path d="m19 9-5 5-4-4-3 3"/>
                        @else {{-- lucide: sliders-horizontal --}}
                            <line x1="21" x2="14" y1="4" y2="4"/><line x1="10" x2="3" y1="4" y2="4"/><line x1="21" x2="12" y1="12" y2="12"/><line x1="8" x2="3" y1="12" y2="12"/><line x1="21" x2="16" y1="20" y2="20"/><line x1="12" x2="3" y1="20" y2="20"/><line x1="14" x2="14" y1="2" y2="6"/><line x1="8" x2="8" y1="10" y2="14"/><line x1="16" x2="16" y1="18" y2="22"/>
                        @endif
                    </svg>
                </span>

                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-semibold">{{ $door['title'] }}</span>
                    <span class="block truncate text-[0.8125rem] text-gray-500 dark:text-gray-400">{{ $door['says'] }}</span>
                </span>

                <svg class="h-4 w-4 shrink-0 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
            </a>
        @endforeach
    </div>
</x-bladewright::layout>
