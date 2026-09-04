<?php

namespace Bladewright\Http\Controllers;

use Illuminate\Http\Response;
use Bladewright\Support\SiteCss;

/**
 * The site's stylesheet, served as the file it is to everybody but us.
 *
 * **Public, like the media**: it dresses the published pages. Cached hard,
 * because a change makes a new URL (`?v=`).
 */
class SiteCssController
{
    public function __invoke(SiteCss $css): Response
    {
        return new Response($css->get(), 200, [
            'Content-Type' => 'text/css; charset=utf-8',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
