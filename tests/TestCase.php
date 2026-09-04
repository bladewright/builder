<?php

namespace Bladewright\Tests;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Orchestra\Testbench\TestCase as Orchestra;
use Bladewright\Access\Abilities;
use Bladewright\BladewrightServiceProvider;

abstract class TestCase extends Orchestra
{
    protected string $siteRoot;

    protected function setUp(): void
    {
        $this->siteRoot = sys_get_temp_dir().'/bladewright-'.Str::random(10);

        parent::setUp();

        // Messages are thrown rather than planted in a band on the screen, so
        // the tests look at whether one was thrown.
        \Livewire\Features\SupportTesting\Testable::macro(
            'assertToast',
            function (string $text) {
                return $this->assertDispatched(
                    'bw-toast',
                    fn (string $event, array $params) => str_contains($params['message'] ?? '', $text),
                );
            },
        );

        // **Each test gets its own place for media.** Anything left behind
        // would show up in the next run's list.
        $this->app['config']->set('filesystems.disks.bladewright.root', $this->siteRoot.'/media');
        (new Filesystem)->ensureDirectoryExists($this->siteRoot.'/media');
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->siteRoot);

        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        // Livewire is a real dependency, so it is on for every test.
        return [\Livewire\LivewireServiceProvider::class, BladewrightServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('bladewright.root', $this->siteRoot);
    }

    protected function defineRoutes($router): void
    {
        // The host application's own route. It must come before Bladewright.
        $router->get('host-owned', fn () => 'host application');
    }

    /**
     * **The admin lives on its own guard** (`bladewright`, over bw_users),
     * so acting as somebody means acting on that guard — otherwise every
     * admin request bounces off AdminAuthenticate. Tests that name a guard
     * themselves keep their word.
     */
    public function actingAs(\Illuminate\Contracts\Auth\Authenticatable $user, $guard = null)
    {
        return parent::actingAs($user, $guard ?? config('bladewright.auth.guard'));
    }

    /**
     * Run the install the way a person answers it.
     *
     * The install always asks in tests (`canAsk` says so), so the standard
     * questions are scripted here once: with `--fresh`, the site's name;
     * then the language, the framework, and whether to create an account (no).
     */
    protected function installSite(string $options = ''): \Illuminate\Testing\PendingCommand
    {
        $pending = $this->artisan(trim('bladewright:install '.$options));

        if (str_contains($options, '--fresh')) {
            $name = trim((string) config('app.name')) ?: (string) config('database.default');
            $pending->expectsQuestion("Type the site's name ({$name}) to wipe it", $name);
        }

        $pending->expectsQuestion('What language does this site write in? (en, ja, …)', (string) config('app.locale', 'en'));
        $pending->expectsQuestion("What is the site's CSS written in?", 'Bootstrap');
        $pending->expectsConfirmation('Create somebody to sign in with?', 'no');

        return $pending;
    }

    /**
     * Sign somebody in.
     *
     * **Roles are gone until they are designed again**, so signing in is all
     * it takes. A member is expressed as the abilities they do not have,
     * which is what the screens actually check.
     */
    protected function actingAsRole(string $role = 'developer', int $id = 1): \Illuminate\Auth\GenericUser
    {
        // The admin's chrome shows who is signed in.
        $user = new \Illuminate\Auth\GenericUser(['id' => $id, 'name' => 'Test user']);

        $this->actingAs($user);

        if ($role === 'member') {
            foreach ([Abilities::WRITE_CODE, Abilities::MANAGE_SETTINGS, Abilities::MANAGE_USERS, Abilities::RUN_TASKS] as $ability) {
                \Illuminate\Support\Facades\Gate::define(Abilities::gate($ability), fn () => false);
            }
        }

        return $user;
    }

    /** Take an ability away for this test. */
    protected function withoutAbility(string ...$abilities): void
    {
        foreach ($abilities as $ability) {
            \Illuminate\Support\Facades\Gate::define(Abilities::gate($ability), fn () => false);
        }
    }
}
