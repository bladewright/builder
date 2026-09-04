<?php

namespace Bladewright\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Bladewright\Access\Abilities;

/**
 * Signing in to the admin.
 *
 * **We do not build authentication.** The host application's guard and users
 * are used as they are; all we add is a screen and a way in. Nothing is
 * written.
 */
class LoginController
{
    /** Slow brute force down, without locking anyone out for long. */
    private const ATTEMPTS = 5;

    private const DECAY = 60;

    public function show(Request $request): View|RedirectResponse
    {
        if (! $this->user()) {
            return view('bladewright::admin.login');
        }

        // Signed in, but not allowed into the admin.
        // **Better than a 403: say why they cannot get in.**
        if (! Gate::allows(Abilities::gate(Abilities::ACCESS_ADMIN))) {
            return view('bladewright::admin.login', [
                'denied' => __('You are signed in, but you are not allowed into the admin.'),
            ]);
        }

        return redirect()->intended(route('bladewright.admin.home'));
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $key = 'bw-login:'.Str::lower($credentials['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, self::ATTEMPTS)) {
            return back()->withInput($request->only('email'))->withErrors([
                'email' => __('Too many attempts. Try again in :seconds seconds.', [
                    'seconds' => RateLimiter::availableIn($key),
                ]),
            ]);
        }

        $guard = Auth::guard(config('bladewright.auth.guard'));

        if (! $guard->attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($key, self::DECAY);

            // **Never say which one was wrong.** It would reveal which email
            // addresses exist.
            return back()->withInput($request->only('email'))->withErrors([
                'email' => __('That email address or password is wrong.'),
            ]);
        }

        RateLimiter::clear($key);

        // Prevent session fixation.
        $request->session()->regenerate();

        return redirect()->intended(route('bladewright.admin.home'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard(config('bladewright.auth.guard'))->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('bladewright.admin.login');
    }

    private function user(): mixed
    {
        return Auth::guard(config('bladewright.auth.guard'))->user();
    }
}
