@props(['href', 'active' => false, 'icon' => null])

{{-- One place to go. **The icon is named here and nowhere else**, so a menu
     entry is one line where it is used; folded, the icon is all that shows
     and the word rides in a tooltip on its right. --}}
<a href="{{ $href }}" @if ($icon) data-tip="{{ $slot }}" @endif
   @class([
       'bw-nav-link flex items-center gap-2.5 rounded-lg px-2 py-1 transition',
       'font-semibold text-bw-accent' => $active,
       'bw-nav-quiet text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-gray-100' => ! $active,
   ])>
    @if ($icon)
        {{-- A rounded square of its own, the mark's little sibling — its
             ground appears under the hand, and stays lit where you are. --}}
        <span @class([
            'bw-nav-square flex h-8 w-8 shrink-0 items-center justify-center rounded-lg transition',
            'bg-bw-accent/15 text-bw-accent dark:bg-bw-accent/25' => $active,
            'text-gray-500 dark:text-gray-400' => ! $active,
        ])>
        {{-- **Lucide**, pasted whole. The set has official ports for React
             Native, Flutter and SwiftUI, so a future mobile admin can wear
             the same pictures by the same names. --}}
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            @switch($icon)
                @case('pages') {{-- lucide: file-text --}}
                    <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>
                    @break
                @case('media') {{-- lucide: image --}}
                    <rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>
                    @break
                @case('layouts') {{-- lucide: layout-template --}}
                    <rect width="18" height="7" x="3" y="3" rx="1"/><rect width="9" height="7" x="3" y="14" rx="1"/><rect width="5" height="7" x="16" y="14" rx="1"/>
                    @break
                @case('components') {{-- lucide: layout-grid --}}
                    <rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/>
                    @break
                @case('blocks') {{-- lucide: box --}}
                    <path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/>
                    @break
                @case('settings') {{-- lucide: settings --}}
                    <path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/>
                    @break
            @endswitch
        </svg>
        </span>
    @endif
    <span class="bw-nav-label truncate">{{ $slot }}</span>
</a>
