{{-- **One card, one attribute** — the way Style is the style attribute's
     mirror, this is the class attribute's. For whoever brought a
     framework: Bootstrap's words go here, and so do the site's own from
     the stylesheet under Settings. --}}
<div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
    <h2 class="m-0 text-base font-semibold">{{ __('Class') }}</h2>
    <p class="mt-1 text-[0.8125rem] text-gray-500 dark:text-gray-400">
        {{ __('Straight onto the element, for whatever stylesheet the site loads.') }}
    </p>

    <div class="mt-4">
        <label class="block font-mono text-[0.75rem] text-gray-600 dark:text-gray-400">class</label>
        <input type="text" placeholder="btn btn-primary"
               class="mt-1.5 w-full rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 font-mono text-[0.75rem] shadow-xs transition focus:border-bw-accent focus:ring-2 focus:ring-bw-accent/20 focus:outline-none dark:border-gray-700 dark:bg-gray-950"
               wire:model.live.debounce.500ms="class">
    </div>
</div>
