<?php

use Livewire\Component;
use Bladewright\Access\Abilities;
use Bladewright\Support\SiteCss;
use Bladewright\Support\Toasts;

/*
 * The site's one stylesheet.
 *
 * **Where what an inline style cannot say gets said**: hover and focus, a
 * media query, a class shared by twenty blocks. The layouts link it with
 * @bwstyles, and a change reaches every page wearing them at once.
 */
new class extends Component
{
    use Toasts;

    public string $css = '';

    public function mount(): void
    {
        $this->css = app(SiteCss::class)->get();
    }

    public function save(): void
    {
        $this->authorize(Abilities::gate(Abilities::MANAGE_SETTINGS));

        app(SiteCss::class)->save($this->css);

        // **The reach is said with the result.**
        $this->toast(__('Saved. Every page whose layout links it changes at once.'));
    }
};
?>

<div class="mt-4 rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 class="m-0 text-base font-semibold">{{ __('Stylesheet') }}</h2>

        @can(\Bladewright\Access\Abilities::gate(\Bladewright\Access\Abilities::MANAGE_SETTINGS))
            <button type="button" wire:click="save"
                    class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg bg-linear-to-tl from-bw-accent-2 to-bw-accent px-3 py-1.5 text-[0.8125rem] font-semibold text-white shadow-xs transition hover:brightness-110">
                {{ __('Save') }}
            </button>
        @endcan
    </div>

    <p class="mt-1 text-[0.8125rem] text-gray-500 dark:text-gray-400">
        @php($word = '@'.'bwstyles')
        {{ __('One file for the whole site — hover and focus, media queries, classes for the Code pills to use. The starter layouts link it already; your own frames link it with :word.', ['word' => $word]) }}
    </p>

    <div class="mt-4" data-bw-code-doc="{{ $css }}">
        <div wire:ignore>
            <textarea rows="16" data-bw-code="css" spellcheck="false"
                      class="w-full resize-y rounded-lg border border-gray-200 bg-gray-100 p-4 font-mono text-[0.8125rem]/6 focus:outline-2 focus:outline-offset-1 focus:outline-bw-accent dark:border-gray-700 dark:bg-gray-800"
                      wire:model.live.debounce.500ms="css">{{ $css }}</textarea>
        </div>
    </div>
</div>
