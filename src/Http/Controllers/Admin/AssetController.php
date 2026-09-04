<?php

namespace Bladewright\Http\Controllers\Admin;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serve the admin's CSS straight from the package.
 *
 * No vendor:publish is required. It works on a read-only container and
 * composer update delivers changes as they are — the same reasoning as not
 * copying packaged blocks into storage.
 */
class AssetController
{
    private const ALLOWED = [
        // **The admin's own, and nothing else.** No CSS is served to the
        // public site: our concerns stay out of the customer's site, and the
        // preview stays honest.
        'bladewright.css',
        'bladewright.js',
        // **The code editor, apart.** Nine tenths of the admin's JavaScript
        // for a pill most screens never open, so it is fetched only where
        // one is.
        'bladewright-editor.js',
        // The tools that run inside the preview. **They touch none of the
        // site's CSS.**
        'bladewright-builder.js',
    ];

    private const TYPES = [
        'css' => 'text/css',
        'js' => 'text/javascript',
    ];

    public function __invoke(string $file): BinaryFileResponse
    {
        abort_unless(in_array($file, self::ALLOWED, true), 404);

        $path = realpath(__DIR__.'/../../../../resources/dist/'.$file);

        abort_unless($path !== false && is_file($path), 404);

        return response()->file($path, [
            'Content-Type' => self::TYPES[pathinfo($file, PATHINFO_EXTENSION)] ?? 'text/plain',
            'Cache-Control' => 'public, max-age=604800',
        ]);
    }
}
