<?php

namespace Bladewright\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * The admin's front door: it opens on the pages, the site itself.
 */
class PagesController
{
    public function index(): RedirectResponse
    {
        return redirect()->route('bladewright.admin.pages');
    }
}
