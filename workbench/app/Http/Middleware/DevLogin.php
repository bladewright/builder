<?php

namespace Workbench\App\Http\Middleware;

use Closure;
use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * For checking locally. Not in the package (autoload-dev).
 *
 * The workbench has no host authentication, so the visitor is always user 1.
 * Seeing what the permissions do takes being signed in as somebody.
 */
class DevLogin
{
    public function handle(Request $request, Closure $next)
    {
        Auth::setUser(new GenericUser(['id' => 1, 'name' => 'Development user']));

        return $next($request);
    }
}
