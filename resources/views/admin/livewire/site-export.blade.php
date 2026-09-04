<?php

use Livewire\Component;
use Bladewright\Access\Abilities;
use Bladewright\Site\StaticSite;
use Bladewright\Support\Toasts;

/*
 * The site, taken as files.
 *
 * **A reading of the site, not a thing kept.** The zip is made on the press
 * and handed straight to the browser; nothing of it stays on the server,
 * because it can always be taken again.
 */
new class extends Component
{
    use Toasts;

    public function pages()
    {
        return app(StaticSite::class)->pages();
    }

    public function shaped()
    {
        return app(StaticSite::class)->shaped();
    }

    public function files(): int
    {
        return app(\Bladewright\Media\MediaLibrary::class)->everything()->count();
    }

    /** Where each page will stand in the folder. */
    public function fileFor($page): string
    {
        return app(StaticSite::class)->fileFor($page);
    }

    /** Make it, hand it over, and forget it. */
    public function take()
    {
        $this->authorize(Abilities::gate(Abilities::MANAGE_SETTINGS));

        if ($this->pages()->isEmpty()) {
            $this->toastError(__('There is no published page to write out yet.'));

            return null;
        }

        $path = tempnam(sys_get_temp_dir(), 'bw-site').'.zip';

        try {
            app(StaticSite::class)->writeTo($path);
        } catch (\Throwable $e) {
            @unlink($path);
            $this->toastError($e->getMessage());

            return null;
        }

        $name = \Illuminate\Support\Str::slug((string) config('app.name', 'site')) ?: 'site';

        // **Gone from the server the moment it has been handed over.**
        return response()->download($path, $name.'-'.now()->format('Y-m-d').'.zip')
            ->deleteFileAfterSend(true);
    }
};
?>

<div class="space-y-4">
    <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
        <h2 class="m-0 text-base font-semibold">{{ __('What comes out') }}</h2>

        <p class="m-0 mt-3 text-[0.8125rem] text-gray-500 dark:text-gray-400">
            {{ __('A folder you can put on any host that serves files. What the application was serving becomes a file: the stylesheet, and every picture the pages show. What is fetched from somewhere else — a framework on a CDN — stays fetched from there.') }}
        </p>

        @php($pages = $this->pages())
        @php($shaped = $this->shaped())

        @if ($pages->isEmpty())
            <p class="mt-4 mb-0 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-[0.8125rem] text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-100">
                {{ __('No page is published yet, so there is nothing to write out.') }}
            </p>
        @else
            <div class="-mx-6 mt-4 overflow-x-auto">
                <table class="w-full border-collapse text-sm">
                    <thead>
                        <tr>
                            <th class="border-b border-gray-200 px-6 pb-2 text-left text-xs font-medium text-gray-500 dark:border-gray-800 dark:text-gray-400">{{ __('Page') }}</th>
                            <th class="border-b border-gray-200 px-6 pb-2 text-left text-xs font-medium text-gray-500 dark:border-gray-800 dark:text-gray-400">{{ __('Becomes') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pages as $page)
                            <tr wire:key="out-{{ $page->uuid }}">
                                <td class="border-b border-gray-100 px-6 py-2.5 align-middle dark:border-gray-800">
                                    {{ $page->name }}
                                    <span class="ml-2 font-mono text-[0.75rem] text-gray-400">/{{ $page->url }}</span>
                                </td>
                                <td class="border-b border-gray-100 px-6 py-2.5 align-middle font-mono text-[0.8125rem] text-gray-500 dark:border-gray-800 dark:text-gray-400">{{ $this->fileFor($page) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <p class="mt-4 mb-0 text-[0.8125rem] text-gray-500 dark:text-gray-400">
                {{ trans_choice('{0}No file from the media library.|{1}One file from the media library, under media/.|[2,*]:n files from the media library, under media/.', $this->files(), ['n' => $this->files()]) }}
            </p>
        @endif

        {{-- **Said rather than quietly left out.** A page that is a shape
             answers a thousand paths or none, and which of them exist is not
             something a copy of the site can know. --}}
        @if ($shaped->isNotEmpty())
            <div class="mt-4 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-[0.8125rem] text-gray-600 dark:border-gray-700 dark:bg-gray-950/50 dark:text-gray-300">
                <p class="m-0 font-semibold">{{ __('Left out') }}</p>
                <p class="m-0 mt-1">{{ __('A page whose URL is a shape stands for many paths, and a copy cannot know which of them exist.') }}</p>
                <ul class="m-0 mt-2 list-none space-y-0.5 pl-0 font-mono text-[0.75rem] text-gray-500 dark:text-gray-400">
                    @foreach ($shaped as $page)
                        <li wire:key="shaped-{{ $page->uuid }}">/{{ $page->url }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>

    @can(\Bladewright\Access\Abilities::gate(\Bladewright\Access\Abilities::MANAGE_SETTINGS))
        <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
            <button type="button" wire:click="take" @disabled($this->pages()->isEmpty())
                    class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg bg-linear-to-tl from-bw-accent-2 to-bw-accent px-3 py-1.5 text-[0.8125rem] font-semibold text-white shadow-xs transition hover:brightness-110 disabled:cursor-default disabled:opacity-40">
                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 10l5 5 5-5"/><path d="M12 15V3"/></svg>
                {{ __('Take the site') }}
            </button>

            <p class="m-0 mt-3 text-[0.8125rem] text-gray-500 dark:text-gray-400">
                {{ __('Made when you press it and handed straight over. Nothing of it is kept here.') }}
            </p>
        </div>
    @endcan
</div>
