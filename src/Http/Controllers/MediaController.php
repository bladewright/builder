<?php

namespace Bladewright\Http\Controllers;

use Illuminate\Http\Request;
use Bladewright\Media\MediaLibrary;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serve a file, but only from a disk nothing outside can read.
 *
 * **A disk with URLs of its own never comes through here** (so our paths stay
 * off the customer's pages). This is the road for private local storage, or
 * any setup without public URLs.
 *
 * The path carries a fingerprint of the contents, which means **what a URL
 * returns never changes**, so it can be cached for a long time.
 */
class MediaController
{
    public function __invoke(Request $request, MediaLibrary $library, string $path): Response|StreamedResponse
    {
        // Nothing outside where we put things is served (.. is refused too).
        abort_unless($library->owns($path), 404);

        $file = $library->find($path);

        abort_if($file === null, 404);

        $etag = '"'.substr(sha1($file->path), 0, 32).'"';

        if ($request->headers->get('If-None-Match') === $etag) {
            return response('', 304, ['ETag' => $etag]);
        }

        return $library->disk()->response($file->path, $file->name, [
            'Content-Type' => $file->mime,
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'ETag' => $etag,
        ], 'inline');
    }
}
