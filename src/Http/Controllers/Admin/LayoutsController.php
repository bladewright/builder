<?php

namespace Bladewright\Http\Controllers\Admin;

use Illuminate\View\View;
use Bladewright\Models\Layout;

/**
 * The layouts screens: the list, the editor, the settings.
 *
 * **No preview route.** The frame is a whole document already, and the
 * editor shows it from what is on the screen rather than what was last
 * saved — so there is nowhere for the preview and the page to drift apart.
 */
class LayoutsController
{
    public function index(): View
    {
        return view('bladewright::admin.layouts');
    }

    public function edit(Layout $layout): View
    {
        // **Not `layout`**: Blade binds that name inside an anonymous
        // component, and the admin's own shell is one.
        return view('bladewright::admin.layout-editor', ['part' => $layout]);
    }

    public function settings(Layout $layout): View
    {
        return view('bladewright::admin.layout-settings', ['part' => $layout]);
    }
}
