<?php

namespace Bladewright;

use Illuminate\Console\Application as Artisan;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Bladewright\Access\Abilities;
use Bladewright\Console\BlocksCommand;
use Bladewright\Console\ComponentsCommand;
use Bladewright\Console\InstallCommand;
use Bladewright\Console\LayoutsCommand;
use Bladewright\Console\MediaCommand;
use Bladewright\Console\PagesCommand;
use Bladewright\Console\SettingCommand;
use Bladewright\Console\UninstallCommand;
use Bladewright\Console\UserCommand;
use Bladewright\Content\Markdown;
use Bladewright\Http\Controllers\Admin\AssetController;
use Bladewright\Http\Controllers\Admin\BlocksController;
use Bladewright\Http\Controllers\Admin\ComponentsController;
use Bladewright\Http\Controllers\Admin\LayoutsController;
use Bladewright\Http\Controllers\Admin\LoginController;
use Bladewright\Http\Controllers\Admin\MediaLibraryController;
use Bladewright\Http\Controllers\Admin\PagesController;
use Bladewright\Http\Controllers\Admin\SettingsController;
use Bladewright\Http\Controllers\Admin\SitePagesController;
use Bladewright\Http\Controllers\MediaController;
use Bladewright\Http\Controllers\SitePageController;
use Bladewright\Media\MediaLibrary;
use Bladewright\Support\Settings;

/**
 * Bladewright: edit a live site from the browser.
 *
 * The site is the four-layer model — pages of components of blocks, wrapped
 * in layouts, everything referenced by uuid and served straight from the
 * database (`Site\PublicSite`). The old world of Blade checkouts and baked
 * markers was dismantled on 2026-09-03.
 *
 * **Nothing is ever written to the application's source tree**, no route
 * file is rewritten, and the host's own routes always win: the site hangs
 * off a single fallback route.
 */
class BladewrightServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/bladewright.php', 'bladewright');

        $this->registerAuthGuard();

        $this->app->singleton(Markdown::class);
        $this->app->singleton(MediaLibrary::class);
        $this->registerMediaDisk();
        $this->app->singleton(Settings::class);

        // **Config-shaped services are singletons**: each caches what it
        // read, and a fresh instance per call re-reads bw_settings — a page
        // render was asking the palette thirty times over.
        $this->app->singleton(\Bladewright\Support\Palette::class);
        $this->app->singleton(\Bladewright\Support\Framework::class);
        $this->app->singleton(\Bladewright\Support\SiteCss::class);
        $this->app->singleton(\Bladewright\Support\Analytics::class);
        // The render's own gatherings: hover and small-screen rules the
        // parts ask for on the way through. One bucket a request.
        $this->app->singleton(\Bladewright\Support\CollectedCss::class);

    }

    /**
     * The admin's own way in: a guard and a provider over `bw_users`.
     *
     * **Additive, under our own names.** The host's guards, providers and
     * users are not touched — this only teaches Laravel two names it did not
     * know, the way a view namespace does. A host that wants its own people
     * in the admin points BLADEWRIGHT_AUTH_GUARD back at its guard instead.
     */
    private function registerAuthGuard(): void
    {
        $config = $this->app['config'];

        if ($config->get('auth.guards.bladewright') === null) {
            $config->set('auth.guards.bladewright', ['driver' => 'session', 'provider' => 'bladewright-users']);
        }

        if ($config->get('auth.providers.bladewright-users') === null) {
            $config->set('auth.providers.bladewright-users', ['driver' => 'eloquent', 'model' => \Bladewright\Models\User::class]);
        }
    }

    /**
     * Register one disk for the media.
     *
     * **This is not implementing storage.** It adds a single setting, exactly
     * as local and public are defined in config/filesystems.php. It is kept
     * apart from the host's local because that is a place the host uses for
     * its own purposes; a separate name means one environment variable moves
     * it.
     */
    private function registerMediaDisk(): void
    {
        $disks = $this->app['config']->get('filesystems.disks', []);

        if (isset($disks['bladewright'])) {
            return;
        }

        $disks['bladewright'] = [
            'driver' => 'local',
            'root' => $this->app['config']->get('bladewright.media.root')
                ?: storage_path('app/bladewright/media'),
            'throw' => false,
        ];

        $this->app['config']->set('filesystems.disks', $disks);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Override config with the stored settings. It has to take effect
        // before the host application's boot, so it happens here.
        $this->app->make(Settings::class)->apply();

        $this->registerAbilities();
        $this->registerAdminViews();
        $this->registerLivewireNamespace();
        $this->registerBladeDirectives();
        $this->registerRoutes();
        $this->registerAboutSection();

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                UserCommand::class,
                PagesCommand::class,
                LayoutsCommand::class,
                ComponentsCommand::class,
                BlocksCommand::class,
                MediaCommand::class,
                SettingCommand::class,
                UninstallCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/bladewright.php' => config_path('bladewright.php'),
            ], 'bladewright-config');
        }
    }

    /**
     * Until roles are designed again: **everyone signed in can do
     * everything** — and only Bladewright's own accounts can sign in.
     */
    private function registerAbilities(): void
    {
        foreach (Abilities::ALL as $ability) {
            Gate::define(
                Abilities::gate($ability),
                fn ($user) => $user !== null,
            );
        }
    }

    /** The admin's views and words, under the package's own namespace. */
    private function registerAdminViews(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'bladewright');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'bladewright');

        Blade::componentNamespace('Bladewright\\View\\Components', 'bladewright');
        Blade::anonymousComponentPath(__DIR__.'/../resources/views/admin', 'bladewright');
    }

    /** The admin's Livewire single-file components (the media library, the settings panel). */
    private function registerLivewireNamespace(): void
    {
        if (! $this->app->bound('livewire.finder')) {
            return;
        }

        \Livewire\Livewire::addNamespace(
            'bladewright',
            viewPath: __DIR__.'/../resources/views/admin/livewire',
        );

        // **Livewire replays `can:` on every update, but not our own
        // gatekeeper** — and it is the gatekeeper that points the Gate at
        // the admin's guard (`Auth::shouldUse`). Without this, the replayed
        // `can:` looks at the default guard, finds nobody, and every update
        // dies 403 the moment the admin guard is not the default one.
        \Livewire\Livewire::addPersistentMiddleware([
            \Bladewright\Http\Middleware\AdminAuthenticate::class,
        ]);
    }

    private function registerBladeDirectives(): void
    {
        // The package's CSS and JS. A directive **so it can carry a version.**
        $this->app['blade.compiler']->directive('bwasset', function ($expression) {
            return "<?php echo e(\\Bladewright\\Support\\Assets::url({$expression})); ?>";
        });

        // The site's stylesheet, linked from a layout's head. **A directive
        // so it can carry its version**: a change makes a new URL, and the
        // browser's hard cache does no harm.
        // The site's one framework, asked at render time. **The head asks
        // the site**, so switching the declaration reaches every frame that
        // carries this word — and the previews ask the same question.
        $this->app['blade.compiler']->directive('bwframework', function () {
            return "<?php echo app(\\Bladewright\\Support\\Framework::class)->linkTag(); ?>";
        });

        // What the page says about itself — title, description, robots —
        // written where the frame opens its head. **The words are the
        // page's, the place is the frame's.**
        $this->app['blade.compiler']->directive('bwmeta', function () {
            return "<?php echo \\Bladewright\\Support\\Meta::tags(\$page ?? null); ?>";
        });

        // Google Analytics from the one stored id — **on the public pages
        // only**: the admin's screens and previews are not visits.
        $this->app['blade.compiler']->directive('bwanalytics', function () {
            return "<?php echo app(\\Bladewright\\Support\\Analytics::class)->scriptTags(); ?>";
        });

        // The site's stylesheet — and the rules this very render gathered:
        // hover states and small-screen collapses cannot be said inline, so
        // the parts leave them with the collector and the head prints them.
        // **The body renders before the frame does**, so the bucket is full
        // by the time this line runs.
        $this->app['blade.compiler']->directive('bwstyles', function () {
            return "<?php echo '<link rel=\"stylesheet\" href=\"'.e(route('bladewright.site.css', ['v' => app(\\Bladewright\\Support\\SiteCss::class)->version()])).'\">'"
                .".app(\\Bladewright\\Support\\CollectedCss::class)->styleTag(); ?>";
        });

        // Markdown for bodies. **The name is ours.**
        $markdown = function ($expression) {
            return "<?php echo app(\\Bladewright\\Content\\Markdown::class)->render({$expression}); ?>";
        };

        $blade = $this->app['blade.compiler'];
        $blade->directive('bwmarkdown', $markdown);

        // `@markdown` is claimed **only when it is free**: Blade's directive()
        // overwrites in silence, and common nouns are not worth a tug of war.
        if (! array_key_exists('markdown', $blade->getCustomDirectives())) {
            $blade->directive('markdown', $markdown);
        }
    }

    /**
     * Nothing is written to the route files.
     *
     * A fallback is always evaluated last, whatever the order of
     * registration, so the host application's own routes come before
     * Bladewright.
     */
    private function registerRoutes(): void
    {
        $config = $this->app['config']->get('bladewright.routing');

        if (! ($config['enabled'] ?? true)) {
            return;
        }

        $this->registerAdminRoutes();

        // Media are the public site's images, so they sit outside authentication.
        Route::middleware($config['middleware'])
            ->get('bladewright/media/{path}', MediaController::class)
            ->where('path', '.*')
            ->name('bladewright.media');

        // And the site's stylesheet dresses the published pages, so it sits
        // there too.
        Route::middleware($config['middleware'])
            ->get('bladewright/site.css', \Bladewright\Http\Controllers\SiteCssController::class)
            ->name('bladewright.site.css');

        Route::middleware($config['middleware'])->group(function () {
            Route::fallback(SitePageController::class)->name('bladewright.page');
        });
    }

    /**
     * The admin's routes.
     *
     * The default is /bladewright rather than /admin: this goes into sites
     * already running, and Filament, Nova and Backpack all default to /admin.
     */
    private function registerAdminRoutes(): void
    {
        $admin = $this->app['config']->get('bladewright.admin');

        $group = Route::prefix($admin['prefix'] ?? 'bladewright')->as('bladewright.admin.');

        if (! empty($admin['domain'])) {
            $group = $group->domain($admin['domain']);
        }

        // The CSS is outside authentication. The login screen has to load it too.
        $group->get('assets/{file}', AssetController::class)
            ->where('file', '[A-Za-z0-9._-]+')
            ->name('asset');

        // The login screen. **Nothing is added to the host application** —
        // neither `/login` nor the name `login` is taken, and it stays here.
        $group->middleware($admin['guest_middleware'] ?? ['web'])->group(function () {
            Route::get('login', [LoginController::class, 'show'])->name('login');
            Route::post('login', [LoginController::class, 'login']);
            Route::post('logout', [LoginController::class, 'logout'])->name('logout');
        });

        $group->middleware($admin['middleware'] ?? ['web', 'auth'])->group(function () {
            // The admin opens on the pages — the site itself.
            Route::get('/', [PagesController::class, 'index'])->name('home');
            Route::get('media', [MediaLibraryController::class, 'index'])->name('media');

            // The four-layer screens, arriving floor by floor.
            Route::get('pages', [SitePagesController::class, 'index'])->name('pages');
            Route::get('pages/{page}', [SitePagesController::class, 'edit'])->name('pages.edit');
            Route::get('pages/{page}/settings', [SitePagesController::class, 'settings'])->name('pages.settings');
            Route::get('pages/{page}/preview', [SitePagesController::class, 'preview'])->name('pages.preview');

            Route::get('layouts', [LayoutsController::class, 'index'])->name('layouts');
            Route::get('layouts/{layout}', [LayoutsController::class, 'edit'])->name('layouts.edit');
            Route::get('layouts/{layout}/settings', [LayoutsController::class, 'settings'])->name('layouts.settings');

            Route::get('components', [ComponentsController::class, 'index'])->name('components');
            Route::get('components/{component}', [ComponentsController::class, 'edit'])->name('components.edit');
            Route::get('components/{component}/settings', [ComponentsController::class, 'settings'])->name('components.settings');

            Route::get('blocks', [BlocksController::class, 'index'])->name('blocks');
            Route::get('blocks/{block}', [BlocksController::class, 'edit'])->name('blocks.edit');
            Route::get('blocks/{block}/settings', [BlocksController::class, 'settings'])->name('blocks.settings');

            Route::middleware('can:'.Abilities::gate(Abilities::MANAGE_SETTINGS))->group(function () {
                Route::get('settings', [SettingsController::class, 'index'])->name('settings');
                Route::get('settings/colours', [SettingsController::class, 'colours'])->name('settings.colours');
                Route::get('settings/stylesheet', [SettingsController::class, 'stylesheet'])->name('settings.stylesheet');
                Route::get('settings/application', [SettingsController::class, 'application'])->name('settings.application');
                Route::get('settings/analytics', [SettingsController::class, 'analytics'])->name('settings.analytics');
                Route::get('settings/export', [SettingsController::class, 'export'])->name('settings.export');

                // The unit for adding a whole feature. What is in it comes later.
            });
        });
    }

    private function registerAboutSection(): void
    {
        if (! class_exists(AboutCommand::class)) {
            return;
        }

        AboutCommand::add('Bladewright', function () {
            $settings = $this->app->make(Settings::class);
            $overrides = $settings->overrides();
            $admin = '/'.trim((string) $this->app['config']->get('bladewright.admin.prefix', 'bladewright'), '/');

            return [
                'Admin' => $admin,
                // **Where the site's contents live.** By default the app's own
                // connection, bw_* alongside — which is why migrate:fresh
                // takes the site with it, and that has to be visible.
                'Database' => $this->databaseSummary(),
                'Media disk' => (string) $this->app['config']->get('bladewright.media.disk', 'bladewright'),
                'Editable settings' => implode(', ', $settings->writableKeys()) ?: 'none',
                // **.env is never rewritten.** Only what appears here is overridden.
                'Config overridden from database' => $overrides === []
                    ? 'none'
                    : implode(', ', array_keys($overrides)),
                // **Never ignored quietly.** A setting that fell off the allow
                // list stays in the database and becomes "I saved it and
                // nothing happened".
                'Saved but not allowed' => ($ignored = $settings->ignored()) === []
                    ? 'none'
                    : implode(', ', array_keys($ignored)),
            ];
        });
    }

    private function databaseSummary(): string
    {
        $chosen = $this->app['config']->get('bladewright.database.connection');
        $name = $chosen ?: (string) $this->app['config']->get('database.default');
        $driver = (string) $this->app['config']->get('database.connections.'.$name.'.driver', '?');

        return $chosen
            ? sprintf('%s (%s) — separate from the app', $name, $driver)
            : sprintf('%s (%s) — same as the app; migrate:fresh wipes the site', $name, $driver);
    }
}
