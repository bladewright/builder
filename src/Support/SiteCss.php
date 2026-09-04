<?php

namespace Bladewright\Support;

use Bladewright\Models\Setting;

/**
 * The site's one stylesheet, kept in the database and served at a URL.
 *
 * **This is where what an inline style cannot say gets said**: hover and
 * focus, media queries, a class shared by twenty blocks. To everybody but
 * the filesystem it is a file — the layout links it like any other — but it
 * lives with the rest of the site's content, written from the admin and
 * gone with an uninstall.
 */
class SiteCss
{
    private const KEY = 'bladewright.site_css';

    private ?string $css = null;

    public function get(): string
    {
        if ($this->css !== null) {
            return $this->css;
        }

        try {
            return $this->css = (string) (Setting::query()->where('key', self::KEY)->value('value') ?? '');
        } catch (\Throwable) {
            // **A stylesheet is not worth a white screen.** Before the
            // migrations, or during a database incident, there simply is none.
            return '';
        }
    }

    public function save(string $css): void
    {
        Setting::query()->updateOrCreate(['key' => self::KEY], ['value' => $css]);

        $this->css = $css;
    }

    /**
     * A word that changes when the CSS does, for the link's `?v=`.
     *
     * **Browsers may cache the stylesheet hard**, because a new version is a
     * new URL.
     */
    public function version(): string
    {
        return substr(md5($this->get()), 0, 12);
    }
}
