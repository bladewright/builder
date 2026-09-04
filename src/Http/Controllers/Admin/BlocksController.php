<?php

namespace Bladewright\Http\Controllers\Admin;

use Illuminate\View\View;
use Bladewright\Models\Block;

/**
 * The blocks screens: the list, the editor, the settings.
 *
 * The first screens of the four-layer world. **The content lives here** —
 * the commands build skeleton, and what a block says is written on these
 * screens (the owner's rule: screen work is too fine for a terminal).
 */
class BlocksController
{
    public function index(): View
    {
        return view('bladewright::admin.blocks');
    }

    public function edit(Block $block): View
    {
        return view('bladewright::admin.block-editor', ['block' => $block]);
    }

    /** Renaming and the Danger Zone live here, behind the gear — not under the editor. */
    public function settings(Block $block): View
    {
        return view('bladewright::admin.block-settings', ['block' => $block]);
    }
}
