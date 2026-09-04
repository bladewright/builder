<?php

namespace Bladewright\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Bladewright\Site\PublicSite;

/**
 * Showing a published page.
 *
 * There is one route (the fallback) and one controller; the page itself
 * comes whole out of the four-layer model (`Site\PublicSite`), resolved by
 * uuid at the moment of the request. No path with a published page: 404,
 * with no distinction between not existing and not being published — a 403
 * would leak that a page is there.
 */
class SitePageController
{
    public function __invoke(Request $request, PublicSite $site): Response
    {
        $response = $site->respond($request->path());

        abort_if($response === null, 404);

        return $response;
    }
}
