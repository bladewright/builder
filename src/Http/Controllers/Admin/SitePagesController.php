<?php

namespace Bladewright\Http\Controllers\Admin;

use Illuminate\Http\Response;
use Illuminate\View\View;
use Bladewright\Models\Page;
use Bladewright\Site\PublicSite;

/**
 * The pages screens: the list, the editor, the settings — and the preview
 * the editor's iframe shows.
 */
class SitePagesController
{
    public function index(): View
    {
        return view('bladewright::admin.pages');
    }

    public function edit(Page $page): View
    {
        return view('bladewright::admin.page-editor', ['page' => $page]);
    }

    /** Renaming, the URL, the layout and the Danger Zone — behind the gear. */
    public function settings(Page $page): View
    {
        return view('bladewright::admin.page-settings', ['page' => $page]);
    }

    /**
     * The page as the site would serve it, **published or not** — this is
     * how the editor sees work in progress. It sits behind the admin's own
     * authentication; a stranger still gets the public 404.
     */
    public function preview(Page $page, PublicSite $site): Response
    {
        // `?editing` stamps every block with its uuid, so the preview can be
        // pressed. The stamps exist only behind this authenticated route.
        return new Response(request()->boolean('editing')
            ? $site->pageForEditing($page)
            : $site->page($page));
    }
}
