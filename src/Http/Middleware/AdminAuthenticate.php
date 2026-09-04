<?php

namespace Bladewright\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * The admin is for signed-in people only.
 *
 * This exists instead of Laravel's `auth` so that **where an unsigned-in
 * visitor lands is always our login screen**. `auth` redirects to
 * `route('login')`, which breaks when the host application has no login and
 * lands on the host's own screen when it does.
 *
 * **Nothing is added to the host application.** Neither `/login` nor the name
 * `login` is taken, and `config/auth.php` is untouched. The session and the
 * guard are the host's own, so anyone already signed in there walks straight
 * in.
 */
class AdminAuthenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        $guard = config('bladewright.auth.guard');

        if (Auth::guard($guard)->check()) {
            // **From here on, this is the person on this guard.**
            // The Gate (and the `can:` middleware) looks at the user of the
            // default guard, so an admin on a guard of its own would have it
            // **looking at somebody else**. Switching once here lines up
            // `$request->user()` and every permission check.
            if ($guard !== null) {
                Auth::shouldUse($guard);
            }

            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(401);
        }

        // Remember where they were headed, and send them there after signing in.
        $request->session()->put('url.intended', $request->fullUrl());

        return redirect()->route('bladewright.admin.login');
    }
}
