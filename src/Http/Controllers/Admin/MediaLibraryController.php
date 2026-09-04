<?php

namespace Bladewright\Http\Controllers\Admin;

use Illuminate\View\View;

class MediaLibraryController
{
    public function index(): View
    {
        return view('bladewright::admin.media');
    }
}
