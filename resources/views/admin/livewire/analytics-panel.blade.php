<?php

use Livewire\Component;
use Bladewright\Access\Abilities;
use Bladewright\Support\Analytics;
use Bladewright\Support\Toasts;

/*
 * The Analytics room: one measurement id, and everything else is ours.
 *
 * **The id is data; the script is the renderer's** — nothing pasted here
 * ever runs as written.
 */
new class extends Component
{
    use Toasts;

    /** The part after `G-` — the prefix stands outside the box, printed. */
    public string $code = '';

    public function mount(): void
    {
        $this->code = (string) preg_replace('/^G-/i', '', app(Analytics::class)->get());
    }

    public function save(): void
    {
        $this->authorize(Abilities::gate(Abilities::MANAGE_SETTINGS));

        // Pasting the whole `G-XXXX` into the box is met halfway.
        $code = (string) preg_replace('/^G-/i', '', trim($this->code));

        try {
            app(Analytics::class)->save($code === '' ? '' : 'G-'.$code);
        } catch (\InvalidArgumentException $e) {
            $this->toastError($e->getMessage());

            return;
        }

        $this->code = $code;

        $this->toast($code === ''
            ? __('Turned off. The pages carry no analytics now.')
            : __('Saved. Every public page carries it from the next request.'));
    }
};
?>

<div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
    <h2 class="m-0 text-base font-semibold">{{ __('Google Analytics') }}</h2>
    <p class="mt-1 text-[0.8125rem] text-gray-500 dark:text-gray-400">
        {{ __('The measurement id from your Google Analytics property. The script itself is written by the site — onto the public pages only, never onto these screens or their previews.') }}
    </p>

    <div class="mt-4 flex flex-col gap-1.5 sm:flex-row sm:items-start sm:gap-4">
        <label class="w-40 shrink-0 pt-2 font-mono text-[0.75rem] text-gray-600 dark:text-gray-400">measurement id</label>
        <div class="flex flex-1 flex-wrap items-start gap-2">
            {{-- `G-` is not the owner's to type: it stands printed, the way
                 the URL field prints its `/`. --}}
            <div class="flex min-w-0 flex-1 items-stretch rounded-lg border border-gray-300 bg-white shadow-xs transition focus-within:border-bw-accent focus-within:ring-2 focus-within:ring-bw-accent/20 dark:border-gray-700 dark:bg-gray-950">
                <span class="flex select-none items-center border-r border-gray-200 px-3 font-mono text-[0.8125rem] text-gray-500 dark:border-gray-800 dark:text-gray-400">G-</span>
                <input type="text" placeholder="XXXXXXXXXX"
                       class="w-full rounded-r-lg border-0 bg-transparent px-3 py-1.5 font-mono text-[0.8125rem] focus:outline-none"
                       wire:model="code" wire:keydown.enter="save">
            </div>
            @can(\Bladewright\Access\Abilities::gate(\Bladewright\Access\Abilities::MANAGE_SETTINGS))
                <button type="button" wire:click="save"
                        class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg bg-linear-to-tl from-bw-accent-2 to-bw-accent px-3 py-1.5 text-[0.8125rem] font-semibold text-white shadow-xs transition hover:brightness-110">
                    {{ __('Save') }}
                </button>
            @endcan
            <div class="w-full text-[0.8125rem] text-gray-500 dark:text-gray-400">
                {{ __('Empty turns it off.') }}
            </div>
        </div>
    </div>
</div>
