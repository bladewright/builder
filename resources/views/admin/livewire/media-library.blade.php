<?php

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Bladewright\Access\Abilities;
use Bladewright\Media\MediaLibrary;
use Bladewright\Support\Toasts;

new class extends Component
{
    use Toasts;

    use WithFileUploads;
    use WithPagination;

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $uploads = [];

    /** The chosen file's path. **Not an id** — storage is the truth. */
    public ?string $selected = null;

    /**
     * Is the drawer on the right open?
     *
     * **The chosen file is remembered even when it closes.** Clear it and the
     * contents go first, halfway through the closing, leaving an empty board
     * sliding away.
     */
    public bool $drawerOpen = false;

    /** The folder that is open. Empty is the top. */
    public string $folder = '';

    /** How many tiles at once. **A library grows**, and a screen does not. */
    public int $perPage = 48;

    public string $newFolder = '';

    /** Embedded as a screen only for choosing (from a block's image field). */
    public bool $picking = false;

    /** What kind of file is being picked for — '' offers anything. */
    public string $accept = '';

    /** What this server can really accept. PHP's own limit can be lower. */
    public function maxBytes(): int
    {
        return app(MediaLibrary::class)->maxBytes();
    }

    public function maxLabel(): string
    {
        return number_format($this->maxBytes() / 1024 / 1024, 1).'MB';
    }

    public function updatedUploads(): void
    {
        $this->authorize(Abilities::gate(Abilities::EDIT_CONTENT));

        $library = app(MediaLibrary::class);

        $stored = 0;

        foreach ($this->uploads as $upload) {
            try {
                // TemporaryUploadedFile extends UploadedFile, so it can be passed straight through.
                $library->store($upload, $this->folder);
                $stored++;
            } catch (\Throwable $e) {
                $this->toastError($e->getMessage());
            }
        }

        $this->uploads = [];
        $this->resetPage();

        if ($stored > 0) {
            $this->toast(__('Uploaded :count file(s).', ['count' => $stored]));
        }
    }

    /**
     * Delete it.
     *
     * **It really goes.** An older revision referring to it loses its image
     * (a state that merely hides it from the list would need a database, and
     * storage is the truth here, so there is none).
     */
    public function remove(string $path): void
    {
        $this->authorize(Abilities::gate(Abilities::EDIT_CONTENT));

        app(MediaLibrary::class)->delete($path);

        $this->toast(__('Deleted. It disappears from the pages that used it.'));
        $this->selected = null;
        $this->drawerOpen = false;
    }

    /**
     * The pages and parts using this file.
     *
     * **Visible before deleting.** Media is the one thing revisions cannot
     * restore, so nobody should delete without knowing where it is used.
     */
    public function usedBy()
    {
        return $this->selected === null
            ? collect()
            : app(\Bladewright\Media\MediaUsage::class)->using($this->selected);
    }

    /** Open a file (the drawer on the right). */
    public function select(string $path): void
    {
        $this->selected = $path;
        $this->drawerOpen = true;
    }

    public function closeDrawer(): void
    {
        $this->drawerOpen = false;
    }

    public function open(string $folder): void
    {
        $this->folder = trim($folder, '/');
        $this->selected = null;
        $this->drawerOpen = false;

        // **Page 3 of the folder you just left means nothing here.**
        $this->resetPage();
    }

    /** Up one. */
    public function up(): void
    {
        $this->folder = str_contains($this->folder, '/')
            ? substr($this->folder, 0, strrpos($this->folder, '/'))
            : '';

        $this->selected = null;
        $this->drawerOpen = false;
        $this->resetPage();
    }

    public function createFolder(): void
    {
        $this->authorize(Abilities::gate(Abilities::EDIT_CONTENT));

        try {
            app(MediaLibrary::class)->makeFolder($this->folder, $this->newFolder);
            $this->newFolder = '';
            $this->dispatch('bw-close-modal', name: 'new-folder');
            $this->toast(__('Folder created.'));
        } catch (\Throwable $e) {
            $this->toastError($e->getMessage());
        }
    }

    /**
     * Delete the folder you are in and go up one.
     *
     * **Not while anything is in it.** The images on the pages referring to
     * them would vanish quietly, so it is emptied first.
     */
    public function removeFolder(string $folder): void
    {
        $this->authorize(Abilities::gate(Abilities::EDIT_CONTENT));

        try {
            app(MediaLibrary::class)->deleteFolder($folder);
            $this->up();
            $this->toast(__('Folder deleted.'));
        } catch (\Throwable $e) {
            $this->toastError($e->getMessage());
        }
    }

    /** @return array<int, string> where you are, for the breadcrumb */
    public function trail(): array
    {
        return $this->folder === '' ? [] : explode('/', $this->folder);
    }

    /** @return array<int, string> */
    public function folders(): array
    {
        return app(MediaLibrary::class)->folders($this->folder);
    }

    public function choose(string $path): void
    {
        $this->dispatch('bw-media-selected', path: $path);
    }

    /**
     * What is in this folder, a page at a time.
     *
     * **A library grows.** Five hundred tiles and five hundred images in one
     * screen is a slow page and a lot of bytes, so it is paged.
     *
     * The listing itself still walks the folder (a file lives inside a shelf
     * named after its contents, so there is no shallow listing to ask for).
     * On a disk where that is expensive — S3 counts every call — the answer is
     * a cache, not a smaller page.
     */
    public function media(): \Illuminate\Pagination\LengthAwarePaginator
    {
        $all = app(MediaLibrary::class)->all($this->folder);

        // A picker asked for pictures offers pictures. The folders still
        // show, so the way through them stays open.
        if ($this->accept !== '') {
            $all = $all->filter(fn ($item) => $item->isKind($this->accept))->values();
        }

        $page = max(1, (int) $this->getPage());

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $all->forPage($page, $this->perPage)->values(),
            $all->count(),
            $this->perPage,
            $page,
        );
    }

    public function selectedMedia(): ?\Bladewright\Media\MediaFile
    {
        return $this->selected === null ? null : app(MediaLibrary::class)->find($this->selected);
    }
};
?>

@php($canEdit = \Illuminate\Support\Facades\Gate::allows(\Bladewright\Access\Abilities::gate(\Bladewright\Access\Abilities::EDIT_CONTENT)))
@php($folders = $this->folders())
@php($items = $this->media())
@php($current = $picking ? null : $this->selectedMedia())

<div>
    {{-- Inside a picker the card sits inside a card already, so the frame
         and its padding fall away: just the list. --}}
    <div @class(['mt-4 rounded-xl border border-gray-200 bg-white p-6 first:mt-0 dark:border-gray-800 dark:bg-gray-900' => ! $picking])>
        {{-- **Picking is picking.** Reached from a block, the library is a
             list to choose from — making folders and bringing files in is
             the Media screen's own business, and a picker that can do
             everything is a second Media screen in a drawer. --}}
        <div @class(['flex flex-wrap items-center justify-between gap-4', 'hidden' => $picking])>
            {{-- **The heading is already above this box**, so saying "Media" twice
                 bought nothing. What is left is the part that is not in it. --}}
            <p class="m-0 max-w-xl text-[0.8125rem] text-gray-500 dark:text-gray-400">
                {{ __('Uploading the same image again adds nothing. Replacing one leaves older revisions showing what they showed.') }}
            </p>

            <div class="flex flex-wrap items-center gap-2">
                @if ($canEdit)
                    <button type="button" data-bw-modal-open="new-folder"
                            class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 7a1 1 0 0 1 1-1h5l2 2h8a1 1 0 0 1 1 1v9a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V7Z"/>
                            <path stroke-linecap="round" d="M12 11v5M9.5 13.5h5"/>
                        </svg>
                        {{ __('New folder') }}
                    </button>
                @endif

                <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg bg-linear-to-tl from-bw-accent-2 to-bw-accent px-3 py-1.5 text-[0.8125rem] font-semibold text-white shadow-xs transition hover:brightness-110 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-bw-accent">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m0 0L8 8m4-4 4 4M4 17v2a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-2"/>
                    </svg>
                    {{ __('Upload') }}
                    {{-- **A file that is too big is stopped before it is sent.** Over PHP's limit
                         the whole request is thrown away and the screen says nothing at all. --}}
                    <input type="file" multiple class="hidden" wire:model="uploads"
                           data-bw-max-bytes="{{ $this->maxBytes() }}"
                           data-bw-max-label="{{ $this->maxLabel() }}">
                </label>
            </div>
        </div>

        <div wire:loading wire:target="uploads" class="mt-1 text-[0.8125rem] text-gray-500 dark:text-gray-400">{{ __('Uploading…') }}</div>

        {{-- Say what is accepted up front. Kinder than refusing afterwards, and fewer questions. --}}
        <p @class(['mt-1 text-[0.8125rem] text-gray-500 dark:text-gray-400', 'hidden' => $picking])>
            {{ __('Up to :max per file.', ['max' => $this->maxLabel()]) }}
            @if ($this->maxBytes() < (int) config('bladewright.media.max_size'))
                {{ __('(configured as :configured, but this server\'s PHP stops at :max)', [
                    'configured' => number_format((int) config('bladewright.media.max_size') / 1024 / 1024, 1).'MB',
                    'max' => $this->maxLabel(),
                ]) }}
            @endif
        </p>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-2">
            {{-- Where you are. **Press it to go back.** --}}
            <nav class="flex flex-wrap items-center gap-1 text-[0.8125rem] text-gray-500 dark:text-gray-400">
                <button type="button" data-bw-drawer-close
                        class="cursor-pointer rounded px-1.5 py-0.5 hover:bg-gray-100 dark:hover:bg-gray-800 {{ $folder === '' ? 'font-semibold text-gray-900 dark:text-gray-100' : '' }}"
                        wire:click="open('')">{{ __('All') }}</button>
                @php($walked = '')
                @foreach ($this->trail() as $part)
                    @php($walked = trim($walked.'/'.$part, '/'))
                    <span class="text-gray-300 dark:text-gray-600">/</span>
                    <button type="button" data-bw-drawer-close
                            class="cursor-pointer rounded px-1.5 py-0.5 hover:bg-gray-100 dark:hover:bg-gray-800 {{ $walked === $folder ? 'font-semibold text-gray-900 dark:text-gray-100' : '' }}"
                            wire:click="open('{{ $walked }}')">{{ $part }}</button>
                @endforeach
            </nav>

            {{-- A folder is deleted from inside it. **With anything still in
                 it, it is refused** — and never from a picker, which only
                 chooses. --}}
            @if ($canEdit && ! $picking && $folder !== '')
                <button type="button"
                        class="inline-flex cursor-pointer items-center gap-1 rounded-md border border-gray-300 bg-white px-2.5 py-1 text-[0.8125rem] font-medium text-red-600 transition hover:bg-red-50 dark:border-gray-700 dark:bg-gray-900 dark:hover:bg-red-950"
                        wire:click="removeFolder('{{ $folder }}')">
                    {{ __('Delete this folder') }}
                </button>
            @endif
        </div>

        @if ($folders === [] && $items->isEmpty())
            <p class="px-4 py-12 text-center text-gray-500 dark:text-gray-400">
                {{ $folder === '' ? __('Nothing here yet.') : __('This folder is empty.') }}
            </p>
        @else
            {{-- **Folders and files in one list.** Separate boxes mean looking twice
                 to find where anything is. --}}
            <div class="mt-5 grid grid-cols-[repeat(auto-fill,minmax(8rem,1fr))] gap-4">
                @foreach ($folders as $name)
                    <button type="button" data-bw-drawer-close
                            class="cursor-pointer overflow-hidden rounded-lg border border-transparent p-2 text-center transition hover:bg-gray-100 dark:hover:bg-gray-800"
                            wire:key="folder-{{ md5($name) }}"
                            wire:click="open('{{ trim($folder.'/'.$name, '/') }}')">
                        <span class="flex h-20 items-center justify-center text-gray-400">
                            <svg class="h-10 w-10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 7a1 1 0 0 1 1-1h5l2 2h8a1 1 0 0 1 1 1v9a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V7Z"/>
                            </svg>
                        </span>
                        <span class="mt-1.5 block overflow-hidden text-[0.6875rem] text-ellipsis whitespace-nowrap text-gray-600 dark:text-gray-300">{{ $name }}</span>
                    </button>
                @endforeach

                @foreach ($items as $item)
                    <button type="button"
                            class="cursor-pointer overflow-hidden rounded-lg border p-2 text-center transition {{ ! $picking && $drawerOpen && $selected === $item->path ? 'border-bw-accent bg-bw-accent/5' : 'border-transparent hover:bg-gray-100 dark:hover:bg-gray-800' }}"
                            wire:key="media-{{ md5($item->path) }}"
                            @if ($picking) wire:click="choose('{{ $item->path }}')" @else data-bw-drawer-open wire:click="select('{{ $item->path }}')" @endif>
                        <span class="flex h-20 items-center justify-center">
                            @if ($item->isImage())
                                <img src="{{ $item->url() }}" alt="{{ $item->name }}" loading="lazy" class="max-h-20 max-w-full object-contain">
                            @elseif ($item->isVideo())
                                <svg class="h-9 w-9 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
                                    <rect x="3" y="5" width="18" height="14" rx="2"/><path stroke-linejoin="round" d="m10 9 5 3-5 3V9Z"/>
                                </svg>
                            @elseif ($item->isPdf())
                                <svg class="h-9 w-9 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
                                    <path stroke-linejoin="round" d="M14 3H7a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1V7l-4-4Z"/><path stroke-linejoin="round" d="M14 3v4h4"/>
                                </svg>
                            @else
                                <span class="text-xs font-bold text-gray-500 uppercase dark:text-gray-400">{{ $item->extension() }}</span>
                            @endif
                        </span>
                        <span class="mt-1.5 block overflow-hidden text-[0.6875rem] text-ellipsis whitespace-nowrap text-gray-500 dark:text-gray-400">{{ $item->name }}</span>
                    </button>
                @endforeach
            </div>

            @if ($items->hasPages())
                {{-- **Our own pager.** Livewire's pagination views live in vendor,
                     which the CSS build never scans, so they would come out bare. --}}
                <div class="mt-5 flex items-center justify-between gap-3 border-t border-gray-100 pt-4 text-[0.8125rem] text-gray-500 dark:border-gray-800 dark:text-gray-400">
                    <span>{{ __(':from–:to of :total', [
                        'from' => $items->firstItem(),
                        'to' => $items->lastItem(),
                        'total' => $items->total(),
                    ]) }}</span>

                    <div class="flex items-center gap-2">
                        <button type="button" wire:click="previousPage" @disabled($items->onFirstPage())
                                class="cursor-pointer rounded-md border border-gray-300 bg-white px-2.5 py-1 font-medium text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">
                            {{ __('Back') }}
                        </button>
                        <button type="button" wire:click="nextPage" @disabled(! $items->hasMorePages())
                                class="cursor-pointer rounded-md border border-gray-300 bg-white px-2.5 py-1 font-medium text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">
                            {{ __('More') }}
                        </button>
                    </div>
                </div>
            @endif
        @endif
    </div>

    @if (! $picking)
        {{-- A drawer from the right. **The list stays visible**, so the next one can be chosen with it open. --}}
        <aside data-bw-drawer
               class="fixed top-0 right-0 z-40 flex h-screen w-80 max-w-full flex-col border-l border-gray-200 bg-white shadow-xl transition-transform duration-200 dark:border-gray-800 dark:bg-gray-900 {{ $drawerOpen ? '' : 'translate-x-full' }}"
               aria-hidden="{{ $drawerOpen ? 'false' : 'true' }}">
            @if ($current)
                <div class="flex items-start justify-between gap-3 border-b border-gray-100 px-4 py-3 dark:border-gray-800">
                    <h2 class="min-w-0 flex-1 truncate text-sm font-semibold">{{ $current->name }}</h2>
                    <button type="button" data-bw-drawer-close wire:click="closeDrawer"
                            class="bw-tip shrink-0 cursor-pointer rounded-md p-1 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800 dark:hover:text-gray-200"
                            data-tip="{{ __('Close') }}" aria-label="{{ __('Close') }}">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/>
                        </svg>
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto px-4 py-4">
                    <div class="flex min-h-40 items-center justify-center rounded-lg bg-gray-50 p-3 dark:bg-gray-950">
                        @if ($current->isImage())
                            <img src="{{ $current->url() }}" alt="" class="max-h-56 max-w-full object-contain">
                        @elseif ($current->isVideo())
                            {{-- A video plays right there. Nothing can be chosen if it has to be opened to be seen. --}}
                            <video src="{{ $current->url() }}" controls preload="metadata" class="max-h-56 max-w-full"></video>
                        @else
                            <span class="text-2xl font-bold text-gray-400 uppercase">{{ $current->extension() }}</span>
                        @endif
                    </div>

                    <table class="mt-4 w-full text-[0.8125rem]">
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            <tr>
                                <td class="py-2 text-gray-500 dark:text-gray-400">{{ __('Type') }}</td>
                                <td class="py-2 text-right">{{ $current->extension() }}</td>
                            </tr>
                            <tr>
                                <td class="py-2 text-gray-500 dark:text-gray-400">{{ __('Size') }}</td>
                                <td class="py-2 text-right">{{ $current->sizeLabel() }}</td>
                            </tr>
                            <tr>
                                <td class="py-2 text-gray-500 dark:text-gray-400">{{ __('Where') }}</td>
                                <td class="py-2 text-right">{{ $folder === '' ? __('All') : $folder }}</td>
                            </tr>
                            <tr>
                                <td class="py-2 text-gray-500 dark:text-gray-400">{{ __('URL') }}</td>
                                <td class="max-w-0 truncate py-2 text-right">
                                    <a href="{{ $current->url() }}" target="_blank" rel="noopener"
                                       class="text-bw-accent hover:underline" title="{{ $current->url() }}">{{ $current->url() }}</a>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    {{-- **This is what gets used most.** Not select-then-copy: press it and it is copied. --}}
                    <button type="button" data-bw-copy="{{ $current->url() }}"
                            class="mt-4 inline-flex w-full cursor-pointer items-center justify-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-[0.8125rem] font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                            <rect x="9" y="9" width="11" height="11" rx="2"/><path stroke-linecap="round" d="M5 15V5a1 1 0 0 1 1-1h9"/>
                        </svg>
                        {{ __('Copy URL') }}
                    </button>
                </div>

                @can(\Bladewright\Access\Abilities::gate(\Bladewright\Access\Abilities::EDIT_CONTENT))
                    {{-- **Media is the one thing revisions cannot restore.** Where it is used
                         comes first, and the way to delete after it. --}}
                    @php($used = $this->usedBy())

                    <div class="border-t border-red-100 bg-red-50/40 px-4 py-3 dark:border-red-900/40 dark:bg-red-950/20">
                        <div class="text-[0.8125rem] font-semibold text-red-700 dark:text-red-300">{{ __('Delete this file') }}</div>

                        <p class="m-0 mt-1 text-[0.75rem] text-gray-600 dark:text-gray-400">
                            {{ __('The file itself is gone, and it cannot be undone.') }}
                        </p>

                        @if ($used->isEmpty())
                            <p class="m-0 mt-1 text-[0.75rem] text-gray-500 dark:text-gray-400">{{ __('Not used anywhere.') }}</p>
                        @else
                            <p class="m-0 mt-1 text-[0.75rem] text-gray-600 dark:text-gray-400">
                                {{ __('Used in :n place(s):', ['n' => $used->count()]) }}
                            </p>
                            <ul class="m-0 mt-1 list-none text-[0.75rem] text-gray-600 dark:text-gray-400">
                                @foreach ($used->take(5) as $item)
                                    <li class="truncate">・{{ $item->name }}</li>
                                @endforeach
                                @if ($used->count() > 5)
                                    <li>{{ __(':n more', ['n' => $used->count() - 5]) }}</li>
                                @endif
                            </ul>
                        @endif

                        <button type="button"
                                class="mt-2 inline-flex cursor-pointer items-center gap-1 rounded-md border border-red-300 bg-white px-2.5 py-1 text-[0.8125rem] font-semibold text-red-700 transition hover:bg-red-50 dark:border-red-900 dark:bg-gray-900 dark:text-red-300 dark:hover:bg-red-950"
                                wire:click="remove('{{ $current->path }}')"
                                wire:confirm="{{ $used->isEmpty()
                                    ? __('Delete this file? It cannot be undone.')
                                    : __('Used in :n place(s). Deleting it removes the image from all of them, and it cannot be undone. Continue?', ['n' => $used->count()]) }}">
                            {{ __('Delete') }}
                        </button>
                    </div>
                @endcan
            @endif
        </aside>

        @if ($canEdit)
            {{-- All it does is take a name, so it sits on top rather than taking the screen. --}}
            {{-- **Whether it is open is the browser's to keep.** Without wire:ignore.self,
                 a Livewire re-render puts it back to closed and the little window
                 disappears while the name is still being typed. --}}
            <div data-bw-modal="new-folder" wire:ignore.self class="fixed inset-0 z-50 hidden items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/50" data-bw-modal-close></div>

                <div class="relative z-10 w-full max-w-sm rounded-xl border border-gray-200 bg-white p-5 shadow-xl dark:border-gray-800 dark:bg-gray-900">
                    <h2 class="text-sm font-semibold">{{ __('New folder') }}</h2>

                    <input type="text" wire:model="newFolder" data-bw-modal-focus
                           class="mt-3 w-full rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-[0.8125rem] transition focus:border-bw-accent focus:ring-2 focus:ring-bw-accent/20 focus:outline-none dark:border-gray-700 dark:bg-gray-950"
                           placeholder="{{ __('Name') }}" wire:keydown.enter="createFolder">

                    <div class="mt-4 flex items-center justify-end gap-2">
                        <button type="button" data-bw-modal-close
                                class="inline-flex cursor-pointer items-center gap-1 rounded-md border border-gray-300 bg-white px-2.5 py-1 text-[0.8125rem] font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">
                            {{ __('Cancel') }}
                        </button>
                        <button type="button" wire:click="createFolder"
                                class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg bg-linear-to-tl from-bw-accent-2 to-bw-accent px-3 py-1.5 text-[0.8125rem] font-semibold text-white shadow-xs transition hover:brightness-110">
                            {{ __('Create') }}
                        </button>
                    </div>
                </div>
            </div>
        @endif
    @endif
</div>
