{{-- The Style card, worn by whatever has a look to decide.

     **One card, two editors** — the machinery is `Support\StyleCard`, this
     is its face. The host guards when the card shows at all. --}}
        {{-- The gap to a card above is the column's business, not this card's —
     on a div there is no card above at all. --}}
<div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="m-0 text-base font-semibold">{{ __('Style') }}</h2>

                {{-- **Pressed, or written.** The controls make the CSS and the
                     CSS makes the controls; the same look, two ways in. --}}
                <div class="inline-flex gap-1 rounded-lg bg-gray-200/70 p-0.5 dark:bg-gray-800">
                    <button type="button" class="bw-pill cursor-pointer rounded-md border-0 bg-transparent px-2.5 py-1 text-[0.75rem] font-medium text-gray-500 transition hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100"
                            data-bw-pills="style" data-bw-pill="controls">{{ __('Controls') }}</button>
                    <button type="button" class="bw-pill cursor-pointer rounded-md border-0 bg-transparent px-2.5 py-1 text-[0.75rem] font-medium text-gray-500 transition hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100"
                            data-bw-pills="style" data-bw-pill="css">{{ __('CSS') }}</button>
                </div>
            </div>

            {{-- **Written by hand, and read back.** A property the card knows
                 goes into its control; anything else is kept as typed and
                 written last, so a hand overrules a press. --}}
            <div class="mt-3" data-bw-pills="style" data-bw-panel="css" hidden data-bw-code-doc="{{ $css }}">
                <div wire:ignore>
                    <textarea rows="10" data-bw-code="css" spellcheck="false"
                              class="w-full resize-y rounded-lg border border-gray-200 bg-gray-100 p-3 font-mono text-[0.75rem]/6 focus:outline-2 focus:outline-offset-1 focus:outline-bw-accent dark:border-gray-700 dark:bg-gray-800"
                              wire:model.live.debounce.500ms="css">{{ $css }}</textarea>
                </div>
            </div>

            <div data-bw-pills="style" data-bw-panel="controls">

            {{-- **The four a writer already knows**, pressed rather than
                 chosen: the same switches a markdown toolbar has. --}}
            @php($switches = array_values(array_filter($this->styleFields(), fn ($f) => $f['kind'] === 'switch')))

            @php($pills = array_values(array_filter($this->styleFields(), fn ($f) => ($f['pills'] ?? false) && $f['kind'] === 'choice')))

            <div class="mt-4 flex flex-wrap items-center gap-2">
                <div @class(['inline-flex gap-0.5 rounded-lg bg-gray-200/70 p-0.5 dark:bg-gray-800', 'hidden' => $switches === []])>
                    @foreach ($switches as $switch)
                        <button type="button" wire:click="toggle('{{ $switch['key'] }}')"
                                @class([
                                    'bw-pill bw-tip w-9 cursor-pointer rounded-md px-2 py-1 text-sm transition',
                                    'font-bold' => $switch['key'] === 'bold',
                                    'italic' => $switch['key'] === 'italic',
                                    'underline' => $switch['key'] === 'underline',
                                    'line-through' => $switch['key'] === 'strike',
                                    'is-on' => ($style[$switch['key']] ?? '') === $switch['on'],
                                    'text-gray-500 dark:text-gray-400' => ($style[$switch['key']] ?? '') !== $switch['on'],
                                ])
                                data-tip="{{ $switch['label'] }}" aria-label="{{ $switch['label'] }}"
                                aria-pressed="{{ ($style[$switch['key']] ?? '') === $switch['on'] ? 'true' : 'false' }}">{{ $switch['letter'] }}</button>
                    @endforeach
                </div>

                {{-- **The colours, as a writing tool shows them**: the
                     letter and the paint, each wearing what it is set to.
                     Pressing one opens the site's palette below. --}}
                @php($inks = array_values(array_filter($this->styleFields(), fn ($f) => isset($f['icon']) && ! isset($f['with']))))

                <div class="inline-flex gap-0.5 rounded-lg bg-gray-200/70 p-0.5 dark:bg-gray-800">
                    @foreach ($inks as $ink)
                        @php($set = trim((string) ($style[$ink['key']] ?? '')))
                        @php($paint = $set !== '' && app(\Bladewright\Support\Palette::class)->reads($set) ? app(\Bladewright\Support\Palette::class)->resolve($set) : 'transparent')

                        <button type="button" wire:click="openColour('{{ $ink['key'] }}')"
                                @class([
                                    'bw-pill bw-tip cursor-pointer rounded-md px-2 py-1 transition',
                                    'is-on' => $colouring === $ink['key'],
                                    'text-gray-500 dark:text-gray-400' => $colouring !== $ink['key'],
                                ])
                                data-tip="{{ $ink['label'] }}" aria-label="{{ $ink['label'] }}">
                            <span class="flex flex-col items-center gap-0.5">
                                @if ($ink['icon'] === 'text')
                                    <span class="text-[0.8125rem] leading-none font-semibold">A</span>
                                @else
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <path stroke-linejoin="round" d="M5 13 12 6l6 6-6 6z"/><path d="M19 15c0 1.7 1 2.6 1 3.6a1.6 1.6 0 0 1-3.2 0c0-1 1-1.9 1-3.6"/>
                                    </svg>
                                @endif
                                {{-- What it is set to, under the mark. --}}
                                <span class="h-1 w-4 rounded-full border border-gray-300/60 dark:border-gray-600/60" style="background:{{ $paint }}"></span>
                            </span>
                        </button>
                    @endforeach
                </div>

                {{-- **Pressed, not chosen from a list**, and standing where
                     the other presses do: three places to sit, shown as what
                     they do. --}}
                @foreach ($pills as $field)
                    <div class="inline-flex gap-0.5 rounded-lg bg-gray-200/70 p-0.5 dark:bg-gray-800" wire:key="pills-{{ $field['key'] }}">
                        @foreach ($field['choices'] as $value => $says)
                            @continue($value === '')

                            <button type="button" wire:click="choose('{{ $field['key'] }}', '{{ $value }}')"
                                    @class([
                                        'bw-pill bw-tip cursor-pointer rounded-md px-2.5 py-1.5 transition',
                                        'is-on' => ($style[$field['key']] ?? '') === $value,
                                        'text-gray-500 dark:text-gray-400' => ($style[$field['key']] ?? '') !== $value,
                                    ])
                                    data-tip="{{ $field['label'] }} — {{ $says }}" aria-label="{{ $field['label'] }} — {{ $says }}"
                                    aria-pressed="{{ ($style[$field['key']] ?? '') === $value ? 'true' : 'false' }}">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
                                    @if ($value === 'left')
                                        <path d="M4 6h16M4 12h10M4 18h13"/>
                                    @elseif ($value === 'center')
                                        <path d="M4 6h16M7 12h10M6 18h12"/>
                                    @else
                                        <path d="M4 6h16M10 12h10M7 18h13"/>
                                    @endif
                                </svg>
                            </button>
                        @endforeach
                    </div>
                @endforeach
            </div>

            @if ($colouring !== null && ! isset(collect($this->styleFields())->firstWhere('key', $colouring)['with']))
                @include('bladewright::admin.colour-picker')
            @endif

            {{-- **The label stands beside what it names**, not over it: the
                 card is read down a column of rows, not a stack of pairs. --}}
            @php($rowmates = [])
            @foreach ($this->styleFields() as $mate)
                @php(isset($mate['row']) && ! isset($mate['slider']) ? $rowmates[$mate['row']][] = $mate['key'] : null)
            @endforeach

            <div class="mt-3 space-y-2">
            @foreach ($this->styleFields() as $field)
                @continue($field['kind'] === 'switch' || ($field['pills'] ?? false) || (isset($field['icon']) && ! isset($field['with'])) || isset($field['with']))
                {{-- Asked only while a picture background is chosen. --}}
                @continue(isset($field['needsImage']) && ! str_contains((string) ($style[$field['needsImage']] ?? ''), 'url('))

                {{-- **Kin share a line** — width, height, min-height are one
                     thought. A slider needs the whole width, so it never
                     joins one. The first of the kin draws the row. --}}
                @if (isset($field['row']) && ! isset($field['slider']) && count($rowmates[$field['row']] ?? []) > 1)
                    @continue($rowmates[$field['row']][0] !== $field['key'])

                    <div class="flex gap-2" wire:key="style-row-{{ $field['row'] }}">
                        @foreach ($this->styleFields() as $kin)
                            @continue(($kin['row'] ?? null) !== $field['row'] || isset($kin['slider']))
                            <div class="min-w-0 flex-1">
                                <label class="block font-mono text-[0.75rem] text-gray-600 dark:text-gray-400">{{ $kin['label'] }}</label>
                                <input type="text" placeholder="{{ $kin['placeholder'] ?? '' }}"
                                       class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-2 py-1.5 text-center font-mono text-[0.75rem] shadow-xs transition focus:border-bw-accent focus:ring-2 focus:ring-bw-accent/20 focus:outline-none dark:border-gray-700 dark:bg-gray-950"
                                       wire:model.live.debounce.500ms="style.{{ $kin['key'] }}">
                            </div>
                        @endforeach
                    </div>

                    @continue
                @endif

                <div class="flex items-start gap-3" wire:key="style-{{ $field['key'] }}">
                    <label class="w-24 shrink-0 pt-2 font-mono text-[0.75rem] text-gray-600 dark:text-gray-400">{{ $field['label'] }}</label>

                    <div class="min-w-0 flex-1">

                    @if ($field['kind'] === 'choice')
                        <select class="w-full cursor-pointer rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-[0.8125rem] shadow-xs transition focus:border-bw-accent focus:ring-2 focus:ring-bw-accent/20 focus:outline-none dark:border-gray-700 dark:bg-gray-950"
                                wire:model.live="style.{{ $field['key'] }}">
                            @foreach ($field['choices'] as $value => $says)
                                <option value="{{ $value }}">{{ $says }}</option>
                            @endforeach
                        </select>

                    @elseif ($field['kind'] === 'colour')
                        {{-- **The site's colours, by name.** A name is looked
                             up when the page is rendered, so changing it in
                             the settings changes every page wearing it; a
                             colour typed in by hand belongs to this block
                             alone. --}}
                        @php($chosen = trim((string) ($style[$field['key']] ?? '')))
                        <div class="flex items-center gap-2">
                            <span class="h-8 w-8 shrink-0 rounded-lg border border-gray-200 dark:border-gray-700"
                                  style="background:{{ $chosen !== '' && app(\Bladewright\Support\Palette::class)->reads($chosen) ? app(\Bladewright\Support\Palette::class)->resolve($chosen) : 'transparent' }}"></span>
                            <select class="w-28 shrink-0 cursor-pointer rounded-lg border border-gray-300 bg-white px-2 py-2 text-[0.8125rem] shadow-xs transition focus:border-bw-accent focus:ring-2 focus:ring-bw-accent/20 focus:outline-none dark:border-gray-700 dark:bg-gray-950"
                                    wire:model.live="style.{{ $field['key'] }}">
                                <option value="">{{ __('nothing') }}</option>
                                @foreach ($this->palette() as $name => $value)
                                    <option value="{{ $name }}">{{ $name }}</option>
                                @endforeach
                                @if ($chosen !== '' && ! array_key_exists($chosen, $this->palette()))
                                    <option value="{{ $chosen }}">{{ __('written in') }}</option>
                                @endif
                            </select>
                            <input type="text" placeholder="#3538cd"
                                   class="min-w-0 flex-1 rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 font-mono text-[0.75rem] shadow-xs transition focus:border-bw-accent focus:ring-2 focus:ring-bw-accent/20 focus:outline-none dark:border-gray-700 dark:bg-gray-950"
                                   wire:model.live.debounce.500ms="style.{{ $field['key'] }}">
                            @if (trim((string) ($style[$field['key']] ?? '')) !== '')
                                <button type="button" wire:click="$set('style.{{ $field['key'] }}', '')"
                                        class="shrink-0 cursor-pointer rounded-md p-1 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800 dark:hover:text-gray-200"
                                        aria-label="{{ __('Clear') }}">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/></svg>
                                </button>
                            @endif
                        </div>

                    @elseif ($field['kind'] === 'sides-set')
                        {{-- **Which edges are drawn**, shown as the box they
                             are drawn on. Nothing chosen is all the way
                             round, which is what a border means unsaid. --}}
                        <div class="rounded-lg border border-dashed border-gray-300 bg-gray-50 p-2 dark:border-gray-700 dark:bg-gray-950/50">
                            <div class="mx-auto grid w-32 grid-cols-3 grid-rows-3 items-center justify-items-center gap-1">
                                <span></span>
                                <button type="button" wire:click="toggleSide('{{ $field['key'] }}', 'top')"
                                        @class(['h-2 w-full cursor-pointer rounded-full transition', 'bg-bw-accent' => $this->sideIsOn($field['key'], 'top'), 'bg-gray-200 dark:bg-gray-700' => ! $this->sideIsOn($field['key'], 'top')])
                                        aria-label="{{ __('Above') }}" aria-pressed="{{ $this->sideIsOn($field['key'], 'top') ? 'true' : 'false' }}"></button>
                                <span></span>

                                <button type="button" wire:click="toggleSide('{{ $field['key'] }}', 'left')"
                                        @class(['h-8 w-2 cursor-pointer rounded-full transition', 'bg-bw-accent' => $this->sideIsOn($field['key'], 'left'), 'bg-gray-200 dark:bg-gray-700' => ! $this->sideIsOn($field['key'], 'left')])
                                        aria-label="{{ __('Left') }}" aria-pressed="{{ $this->sideIsOn($field['key'], 'left') ? 'true' : 'false' }}"></button>
                                <span class="font-mono text-[0.625rem] text-gray-400">{{ __('the block') }}</span>
                                <button type="button" wire:click="toggleSide('{{ $field['key'] }}', 'right')"
                                        @class(['h-8 w-2 cursor-pointer rounded-full transition', 'bg-bw-accent' => $this->sideIsOn($field['key'], 'right'), 'bg-gray-200 dark:bg-gray-700' => ! $this->sideIsOn($field['key'], 'right')])
                                        aria-label="{{ __('Right') }}" aria-pressed="{{ $this->sideIsOn($field['key'], 'right') ? 'true' : 'false' }}"></button>

                                <span></span>
                                <button type="button" wire:click="toggleSide('{{ $field['key'] }}', 'bottom')"
                                        @class(['h-2 w-full cursor-pointer rounded-full transition', 'bg-bw-accent' => $this->sideIsOn($field['key'], 'bottom'), 'bg-gray-200 dark:bg-gray-700' => ! $this->sideIsOn($field['key'], 'bottom')])
                                        aria-label="{{ __('Below') }}" aria-pressed="{{ $this->sideIsOn($field['key'], 'bottom') ? 'true' : 'false' }}"></button>
                                <span></span>
                            </div>
                        </div>

                    @elseif (($field['sides'] ?? false) && $this->paddingFitsTheBox())
                        {{-- **The four sides, in a box** — the way a browser's
                             own inspector shows them. What is stored is still
                             one value; this is how it is read and written. --}}
                        <div class="rounded-lg border border-dashed border-gray-300 bg-gray-50 p-1.5 dark:border-gray-700 dark:bg-gray-950/50">
                            <div class="grid grid-cols-3 items-center gap-1">
                                <span></span>
                                <input type="text" placeholder="0" aria-label="{{ __('Above') }}"
                                       class="w-full rounded-md border border-gray-300 bg-white px-1 py-1 text-center font-mono text-[0.6875rem] shadow-xs transition focus:border-bw-accent focus:ring-2 focus:ring-bw-accent/20 focus:outline-none dark:border-gray-700 dark:bg-gray-900"
                                       wire:model.live.debounce.500ms="sides.top">
                                <span></span>

                                <input type="text" placeholder="0" aria-label="{{ __('Left') }}"
                                       class="w-full rounded-md border border-gray-300 bg-white px-2 py-1 text-center font-mono text-[0.75rem] shadow-xs transition focus:border-bw-accent focus:ring-2 focus:ring-bw-accent/20 focus:outline-none dark:border-gray-700 dark:bg-gray-900"
                                       wire:model.live.debounce.500ms="sides.left">
                                <span class="rounded-md border border-gray-200 bg-white py-1.5 text-center font-mono text-[0.625rem] text-gray-400 dark:border-gray-700 dark:bg-gray-900">{{ __('the block') }}</span>
                                <input type="text" placeholder="0" aria-label="{{ __('Right') }}"
                                       class="w-full rounded-md border border-gray-300 bg-white px-2 py-1 text-center font-mono text-[0.75rem] shadow-xs transition focus:border-bw-accent focus:ring-2 focus:ring-bw-accent/20 focus:outline-none dark:border-gray-700 dark:bg-gray-900"
                                       wire:model.live.debounce.500ms="sides.right">

                                <span></span>
                                <input type="text" placeholder="0" aria-label="{{ __('Below') }}"
                                       class="w-full rounded-md border border-gray-300 bg-white px-2 py-1 text-center font-mono text-[0.75rem] shadow-xs transition focus:border-bw-accent focus:ring-2 focus:ring-bw-accent/20 focus:outline-none dark:border-gray-700 dark:bg-gray-900"
                                       wire:model.live.debounce.500ms="sides.bottom">
                                <span></span>
                            </div>

                            @if (trim((string) ($style[$field['key']] ?? '')) !== '')
                                <div class="mt-1 text-center font-mono text-[0.625rem] text-gray-400">{{ $style[$field['key']] }}</div>
                            @endif
                        </div>

                    @elseif (isset($field['slider']))
                        {{-- **Felt out, or typed.** The slider moves the box
                             and the box moves the slider; what is stored is
                             whatever the box says. --}}
                        @php($carried = collect($this->styleFields())->firstWhere('with', $field['key']))

                        <div class="flex items-center gap-3">
                            <input type="range" class="min-w-0 flex-1 accent-bw-accent"
                                   min="0" max="{{ $field['slider']['max'] }}" step="{{ $field['slider']['step'] }}"
                                   aria-label="{{ $field['label'] }}"
                                   wire:model.live="sliders.{{ $field['key'] }}">
                            {{-- The bar is the tool; the box only says where
                                 it stands, so it takes the room it needs. --}}
                            <input type="text" placeholder="{{ $field['placeholder'] ?? '' }}"
                                   class="w-20 shrink-0 rounded-lg border border-gray-300 bg-white px-2 py-1.5 text-center font-mono text-[0.75rem] shadow-xs transition focus:border-bw-accent focus:ring-2 focus:ring-bw-accent/20 focus:outline-none dark:border-gray-700 dark:bg-gray-950"
                                   wire:model.live.debounce.500ms="style.{{ $field['key'] }}">

                            @if ($carried)
                                {{-- **A colour is read beside what it paints**,
                                     so the border's own stands in its row. --}}
                                @php($set = trim((string) ($style[$carried['key']] ?? '')))
                                @php($paint = $set !== '' && app(\Bladewright\Support\Palette::class)->reads($set) ? app(\Bladewright\Support\Palette::class)->resolve($set) : 'transparent')

                                <button type="button" wire:click="openColour('{{ $carried['key'] }}')"
                                        @class([
                                            'bw-pill bw-tip shrink-0 cursor-pointer rounded-lg bg-gray-200/70 px-2 py-1 transition dark:bg-gray-800',
                                            'is-on' => $colouring === $carried['key'],
                                            'text-gray-500 dark:text-gray-400' => $colouring !== $carried['key'],
                                        ])
                                        data-tip="{{ $carried['label'] }}" aria-label="{{ $carried['label'] }}">
                                    <span class="flex flex-col items-center gap-0.5">
                                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                            <rect x="4" y="6" width="16" height="12" rx="2"/>
                                        </svg>
                                        <span class="h-1 w-4 rounded-full border border-gray-300/60 dark:border-gray-600/60" style="background:{{ $paint }}"></span>
                                    </span>
                                </button>
                            @endif
                        </div>

                        @if ($carried && $colouring === $carried['key'])
                            @include('bladewright::admin.colour-picker')
                        @endif

                    @else
                        <input type="text" placeholder="{{ $field['placeholder'] ?? '' }}"
                               class="w-full rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 font-mono text-[0.75rem] shadow-xs transition focus:border-bw-accent focus:ring-2 focus:ring-bw-accent/20 focus:outline-none dark:border-gray-700 dark:bg-gray-950"
                               wire:model.live.debounce.500ms="style.{{ $field['key'] }}">
                    @endif
                    </div>
                </div>
            @endforeach
            </div>
            </div>
        </div>

@if ($stylePicking !== null)
    {{-- The same window the media fields open — here for a background. --}}
    <div data-bw-modal="pick-style-media" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50" data-bw-modal-close wire:click="$set('stylePicking', null)"></div>

        <div class="relative z-10 flex max-h-[85vh] w-full max-w-3xl flex-col overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xl dark:border-gray-800 dark:bg-gray-900">
            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3 dark:border-gray-800">
                <span class="text-sm font-semibold">{{ __('Choose a picture') }}</span>
                <button type="button" data-bw-modal-close wire:click="$set('stylePicking', null)"
                        class="cursor-pointer rounded-md p-1 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200" aria-label="{{ __('Cancel') }}">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/>
                    </svg>
                </button>
            </div>

            <div class="overflow-y-auto p-5">
                <livewire:bladewright::media-library :picking="true" accept="image" wire:key="style-picker-{{ $stylePicking }}" />
            </div>
        </div>
    </div>
@endif
