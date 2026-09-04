<?php

namespace Bladewright;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Bladewright\Http\Controllers\SitePageController;
use Bladewright\Site\PageRoutes;
use Bladewright\Site\PublicSite;

/**
 * The way in for a host application to call a Bladewright page directly.
 *
 * **Only one fallback survives.** They all share a URI, so they land on the
 * same key in the route table and whichever registers last erases the one
 * before. A customer with an SPA catch-all swallows every page of ours.
 *
 * On such a site, the customer calls this from inside their own fallback:
 *
 *   Route::fallback(function (Request $request) {
 *       return Bladewright::page($request) ?? view('spa');
 *   });
 */
class Bladewright
{
    /**
     * The published page for that request, or null.
     *
     * **Returning null is the point.** Throwing a 404 would stop the rest of
     * the customer's fallback — their SPA shell, their own error page — from
     * ever running.
     */
    public static function page(?Request $request = null): ?Response
    {
        $request ??= request();

        if (app(PageRoutes::class)->match($request->path()) === null) {
            return null;
        }

        return app()->call([app(SitePageController::class), '__invoke'], ['request' => $request]);
    }

    /**
     * The language new pages are born in.
     *
     * **One rule, one place**: the site's own setting when there is one
     * (database or BLADEWRIGHT_LOCALE), the application's locale otherwise —
     * the answer the Laravel developer already gave.
     */
    public static function locale(): string
    {
        return config('bladewright.locale') ?: config('app.locale', 'en');
    }

    /** Is there a published page at that path (checked without rendering)? */
    public static function has(string $path): bool
    {
        $found = app(PageRoutes::class)->match(trim($path, '/'));

        return $found !== null && $found[0]->is_published;
    }
}
