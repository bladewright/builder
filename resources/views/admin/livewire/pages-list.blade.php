<?php

use Livewire\Component;
use Bladewright\Access\Abilities;
use Bladewright\Blocks\SitePages;
use Bladewright\Models\Layout;
use Bladewright\Models\Page;
use Bladewright\Support\Toasts;

/*
 * The pages, listed. **The row is the page** — press anywhere on it and the
 * editor opens. Making one is a moment, not a place: a small window asks for
 * the name, the URL and the frame, and leaves.
 */
new class extends Component
{
    use Toasts;

    public string $search = '';

    public bool $creating = false;

    public string $newName = '';

    public string $newUrl = '';

    public string $newLayout = '';

    public function startCreating(): void
    {
        $this->authorize(Abilities::gate(Abilities::EDIT_CONTENT));

        $this->creating = true;
        // The one frame most sites have is the one most pages want.
        $this->newLayout = Layout::query()->orderBy('name')->value('name') ?? '';
    }

    public function cancelCreating(): void
    {
        $this->creating = false;
        $this->newName = '';
        $this->newUrl = '';
    }

    public function create(): void
    {
        $this->authorize(Abilities::gate(Abilities::EDIT_CONTENT));

        $this->validate([
            'newName' => ['required', 'max:100'],
        ], attributes: ['newName' => __('Page name')]);

        // **The URL says so itself**, under the box it is about.
        if ($problem = app(SitePages::class)->urlProblem($this->newUrl)) {
            $this->addError('newUrl', $problem);

            return;
        }

        try {
            $page = app(SitePages::class)->create($this->newName, $this->newUrl, $this->newLayout ?: null);
        } catch (\InvalidArgumentException $e) {
            $this->addError('newName', $e->getMessage());

            return;
        }

        $this->redirect(route('bladewright.admin.pages.edit', $page), navigate: false);
    }

    public function pages()
    {
        return Page::query()
            ->withCount('children')
            ->when($this->search !== '', fn ($q) => $q
                ->where(fn ($w) => $w
                    ->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('url', 'like', '%'.$this->search.'%')))
            // **Most recently edited, always.**
            ->orderByDesc('updated_at')
            ->get();
    }
};
?>

<div class="mt-4 rounded-xl border border-gray-200 bg-white p-6 first:mt-0 dark:border-gray-800 dark:bg-gray-900">
    <div class="flex flex-wrap items-center gap-2">
        <input
            type="search"
            class="min-w-56 flex-1 rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-[0.8125rem] shadow-xs transition focus:border-bw-accent focus:ring-2 focus:ring-bw-accent/20 focus:outline-none dark:border-gray-700 dark:bg-gray-950"
            wire:model.live.debounce.300ms="search"
            placeholder="{{ __('Filter by name or URL') }}"
        >

        <span class="flex-1"></span>

        @can(\Bladewright\Access\Abilities::gate(\Bladewright\Access\Abilities::EDIT_CONTENT))
            <button type="button" class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg bg-linear-to-tl from-bw-accent-2 to-bw-accent px-3 py-1.5 text-[0.8125rem] font-semibold text-white shadow-xs transition hover:brightness-110 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-bw-accent" wire:click="startCreating">
                {{ __('Create a page') }}
            </button>
        @endcan
    </div>

    @if ($creating)
        <div data-bw-modal="new-page" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/50" data-bw-modal-close wire:click="cancelCreating"></div>

            <div class="relative z-10 w-full max-w-md rounded-xl border border-gray-200 bg-white p-5 shadow-xl dark:border-gray-800 dark:bg-gray-900">
                <h2 class="m-0 text-sm font-semibold">{{ __('Create a page') }}</h2>

                <label class="mt-4 block text-[0.8125rem] font-medium text-gray-600 dark:text-gray-400">{{ __('Page name') }}</label>
                <input type="text" autofocus
                       class="mt-1.5 w-full rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-[0.8125rem] shadow-xs transition focus:border-bw-accent focus:ring-2 focus:ring-bw-accent/20 focus:outline-none dark:border-gray-700 dark:bg-gray-950"
                       wire:model="newName" wire:keydown.enter="create" placeholder="{{ __('About') }}">
                @error('newName') <div class="mt-1 text-[0.8125rem] text-red-600 dark:text-red-400">{{ $message }}</div> @enderror

                <label class="mt-4 block text-[0.8125rem] font-medium text-gray-600 dark:text-gray-400">{{ __('URL') }}</label>
                {{-- **The slash is the site's, not something to type.** --}}
                <div class="mt-1.5 flex items-stretch rounded-lg border bg-white shadow-xs transition focus-within:ring-2 dark:bg-gray-950 {{ $errors->has('newUrl')
                    ? 'border-red-400 focus-within:border-red-400 focus-within:ring-red-400/20 dark:border-red-800'
                    : 'border-gray-300 focus-within:border-bw-accent focus-within:ring-bw-accent/20 dark:border-gray-700' }}">
                    <span class="flex select-none items-center border-r border-gray-200 px-3 font-mono text-sm text-gray-500 dark:border-gray-800 dark:text-gray-400">/</span>
                    <input type="text"
                           class="w-full rounded-r-lg border-0 bg-transparent px-3 py-2 font-mono text-sm focus:outline-none"
                           wire:model="newUrl" wire:keydown.enter="create" placeholder="about">
                </div>
                @error('newUrl')
                    <div class="mt-1 text-[0.8125rem] text-red-600 dark:text-red-400">{{ $message }}</div>
                @else
                    <div class="mt-1 text-[0.8125rem] text-gray-500 dark:text-gray-400">{{ __('Leave it empty for the front page.') }}</div>
                @enderror

                <label class="mt-4 block text-[0.8125rem] font-medium text-gray-600 dark:text-gray-400">{{ __('Layout') }}</label>
                <select class="mt-1.5 w-full rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-[0.8125rem] shadow-xs transition focus:border-bw-accent focus:ring-2 focus:ring-bw-accent/20 focus:outline-none dark:border-gray-700 dark:bg-gray-950"
                        wire:model="newLayout">
                    <option value="">{{ __('(none — the page stands bare)') }}</option>
                    @foreach (\Bladewright\Models\Layout::query()->orderBy('name')->pluck('name') as $layoutName)
                        <option value="{{ $layoutName }}">{{ $layoutName }}</option>
                    @endforeach
                </select>

                <div class="mt-5 flex items-center justify-end gap-2">
                    <button type="button" data-bw-modal-close wire:click="cancelCreating"
                            class="inline-flex cursor-pointer items-center gap-1 rounded-md border border-gray-300 bg-white px-2.5 py-1.5 text-[0.8125rem] font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">{{ __('Cancel') }}</button>
                    <button type="button" wire:click="create"
                            class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg bg-linear-to-tl from-bw-accent-2 to-bw-accent px-3 py-1.5 text-[0.8125rem] font-semibold text-white shadow-xs transition hover:brightness-110 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-bw-accent">{{ __('Create') }}</button>
                </div>
            </div>
        </div>
    @endif

    @php($pages = $this->pages())

    @if ($pages->isEmpty())
        <p class="px-4 py-12 text-center text-gray-500 dark:text-gray-400">
            {{ $search === '' ? __('No pages yet.') : __('Nothing matches.') }}
        </p>
    @else
        <div class="-mx-6 mt-4 overflow-x-auto">
        <table class="w-full border-collapse text-sm">
            <thead>
                <tr>
                    <th class="border-b border-gray-200 px-6 pb-2 text-left text-xs font-medium text-gray-500 dark:border-gray-800 dark:text-gray-400">{{ __('Pages') }}</th>
                    <th class="border-b border-gray-200 px-6 pb-2 text-left text-xs font-medium text-gray-500 dark:border-gray-800 dark:text-gray-400">{{ __('State') }}</th>
                    <th class="border-b border-gray-200 px-6 pb-2 text-left text-xs font-medium text-gray-500 dark:border-gray-800 dark:text-gray-400">{{ __('Components') }}</th>
                    <th class="border-b border-gray-200 px-6 pb-2 text-left text-xs font-medium text-gray-500 dark:border-gray-800 dark:text-gray-400">{{ __('Updated') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($pages as $page)
                    <tr wire:key="page-{{ $page->uuid }}" data-bw-row tabindex="0"
                        data-bw-row-href="{{ route('bladewright.admin.pages.edit', $page) }}"
                        class="cursor-pointer transition hover:bg-gray-50 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-bw-accent dark:hover:bg-gray-800/50">
                        <td class="border-b border-gray-100 px-6 py-3 align-middle dark:border-gray-800">
                            <div class="font-semibold">{{ $page->name }}</div>
                            <div class="font-mono text-[0.8125rem] text-gray-500 dark:text-gray-400">/{{ $page->url }}</div>
                        </td>
                        <td class="border-b border-gray-100 px-6 py-3 align-middle dark:border-gray-800">
                            @if ($page->is_published)
                                <span class="mr-1 inline-block rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold whitespace-nowrap text-green-700 dark:bg-green-900/40 dark:text-green-300">{{ __('Published') }}</span>
                            @else
                                <span class="mr-1 inline-block rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold whitespace-nowrap text-gray-600 dark:bg-gray-800 dark:text-gray-300">{{ __('Not published') }}</span>
                            @endif
                        </td>
                        <td class="border-b border-gray-100 px-6 py-3 align-middle text-[0.8125rem] text-gray-500 dark:border-gray-800 dark:text-gray-400">
                            {{ $page->children_count }}
                        </td>
                        <td class="border-b border-gray-100 px-6 py-3 align-middle font-mono text-[0.8125rem] text-gray-500 dark:border-gray-800 dark:text-gray-400">
                            {{ optional($page->updated_at)->format('Y-m-d H:i') ?? '—' }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    @endif
</div>
