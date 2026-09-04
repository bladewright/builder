<?php

namespace Bladewright\Support;

use InvalidArgumentException;
use Bladewright\Models\Setting;

/**
 * Google Analytics, from one measurement id.
 *
 * **The id is data; the script is ours.** Nothing pasted, nothing stored
 * that runs — the owner hands over `G-XXXXXXX` and `@bwanalytics` writes
 * the two script lines Google documents, on the public pages only:
 * **a preview is not a visit**, so the admin's own screens send nothing.
 */
class Analytics
{
    private const KEY = 'bladewright.analytics_id';

    /** The shape Google hands out for a GA4 measurement id. */
    private const SHAPE = '/^G-[A-Z0-9]{4,20}$/i';

    private ?string $id = null;

    public function get(): string
    {
        if ($this->id !== null) {
            return $this->id;
        }

        try {
            $said = (string) (Setting::query()->where('key', self::KEY)->value('value') ?? '');
        } catch (\Throwable) {
            // Before the migrations there is nothing to read.
            $said = '';
        }

        // Only the shape Google hands out ever reaches a page.
        return $this->id = preg_match(self::SHAPE, $said) === 1 ? $said : '';
    }

    /** An empty id turns it off; anything else has to look like Google's. */
    public function save(string $id): void
    {
        $id = trim($id);

        if ($id !== '' && preg_match(self::SHAPE, $id) !== 1) {
            throw new InvalidArgumentException(__('[:id] does not look like a measurement id (G-XXXXXXXXXX).', ['id' => $id]));
        }

        Setting::query()->updateOrCreate(['key' => self::KEY], ['value' => $id]);

        $this->id = $id;
    }

    /** What `@bwanalytics` writes into a head — or nothing at all. */
    public function scriptTags(): string
    {
        $id = $this->get();

        if ($id === '') {
            return '';
        }

        // **A preview is not a visit.** The admin looking at a page must not
        // count as traffic, so the admin's own routes get silence.
        try {
            if (request()->routeIs('bladewright.admin.*')) {
                return '';
            }
        } catch (\Throwable) {
            // No request to ask (the console, a queue): serve it.
        }

        $safe = e($id);

        return '<script async src="https://www.googletagmanager.com/gtag/js?id='.$safe.'"></script>'."\n"
            .'    <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments)}gtag(\'js\',new Date());gtag(\'config\',\''.$safe.'\')</script>';
    }
}
