<?php

namespace Bladewright\Support;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Foundation\Application;
use InvalidArgumentException;
use Bladewright\Models\Setting;
use Throwable;

/**
 * Changing Laravel's configuration from the admin.
 *
 * No file is rewritten; config is simply overridden at boot. **To somebody
 * working from the GUI it is indistinguishable from editing config/app.php**,
 * but it lives alongside config:cache, works on a read-only container, and
 * cannot break the way a string replacement can.
 */
class Settings
{
    /**
     * Never overridable, whatever the allow list says.
     *
     * database … configuration is read from here, so it is a chicken and an egg
     * app.key  … change it and every piece of existing encrypted data is lost
     * app.debug / app.env … opened in production, information leaks
     */
    private const FORBIDDEN = ['database', 'app.key', 'app.debug', 'app.env', 'bladewright'];

    /**
     * The forbidden ones opened by name.
     *
     * **Our own settings are not touched from a screen** unless a name stands
     * here. The site's language is one: what language new pages are born in
     * is the site's own character, not a matter of touching code. Additions
     * come one name at a time. Never a wildcard.
     */
    private const ALLOWED_OWN = ['bladewright.locale'];

    /**
     * Keys that changing config alone does not settle.
     *
     * timezone holds a PHP global setting and locale holds a translator, each
     * separately.
     */
    private const APPLIERS = [
        'app.timezone' => 'applyTimezone',
        'app.locale' => 'applyLocale',
        'app.fallback_locale' => 'applyFallbackLocale',
    ];

    private const CACHE_KEY = 'bladewright:settings';

    /**
     * The value before the override.
     *
     * **Not being able to see where something was decided is the worst of it.**
     * When a file (or .env) and the database disagree, a developer looks at the
     * file and thinks it is right. Whatever overrode it has to remember the
     * original and be able to show it.
     *
     * @var array<string, mixed>
     */
    private array $originals = [];

    public function __construct(
        private readonly Application $app,
        private readonly CacheFactory $cache,
    ) {}

    /**
     * Override config with the stored settings.
     *
     * **It must not fall over when the database is unreadable.** Throwing
     * before the migrations, or during a database incident, takes the whole
     * site down.
     */
    public function apply(): void
    {
        foreach ($this->all() as $key => $value) {
            if (! $this->isWritable($key)) {
                continue;
            }

            // **Remembered on the first pass only.** After that it would
            // remember the overridden value as the original, and what the file
            // actually said would be lost.
            $this->originals[$key] ??= $this->app['config']->get($key);

            $this->app['config']->set($key, $value);

            if (isset(self::APPLIERS[$key])) {
                $this->{self::APPLIERS[$key]}($value);
            }
        }
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        $load = function (): array {
            try {
                return Setting::query()->pluck('value', 'key')->all();
            } catch (Throwable) {
                // No table yet, or a database incident. Carry on with the defaults.
                return [];
            }
        };

        if (! $this->app['config']->get('bladewright.cache.enabled')) {
            return $load();
        }

        try {
            return $this->store()->remember(
                self::CACHE_KEY,
                $this->app['config']->get('bladewright.cache.ttl'),
                $load,
            );
        } catch (Throwable) {
            return $load();
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->assertWritable($key);

        // **A structured setting must not be flattened by a single string.**
        // Put a string where storage's connection
        // (`filesystems.disks.bladewright`) expects an array and the driver
        // goes with it — **every image disappears**. Settings take effect at
        // boot, so it happens on every page from the next request on.
        if (is_array($this->app['config']->get($key)) && ! is_array($value)) {
            throw new InvalidArgumentException(
                "[{$key}] is a structured setting; a single value cannot replace it (change where files live in .env or the config file).",
            );
        }

        Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);

        $this->flush();
        $this->apply();
    }

    public function forget(string $key): void
    {
        Setting::query()->where('key', $key)->delete();

        $this->flush();
    }

    public function flush(): void
    {
        if (! $this->app['config']->get('bladewright.cache.enabled')) {
            return;
        }

        try {
            $this->store()->forget(self::CACHE_KEY);
        } catch (Throwable) {
            // A settings change goes through even where no cache is available.
        }
    }

    /**
     * May this key be overridden?
     *
     * **Only** what is on the allow list and not forbidden.
     */
    public function isWritable(string $key): bool
    {
        if (in_array($key, self::ALLOWED_OWN, true)) {
            return true;
        }

        foreach (self::FORBIDDEN as $forbidden) {
            if ($key === $forbidden || str_starts_with($key, $forbidden.'.')) {
                return false;
            }
        }

        foreach ((array) $this->app['config']->get('bladewright.settings.allow', []) as $pattern) {
            if ($pattern === $key) {
                return true;
            }

            if (str_ends_with($pattern, '.*') && str_starts_with($key, substr($pattern, 0, -1))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Stored, but no longer allowed.
     *
     * **Ignoring it quietly makes an accident nobody can explain.** It
     * happened: `app.timezone` came off the allow list, the `published_at`
     * values written until then in Asia/Tokyo were read as UTC, and **published
     * pages became "not published yet" and answered 404**. The values stay in
     * the database, so what was taken off has to be visible.
     *
     * @return array<string, mixed>
     */
    public function ignored(): array
    {
        $ignored = [];

        foreach ($this->all() as $key => $value) {
            if (! $this->isWritable($key)) {
                $ignored[$key] = $value;
            }
        }

        return $ignored;
    }

    /**
     * The keys currently overridden. **Shown by `php artisan about`.**
     *
     * @return array<string, mixed> key => the value before the override
     */
    public function overrides(): array
    {
        return $this->originals;
    }

    /** The value before the override (what the config file or .env decided). */
    public function original(string $key): mixed
    {
        return $this->originals[$key] ?? null;
    }

    public function isOverriding(string $key): bool
    {
        return array_key_exists($key, $this->originals);
    }

    /** @return array<int, string> */
    public function writableKeys(): array
    {
        return array_values((array) $this->app['config']->get('bladewright.settings.allow', []));
    }

    private function assertWritable(string $key): void
    {
        if (! $this->isWritable($key)) {
            throw new InvalidArgumentException("Setting [{$key}] is not writable from the admin.");
        }
    }

    /**
     * date_default_timezone_set runs second in the boot, before the providers,
     * but it is a PHP global setting so overriding it later works (measured).
     */
    private function applyTimezone(mixed $value): void
    {
        if (is_string($value) && in_array($value, timezone_identifiers_list(), true)) {
            date_default_timezone_set($value);
        }
    }

    private function applyLocale(mixed $value): void
    {
        if (is_string($value) && $value !== '') {
            $this->app->setLocale($value);
        }
    }

    private function applyFallbackLocale(mixed $value): void
    {
        if (is_string($value) && $value !== '') {
            $this->app->setFallbackLocale($value);
        }
    }

    private function store()
    {
        return $this->cache->store($this->app['config']->get('bladewright.cache.store'));
    }
}
