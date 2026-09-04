<?php

namespace Workbench\App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * The scaffolding for checking locally. Not in the package (autoload-dev).
 *
 * Trying bladewright:transfer here needs somewhere to transfer to, so a second
 * sqlite connection is defined.
 */
class WorkbenchServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->pinDatabase();

        // **The database was swapped, so the stored settings are read again.**
        // The package's boot can run before this one, and then it looks at
        // another database and remembers that there are no settings. Rather
        // than leaning on the order of the providers, what it remembered is
        // thrown away and read again.
        if (! $this->app->runningUnitTests()) {
            $settings = $this->app->make(\Bladewright\Support\Settings::class);
            $settings->flush();
            $settings->apply();
        }

        // The same story as the package's AdminAuthenticate: Livewire
        // replays `can:` on updates, so whoever signs the visitor in must
        // ride along or the replayed check finds nobody.
        if ($this->app->bound('livewire')) {
            \Livewire\Livewire::addPersistentMiddleware([
                \Workbench\App\Http\Middleware\DevLogin::class,
            ]);
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                \Workbench\App\Console\BuildDemoCommand::class,
                \Workbench\App\Console\BuildLayoutCommand::class,
            ]);
        }
    }

    /**
     * Fix the database the development server sees.
     *
     * **The child process of serve does not receive testbench.yaml's env**
     * (Laravel's ServeCommand passes only a few, such as APP_ENV). So testbench
     * starts with APP_ENV=testing and `database.default` becomes testing's
     * :memory:. Data made by a command is invisible from the server, and it
     * fails with `no such table: users`.
     *
     * **Where DB_DATABASE was given, nothing is touched.** The tests, and the
     * child processes of their trial renders, name their own database.
     */
    private function pinDatabase(): void
    {
        $given = env('DB_DATABASE');

        // An absolute path comes from the tests and from a trial render's child
        // process, and it is respected. What reaches serve is testbench.yaml's
        // relative path, and **the server's working directory is the
        // skeleton's, so it cannot be resolved** — and database.default falls
        // to testing's :memory:.
        if ($this->app->runningUnitTests() || (is_string($given) && str_starts_with($given, '/'))) {
            return;
        }

        $root = dirname(__DIR__, 3);

        $this->app['config']->set('database.default', 'sqlite');
        $this->app['config']->set('database.connections.sqlite.database', $root.'/workbench/database/database.sqlite');

        // **The cache does not receive the yaml's setting (array) either.** Old
        // settings sit in the database's cache table, and swapping the database
        // keeps reading the previous values.
        $this->app['config']->set('cache.default', 'array');
    }

    public function register(): void
    {
        // basePath() points at testbench's skeleton and cannot be used. The
        // package's root is worked out from this file.
        $root = dirname(__DIR__, 3);

        // A trial render's child process starts the skeleton's artisan and so
        // receives none of testbench.yaml's env. It is shown the same database.
        $this->app['config']->set('bladewright.validation.env', [
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => $root.'/workbench/database/database.sqlite',
            'CACHE_STORE' => 'array',
            // What the skeleton's artisan needs to find the real vendor.
            'TESTBENCH_WORKING_PATH' => $root,
        ]);

        // The workbench has no host authentication, so for development the
        // visitor is always treated as signed in. The permissions (`can:`) still
        // apply, since what they do is what we want to see. The preview is the
        // same, and is really guarded by auth plus can.
        //
        // BW_DEV_LOGIN=0 sends you through the real login screen, for checking
        // /bladewright/login by hand.
        $gate = filter_var(env('BW_DEV_LOGIN', true), FILTER_VALIDATE_BOOL)
            ? \Workbench\App\Http\Middleware\DevLogin::class
            : \Bladewright\Http\Middleware\AdminAuthenticate::class;

        $this->app['config']->set('bladewright.routing.preview_middleware', [
            'web',
            $gate,
            'can:bladewright.access-admin',
        ]);

        $this->app['config']->set('bladewright.admin.middleware', [
            'web',
            $gate,
            'can:bladewright.access-admin',
        ]);

        $this->app['config']->set('database.connections.second', [
            'driver' => 'sqlite',
            'database' => $root.'/workbench/database/second.sqlite',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
    }
}
