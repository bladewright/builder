<?php

use Livewire\Component;
use Bladewright\Access\Abilities;
use Bladewright\Support\Settings;
use Bladewright\Support\Toasts;

new class extends Component
{
    use Toasts;

    /**
     * @var array<int, array{key: string, value: string}>
     *
     * **It cannot be an array keyed by the setting's key.** Keys contain dots,
     * as in app.name, and Livewire reads a dot as a path into a nest, so it
     * would write to values['app']['name'].
     */
    public array $values = [];

    public function mount(): void
    {
        $this->values = [];

        foreach ($this->keys() as $key) {
            $this->values[] = ['key' => $key, 'value' => (string) (config($key) ?? '')];
        }
    }

    /**
     * The keys this screen handles.
     *
     * Wildcards (`mail.*` / `filesystems.disks.*`) are not shown here. They are
     * structured settings and each needs a screen of its own.
     * **Storage connections hold secrets and wait for their own**, which today
     * would be in plain text.
     *
     * @return array<int, string>
     */
    public function keys(): array
    {
        return array_values(array_filter(
            app(Settings::class)->writableKeys(),
            // **Structured settings are not shown here.** Anything held as an
            // array, such as a disk's connection, needs a screen of its own
            // (and holds secrets).
            fn (string $key) => ! str_contains($key, '*') && ! is_array(config($key)),
        ));
    }

    public function label(string $key): string
    {
        return match ($key) {
            'app.name' => __('Site name'),
            'app.timezone' => __('Time zone'),
            'app.locale' => __('Language'),
            'app.fallback_locale' => __('Fallback language'),
            'bladewright.locale' => __('Language new pages are born in'),
            default => $key,
        };
    }

    /** Only where an explanation is needed. **Showing the key and hoping is unkind.** */
    public function note(string $key): ?string
    {
        return match ($key) {
            'bladewright.locale' => __('Their <html lang> and their URL\'s language. Pages that already exist keep their own. Left empty, the application\'s language is followed.'),
            default => null,
        };
    }

    /** The ones settled by yes or no. None today; the shape stays for the next one. */
    public function isSwitch(string $key): bool
    {
        return false;
    }

    public function isFromDatabase(string $key): bool
    {
        return app(Settings::class)->get($key) !== null;
    }

    /**
     * The value the config file (or .env) decided.
     *
     * **Show which one is in force.** When behaviour disagrees with the file, a
     * developer looks at the file and thinks it is right.
     */
    public function fileValue(string $key): ?string
    {
        $settings = app(Settings::class);

        if (! $settings->isOverriding($key)) {
            return null;
        }

        $original = $settings->original($key);

        return match (true) {
            $original === null => __('(not set)'),
            is_bool($original) => $original ? 'true' : 'false',
            is_scalar($original) => (string) $original,
            default => null,
        };
    }

    public function timezones(): array
    {
        return timezone_identifiers_list();
    }

    public function save(): void
    {
        $this->authorize(Abilities::gate(Abilities::MANAGE_SETTINGS));

        $settings = app(Settings::class);

        foreach ($this->values as $row) {
            if (! $settings->isWritable($row['key'])) {
                continue;
            }

            $row['value'] === ''
                ? $settings->forget($row['key'])
                : $settings->set($row['key'], $row['value']);
        }

        $settings->apply();
        $this->mount();

        $this->toast(__('Saved.'));
    }

    /** resetToDefault, because reset() collides with Livewire's own. */
    public function resetToDefault(string $key): void
    {
        $this->authorize(Abilities::gate(Abilities::MANAGE_SETTINGS));

        app(Settings::class)->forget($key);
        app(Settings::class)->apply();

        $this->toast(__('Reset :key to the default in the config file. It applies from the next boot.', ['key' => $key]));
    }
};
?>

<div class="mt-4 rounded-xl border border-gray-200 bg-white p-6 first:mt-0 dark:border-gray-800 dark:bg-gray-900">

    @php($ignored = app(Settings::class)->ignored())

    @if ($ignored !== [])
        {{-- **Never ignored quietly.** A setting off the allow list stays in the database. --}}
        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-[0.8125rem] text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-100">
            {{ __('Some saved settings are not in effect: :keys', ['keys' => implode(', ', array_keys($ignored))]) }}
            <div class="mt-1">{{ __('Add it to settings.allow in config/bladewright.php to make it apply. Leaving it is harmless.') }}</div>
        </div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="m-0 text-base font-semibold">{{ __('Site settings') }}</h2>
            <p class="mt-1 text-[0.8125rem] text-gray-500 dark:text-gray-400">
                {{-- **By default the host application's settings are untouched.** Without saying
                     why they are missing, it just looks like there is little to configure. --}}
                {{ __('These overwrite configuration at boot (the config files are never edited). Application-wide settings such as the site name or the time zone are left alone by default — add them to settings.allow in config/bladewright.php to bring them here.') }}
            </p>
        </div>
    </div>

    @foreach ($values as $index => $row)
        @php($key = $row['key'])
        <div class="flex flex-col gap-1.5 border-t border-gray-100 py-3 first-of-type:border-t-0 sm:flex-row sm:items-start sm:gap-4 dark:border-gray-800" wire:key="setting-{{ $key }}">
            <label class="w-40 shrink-0 pt-2 text-[0.8125rem] font-medium text-gray-600 dark:text-gray-400">{{ $this->label($key) }}</label>
            <div class="flex-1">
                @if ($this->isSwitch($key))
                    {{-- Yes or no gets a toggle, not a field --}}
                    <label class="inline-flex cursor-pointer items-center gap-2.5">
                        <input type="checkbox" class="peer sr-only"
                               value="1" @checked(filter_var($row['value'], FILTER_VALIDATE_BOOL))
                               wire:model="values.{{ $index }}.value">
                        <span class="relative h-5 w-9 rounded-full bg-gray-300 transition-colors
                                     peer-checked:bg-bw-accent peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2
                                     peer-focus-visible:outline-bw-accent dark:bg-gray-700
                                     after:absolute after:top-0.5 after:left-0.5 after:h-4 after:w-4 after:rounded-full
                                     after:bg-white after:shadow-xs after:transition-transform after:content-['']
                                     peer-checked:after:translate-x-4"></span>
                        <span class="text-sm text-gray-600 peer-checked:text-gray-900 dark:text-gray-400 dark:peer-checked:text-gray-100">
                            {{ filter_var($row['value'], FILTER_VALIDATE_BOOL) ? __('Supported') : __('Not supported') }}
                        </span>
                    </label>
                @elseif ($key === 'app.timezone')
                    <select class="w-full rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-[0.8125rem] shadow-xs transition focus:border-bw-accent focus:ring-2 focus:ring-bw-accent/20 focus:outline-none dark:border-gray-700 dark:bg-gray-950 flex-1" wire:model="values.{{ $index }}.value">
                        @foreach ($this->timezones() as $zone)
                            <option value="{{ $zone }}" @selected($row['value'] === $zone)>{{ $zone }}</option>
                        @endforeach
                    </select>
                @else
                    <input type="text" class="w-full rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-[0.8125rem] shadow-xs transition focus:border-bw-accent focus:ring-2 focus:ring-bw-accent/20 focus:outline-none dark:border-gray-700 dark:bg-gray-950 flex-1"
                           value="{{ $row['value'] }}" wire:model="values.{{ $index }}.value">
                @endif

                @if ($this->note($key))
                    <p class="mt-1 text-[0.8125rem] text-gray-500 dark:text-gray-400">{{ $this->note($key) }}</p>
                @endif

                <div class="mt-1 text-[0.8125rem] text-gray-500 dark:text-gray-400">
                    <code>{{ $key }}</code>
                    @if ($this->isFromDatabase($key))
                        · <button type="button" class="cursor-pointer border-0 bg-transparent p-0 text-inherit underline decoration-bw-accent underline-offset-2" wire:click="resetToDefault('{{ $key }}')">{{ __('Reset to the default') }}</button>
                    @else
                        · {{ __('the config file\'s default') }}
                    @endif
                </div>

                {{-- **So nobody gets stuck when it disagrees with the file.**
                     .env is never rewritten, so the original value is still there. --}}
                @if (($file = $this->fileValue($key)) !== null)
                    <p class="mt-1 text-[0.8125rem] text-amber-700 dark:text-amber-300">
                        {{ __('The config file (.env) says :value. The value on this screen is winning.', ['value' => $file]) }}
                    </p>
                @endif
            </div>
        </div>
    @endforeach

    <div class="mt-4 flex flex-wrap items-center gap-2">
        <span class="flex-1"></span>
        <button type="button" class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg bg-linear-to-tl from-bw-accent-2 to-bw-accent px-3 py-1.5 text-[0.8125rem] font-semibold text-white shadow-xs transition hover:brightness-110 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-bw-accent" wire:click="save">{{ __('Save') }}</button>
    </div>
</div>
