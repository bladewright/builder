<?php

namespace Bladewright\Http\Controllers\Admin;

use Illuminate\View\View;
use Bladewright\Models\Structure;

/**
 * The components screens: the list, the editor, the settings.
 *
 * **No preview route.** The editor renders the component itself, from what
 * is on the screen rather than what was last saved, so there is nowhere for
 * the preview and the page to drift apart.
 *
 * (`Structure` is the model's transitional name while the word `Component`
 * is being freed. Every word a person sees says component.)
 */
class ComponentsController
{
    public function index(): View
    {
        return view('bladewright::admin.components');
    }

    public function edit(Structure $component): View
    {
        // **Not `component`**: Blade binds that name inside an anonymous
        // component, and the layout is one.
        return view('bladewright::admin.component-editor', ['part' => $component]);
    }

    public function settings(Structure $component): View
    {
        return view('bladewright::admin.component-settings', ['part' => $component]);
    }
}
