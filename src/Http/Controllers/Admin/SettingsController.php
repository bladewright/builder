<?php

namespace Bladewright\Http\Controllers\Admin;

use Illuminate\View\View;

/**
 * The settings: a hall of doors, and the rooms behind them.
 *
 * **The index configures nothing** — the settings keep growing, so each one
 * works in a room of its own and the index only says where they are.
 */
class SettingsController
{
    public function index(): View
    {
        return view('bladewright::admin.settings.index');
    }

    public function colours(): View
    {
        return view('bladewright::admin.settings.colours');
    }

    public function stylesheet(): View
    {
        return view('bladewright::admin.settings.stylesheet');
    }

    public function application(): View
    {
        return view('bladewright::admin.settings.application');
    }

    public function analytics(): View
    {
        return view('bladewright::admin.settings.analytics');
    }

    /** The site as files somebody can put anywhere. */
    public function export(): View
    {
        return view('bladewright::admin.settings.export');
    }
}
