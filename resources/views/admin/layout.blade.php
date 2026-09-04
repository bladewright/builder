@php($abilities = \Bladewright\Access\Abilities::class)
@php($me = auth()->guard(config('bladewright.auth.guard'))->user())
@php($current = request()->path())
{{-- **Where you are is decided by where the URL begins, not by a word in it.**
     Looking anywhere in the path lit Settings up on `pages/1/settings`, which
     is a page's settings and nothing to do with the site's. --}}
@php($section = fn (string $name) => str_starts_with($current, ltrim(config('bladewright.admin.prefix'), '/').'/'.$name))

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Bladewright' }}</title>
    <link rel="stylesheet" href="@bwasset('bladewright.css')">
    {{-- **Decided before rendering.** Adding the class afterwards makes the
         sidebar flash into view and vanish again. --}}
    <script>
        try {
            const root = document.documentElement
            root.dataset.bwSidebar = localStorage.getItem('bw-sidebar') || 'open'
            // **Light unless somebody said otherwise.** More than one person
            // opens this admin, and light is the assumption a shared tool can
            // make. The machine's own setting is not followed either: a tool
            // that changes appearance with the time of day is a tool nobody
            // recognises. The toggle by Sign out is each person's own.
            root.dataset.bwTheme = localStorage.getItem('bw-theme') || 'light'
        } catch (e) {}
    </script>
    {{-- **The editor rides behind, and only where it is wanted.** Its src
         is carried here rather than worked out in JavaScript, so the version
         stamp is the same one every other asset wears. --}}
    <script src="@bwasset('bladewright.js')" data-bw-editor="@bwasset('bladewright-editor.js')" defer></script>
    @livewireStyles
</head>
<body class="min-h-screen bg-gray-50 text-gray-900 dark:bg-gray-950 dark:text-gray-100">
    {{-- The handle on the edge of the sidebar. **Vertically centred** so it is
         within reach wherever you are looking (the same idea as the collapse
         button on a map). --}}
    <button type="button" data-bw-sidebar-toggle
            class="bw-sidebar-handle bw-tip fixed top-1/2 z-40 hidden h-12 w-5 cursor-pointer items-center justify-center
                   border border-gray-200 bg-white text-gray-400 shadow-sm transition
                   hover:text-gray-700 lg:flex dark:border-gray-800 dark:bg-gray-900 dark:hover:text-gray-200"
            aria-label="{{ __('Show or hide the menu') }}" data-tip="{{ __('Show or hide the menu') }}">
        <span class="bw-sidebar-close text-xs leading-none">‹</span>
        <span class="bw-sidebar-show text-xs leading-none">›</span>
    </button>

    {{-- **clip, not hidden.** `overflow-x: hidden` makes this a scrolling
         box, and anything sticky inside sticks to it rather than to the
         window — which is to say, not at all. --}}
    <div class="flex min-h-screen overflow-x-clip">
        {{-- The left sidebar. **Where you can go stays visible** (it used to be
             buried in a bar). --}}
        {{-- **It stays where it is put.** The editors run past the fold, and
             a sidebar that scrolls away with them leaves nowhere to go from
             the bottom of a long screen. --}}
        {{-- No scroll box of its own: seven rows always fit, and an
             overflow-y would clip the tooltips standing to its right. --}}
        <aside class="bw-sidebar hidden w-60 shrink-0 flex-col border-r border-gray-200 bg-white lg:sticky lg:top-0 lg:z-30 lg:flex lg:h-screen lg:self-start dark:border-gray-800 dark:bg-gray-900">
            <div class="flex items-center gap-2 px-4 py-5">
                {{-- indigo → cyan, inherited from v3. **The branding lives in this one place.** --}}
                <a href="{{ route('bladewright.admin.home') }}" class="flex items-center gap-2.5">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-linear-to-tl from-bw-accent-2 to-bw-accent text-sm font-bold text-white">B</span>
                    <span class="bw-nav-label bg-linear-to-tl from-bw-accent-2 to-bw-accent bg-clip-text text-base font-bold tracking-tight text-transparent">Bladewright</span>
                </a>

            </div>

            {{-- The screens for the four-layer world are being rebuilt; the
                 media library is the one content screen standing. --}}
            <nav class="flex-1 space-y-1 px-2 pb-4 text-sm">
                {{-- **The site is the four layers**, top to bottom; what
                     feeds them is a group of its own. --}}
                <p class="bw-nav-head px-2 pt-1 pb-1 text-[0.6875rem] font-medium tracking-wider text-gray-400 uppercase">{{ __('Site') }}</p>
                <x-bladewright::nav-link :href="route('bladewright.admin.pages')" :active="$section('pages')" icon="pages">{{ __('Pages') }}</x-bladewright::nav-link>
                <x-bladewright::nav-link :href="route('bladewright.admin.layouts')" :active="$section('layouts')" icon="layouts">{{ __('Layouts') }}</x-bladewright::nav-link>
                <x-bladewright::nav-link :href="route('bladewright.admin.components')" :active="$section('components')" icon="components">{{ __('Components') }}</x-bladewright::nav-link>
                <x-bladewright::nav-link :href="route('bladewright.admin.blocks')" :active="$section('blocks')" icon="blocks">{{ __('Blocks') }}</x-bladewright::nav-link>

                <p class="bw-nav-head px-2 pt-5 pb-1 text-[0.6875rem] font-medium tracking-wider text-gray-400 uppercase">{{ __('Data') }}</p>
                <x-bladewright::nav-link :href="route('bladewright.admin.media')" :active="$section('media')" icon="media">{{ __('Media') }}</x-bladewright::nav-link>

                @can($abilities::gate($abilities::MANAGE_SETTINGS))
                    <p class="bw-nav-head px-2 pt-5 pb-1 text-[0.6875rem] font-medium tracking-wider text-gray-400 uppercase">{{ __('Admin') }}</p>
                    <x-bladewright::nav-link :href="route('bladewright.admin.settings')" :active="$section('settings')" icon="settings">{{ __('Settings') }}</x-bladewright::nav-link>
                @endcan
            </nav>

            @if ($me)
                <div class="border-t border-gray-200 px-3 py-3 dark:border-gray-800">
                    <form method="post" action="{{ route('bladewright.admin.logout') }}" class="bw-user-row flex items-center justify-between gap-2">
                        @csrf
                        <span class="bw-nav-label truncate text-[0.8125rem] text-gray-500 dark:text-gray-400">{{ $me->name ?? $me->email ?? '' }}</span>

                        <div class="bw-user-tools flex shrink-0 items-center gap-3">
                            {{-- **An icon, beside signing out.** It belongs to
                                 whoever is looking at this screen, not to the
                                 site — the settings hold the site's own dark
                                 mode, and a row there would be read as that. --}}
                            <button type="button" data-bw-theme-toggle
                                    class="bw-theme-toggle cursor-pointer text-gray-400 transition hover:text-gray-700 dark:hover:text-gray-200"
                                    aria-label="{{ __('Light or dark') }}">
                                <svg class="bw-theme-sun h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                    <circle cx="12" cy="12" r="4"/>
                                    <path stroke-linecap="round" d="M12 3v2m0 14v2M3 12h2m14 0h2M5.6 5.6l1.4 1.4m10 10 1.4 1.4m0-12.8-1.4 1.4m-10 10-1.4 1.4"/>
                                </svg>
                                <svg class="bw-theme-moon h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M20 14.5A8 8 0 1 1 9.5 4a6.5 6.5 0 0 0 10.5 10.5Z"/>
                                </svg>
                            </button>

                            {{-- An icon, like the one beside it. **The tooltip
                                 carries the word**, so nobody presses a door
                                 without knowing where it goes. --}}
                            <button type="submit" class="bw-tip bw-tip-above cursor-pointer text-gray-400 transition hover:text-gray-700 dark:hover:text-gray-200"
                                    data-tip="{{ __('Sign out') }}" aria-label="{{ __('Sign out') }}">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 17v1.5a1.5 1.5 0 0 1-1.5 1.5h-7A1.5 1.5 0 0 1 5 18.5v-13A1.5 1.5 0 0 1 6.5 4h7A1.5 1.5 0 0 1 15 5.5V7"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 12h9m0 0-3-3m3 3-3 3"/>
                                </svg>
                            </button>
                        </div>
                    </form>
                </div>
            @endif
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            {{-- On a narrow screen the sidebar becomes a row of links --}}
            <nav class="flex gap-4 overflow-x-auto border-b border-gray-200 bg-white px-4 py-3 text-sm lg:hidden dark:border-gray-800 dark:bg-gray-900">
                <a href="{{ route('bladewright.admin.pages') }}">{{ __('Pages') }}</a>
                <a href="{{ route('bladewright.admin.layouts') }}">{{ __('Layouts') }}</a>
                <a href="{{ route('bladewright.admin.components') }}">{{ __('Components') }}</a>
                <a href="{{ route('bladewright.admin.blocks') }}">{{ __('Blocks') }}</a>
                <a href="{{ route('bladewright.admin.media') }}">{{ __('Media') }}</a>
                @can($abilities::gate($abilities::MANAGE_SETTINGS))
                    <a href="{{ route('bladewright.admin.settings') }}">{{ __('Settings') }}</a>
                @endcan
            </nav>

            {{-- **Nothing is squeezed horizontally.** Tables, block arrangements and
                 code are all screens where width *is* legibility, so they do not
                 get a prose measure. The heading has no background of its own
                 either; it sits on the same surface as the content. --}}
            <main class="flex-1 px-6 py-6">
                <header class="mb-5 flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h1 class="text-xl font-semibold tracking-tight">{{ $title ?? '' }}</h1>
                        @isset($subtitle)
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $subtitle }}</p>
                        @endisset
                    </div>

                    @isset($actions)
                        <div class="flex shrink-0 items-center gap-2">{{ $actions }}</div>
                    @endisset
                </header>

                {{ $slot }}
            </main>
        </div>
    </div>

    {{-- Where notices go. **They never push the page down**, so a table does
         not jump every time something is saved. --}}
    <div id="bw-toasts" class="pointer-events-none fixed inset-x-0 bottom-0 z-50 flex flex-col items-center gap-2 p-4 sm:items-end"></div>

    @livewireScripts
</body>
</html>
