<?php

namespace Bladewright\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Bladewright\Models\User;
use Bladewright\Tests\TestCase;

/**
 * Signing in to the admin.
 *
 * **Nothing is added to the host application.** Neither `/login` nor the name
 * `login` is taken; it stays at `/bladewright/login`. The people are ours —
 * `bw_users`, on the `bladewright` guard — so the host's members are never
 * let in and never written.
 */
class AdminLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // Checked with the defaults, as the package ships them.
        $app['config']->set('bladewright.admin.middleware', [
            'web',
            \Bladewright\Http\Middleware\AdminAuthenticate::class,
            'can:bladewright.access-admin',
        ]);
    }

    private function user(string $email = 'kanri@example.com', string $password = 'secret-password'): User
    {
        return User::create(['email' => $email, 'password' => $password]);
    }

    /** Not signed in, it goes to our login screen. **Never to the host's /login.** */
    public function test_the_admin_sends_a_stranger_to_our_login(): void
    {
        $this->get('/bladewright')->assertRedirect('/bladewright/login');
    }

    /** A host with no login does not fall over (`auth` would throw here). */
    public function test_it_does_not_need_a_host_login_route(): void
    {
        $this->assertFalse(Route::has('login'));

        $this->get('/bladewright')->assertRedirect('/bladewright/login');
        $this->get('/bladewright/login')->assertOk()->assertSee('Sign in', false);
    }

    /** **The host's /login is not taken.** */
    public function test_we_do_not_claim_the_host_login_path(): void
    {
        $this->get('/login')->assertNotFound();
    }

    public function test_a_user_can_sign_in_and_reach_the_admin(): void
    {
        $user = $this->user();

        $this->post('/bladewright/login', [
            'email' => 'kanri@example.com',
            'password' => 'secret-password',
        ])->assertRedirect('/bladewright');

        $this->assertAuthenticatedAs($user, 'bladewright');
        // The front door opens on the pages — the site itself.
        $this->get('/bladewright')->assertRedirect('/bladewright/pages');
        $this->get('/bladewright/pages')->assertOk();
    }

    /** Back to the screen they were headed for before signing in. */
    public function test_it_returns_to_where_you_were_going(): void
    {
        $user = $this->user();

        $this->get('/bladewright/media')->assertRedirect('/bladewright/login');

        $this->post('/bladewright/login', [
            'email' => 'kanri@example.com',
            'password' => 'secret-password',
        ])->assertRedirect('/bladewright/media');
    }

    /** **It never says which one was wrong**, so no email address is revealed. */
    public function test_a_wrong_password_is_refused_without_saying_why(): void
    {
        $this->user();

        $response = $this->post('/bladewright/login', [
            'email' => 'kanri@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect();
        $this->assertGuest();
        $this->assertStringContainsString(
            'That email address or password is wrong',
            session('errors')->first(),
        );
    }

    /** Brute force is stopped. */
    public function test_too_many_attempts_are_throttled(): void
    {
        $this->user();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/bladewright/login', ['email' => 'kanri@example.com', 'password' => 'nope']);
        }

        $this->post('/bladewright/login', [
            'email' => 'kanri@example.com',
            'password' => 'secret-password',
        ]);

        $this->assertGuest();

        RateLimiter::clear('bw-login:kanri@example.com|127.0.0.1');
    }

    /** Already signed in, they walk straight in — the same session. */
    public function test_an_existing_session_is_accepted(): void
    {
        $user = $this->user();

        $this->actingAs($user);

        $this->get('/bladewright/media')->assertOk();
    }

    public function test_a_user_can_sign_out(): void
    {
        $user = $this->user();
        $this->actingAs($user);

        $this->post('/bladewright/logout')->assertRedirect('/bladewright/login');

        $this->assertGuest();
    }

}
