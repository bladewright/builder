<?php

namespace Bladewright\Support;

/**
 * The URL of the CSS the package serves.
 *
 * **Always with a version.** It is served with a seven-day cache, so without
 * one a fix to the CSS in `composer update` would take a week to reach a
 * visitor (it happened: fixed here, and the old look kept coming out).
 */
class Assets
{
    public static function url(string $file): string
    {
        return route('bladewright.admin.asset', $file).'?v='.self::version($file);
    }

    /** A short mark that changes when the contents do. With no file, the date stands in. */
    public static function version(string $file): string
    {
        $path = __DIR__.'/../../resources/dist/'.$file;

        return is_file($path)
            ? substr(hash_file('xxh128', $path), 0, 8)
            : date('Ymd');
    }
}
