<?php

use Livewire\Component;
use Bladewright\Access\Abilities;
use Bladewright\Support\Palette;
use Bladewright\Support\Toasts;

/*
 * The site's colours, kept once.
 *
 * **Blocks carry the name, not the value**, so this is where a colour is
 * actually decided — change `accent` here and every page wearing it changes
 * the next time it is asked for. An entry need not be a colour: a gradient
 * is one entry like any other, and only a background can wear it.
 */
new class extends Component
{
    use Toasts;

    /** @var array<int, array{name: string, value: string}> */
    public array $rows = [];

    public function mount(): void
    {
        foreach (app(Palette::class)->all() as $name => $value) {
            $this->rows[] = ['name' => $name, 'value' => $value];
        }

        $this->rows[] = ['name' => '', 'value' => ''];
    }

    /** One more line to write in. */
    public function addRow(): void
    {
        $this->rows[] = ['name' => '', 'value' => ''];
    }

    public function removeRow(int $index): void
    {
        unset($this->rows[$index]);

        $this->rows = array_values($this->rows);
    }

    public function save(): void
    {
        $this->authorize(Abilities::gate(Abilities::MANAGE_SETTINGS));

        $entries = [];

        foreach ($this->rows as $row) {
            $entries[trim((string) $row['name'])] = trim((string) $row['value']);
        }

        try {
            // **Before the list becomes names**, or a repeat disappears into
            // the one before it.
            app(Palette::class)->assertNamesAreUnique(array_column($this->rows, 'name'));

            $kept = app(Palette::class)->save($entries);
        } catch (\InvalidArgumentException $e) {
            $this->toastError($e->getMessage());

            return;
        }

        $this->rows = [];

        foreach ($kept as $name => $value) {
            $this->rows[] = ['name' => $name, 'value' => $value];
        }

        $this->rows[] = ['name' => '', 'value' => ''];

        // **The reach is said with the result.**
        $this->toast(__('Saved. Every page using these names changes with them.'));
    }
};
?>

<div class="mt-4 rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
    <h2 class="m-0 text-base font-semibold">{{ __('Colours') }}</h2>
    <p class="mt-1 text-[0.8125rem] text-gray-500 dark:text-gray-400">
        {{ __('Blocks are painted by name, so a colour is decided here and nowhere else. A gradient is an entry like any other — only a background can wear one.') }}
    </p>

    <div class="mt-4 space-y-2">
        @foreach ($rows as $index => $row)
            <div class="flex items-center gap-2" wire:key="colour-{{ $index }}">
                {{-- What the value paints, shown beside it. --}}
                <span class="h-9 w-9 shrink-0 rounded-lg border border-gray-200 dark:border-gray-700"
                      style="background:{{ app(\Bladewright\Support\Palette::class)->reads(trim((string) $row['value'])) ? $row['value'] : 'transparent' }}"></span>

                <input type="text" placeholder="{{ __('name') }}"
                       class="w-40 shrink-0 rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 font-mono text-[0.75rem] shadow-xs transition focus:border-bw-accent focus:ring-2 focus:ring-bw-accent/20 focus:outline-none dark:border-gray-700 dark:bg-gray-950"
                       wire:model.live.debounce.500ms="rows.{{ $index }}.name">

                <input type="text" placeholder="#3538cd"
                       class="min-w-0 flex-1 rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 font-mono text-[0.75rem] shadow-xs transition focus:border-bw-accent focus:ring-2 focus:ring-bw-accent/20 focus:outline-none dark:border-gray-700 dark:bg-gray-950"
                       wire:model.live.debounce.500ms="rows.{{ $index }}.value">

                <button type="button" wire:click="removeRow({{ $index }})"
                        class="shrink-0 cursor-pointer rounded-md p-1 text-gray-400 transition hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950 dark:hover:text-red-400"
                        aria-label="{{ __('Take it out') }}">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/></svg>
                </button>
            </div>
        @endforeach
    </div>

    @can(\Bladewright\Access\Abilities::gate(\Bladewright\Access\Abilities::MANAGE_SETTINGS))
        <div class="mt-4 flex items-center justify-between gap-2">
            <button type="button" wire:click="addRow"
                    class="inline-flex cursor-pointer items-center gap-1 rounded-md border border-gray-300 bg-white px-2.5 py-1.5 text-[0.8125rem] font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">
                {{ __('One more') }}
            </button>

            <button type="button" wire:click="save"
                    class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg bg-linear-to-tl from-bw-accent-2 to-bw-accent px-3 py-1.5 text-[0.8125rem] font-semibold text-white shadow-xs transition hover:brightness-110">
                {{ __('Save') }}
            </button>
        </div>
    @endcan
</div>
