<?php

namespace Bladewright\Tests\Feature;

use Bladewright\Http\Middleware\AdminAuthenticate;
use Bladewright\Tests\TestCase;
use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Letting administrators and members live on different guards.
 *
 * **A customer must not lose the ability to build a members' site.** The admin
 * can sign in on a guard of its own, and what that guard says goes for
 * everything after it.
 *
 * The rest of this — the same id on two guards being two people — was about
 * roles, and comes back when they do.
 */
class AdminGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function defineRoutes($router): void
    {
        parent::defineRoutes($router);
        $router->get('login', fn () => 'login')->name('login');
    }

    /** A guard that returns whoever is signed in, and nothing more. */
    private function fakeGuard(string $name, ?GenericUser $user): void
    {
        Auth::extend($name, fn () => new class($user) implements \Illuminate\Contracts\Auth\Guard
        {
            public function __construct(private ?GenericUser $user) {}

            public function check(): bool
            {
                return $this->user !== null;
            }

            public function guest(): bool
            {
                return ! $this->check();
            }

            public function user(): ?GenericUser
            {
                return $this->user;
            }

            public function id()
            {
                return $this->user?->getAuthIdentifier();
            }

            public function validate(array $credentials = []): bool
            {
                return false;
            }

            public function hasUser(): bool
            {
                return $this->check();
            }

            public function setUser(\Illuminate\Contracts\Auth\Authenticatable $user): void {}
        });

        config(['auth.guards.'.$name => ['driver' => $name]]);
    }

    /**
     * **Giving the admin its own guard breaks nothing.**
     *
     * The Gate (and the `can:` middleware) looks at the user of the current
     * guard. Checking on another guard and waving them through leaves
     * **everything after it looking at somebody else**.
     */
    public function test_the_admin_guard_becomes_the_current_one(): void
    {
        $staff = new GenericUser(['id' => 7]);
        $this->fakeGuard('staff', $staff);
        config(['bladewright.auth.guard' => 'staff']);

        $passed = false;

        (new AdminAuthenticate)->handle(Request::create('/bladewright/pages'), function () use (&$passed) {
            $passed = true;

            return response('ok');
        });

        $this->assertTrue($passed);

        // From here on, `$request->user()` and the Gate see the same person.
        $this->assertSame('staff', Auth::getDefaultDriver());
        $this->assertSame(7, Auth::user()?->getAuthIdentifier());
    }
}
