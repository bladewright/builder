{{-- The site's light and dark, inside the preview. **Auto until pressed** —
     the frames follow the visitor's machine, and so does the preview. --}}
<div class="inline-flex gap-0.5 rounded-lg bg-gray-200/70 p-0.5 dark:bg-gray-800">
    <button type="button" class="bw-scheme bw-tip inline-flex h-7 cursor-pointer items-center justify-center rounded-md px-2 text-gray-500 transition dark:text-gray-400"
            data-bw-scheme-set="light" data-tip="{{ __('Light') }}" aria-label="{{ __('Light') }}">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
            <circle cx="12" cy="12" r="4"/>
            <path stroke-linecap="round" d="M12 3v2m0 14v2M3 12h2m14 0h2M5.6 5.6l1.4 1.4m10 10 1.4 1.4m0-12.8-1.4 1.4m-10 10-1.4 1.4"/>
        </svg>
    </button>
    <button type="button" class="bw-scheme bw-tip inline-flex h-7 cursor-pointer items-center justify-center rounded-md px-2 text-gray-500 transition dark:text-gray-400"
            data-bw-scheme-set="dark" data-tip="{{ __('Dark') }}" aria-label="{{ __('Dark') }}">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M20 14.5A8 8 0 1 1 9.5 4a6.5 6.5 0 0 0 10.5 10.5Z"/>
        </svg>
    </button>
</div>
