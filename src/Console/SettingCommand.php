<?php

namespace Bladewright\Console;

use Illuminate\Console\Command;
use Bladewright\Bladewright;
use Bladewright\Support\Settings;

/**
 * The site's settings. One is left: **the language new pages are born in.**
 *
 * The rest of what stood here went to where it already lived (the owner's
 * table, 2026-09-02): env and debug are `php artisan about`'s answer and
 * `.env`'s to change, the app's name is the host developer's, the brand in
 * the header is the layout's own value, and whether the site has a dark
 * scheme is read from the layout's CSS rather than declared.
 */
class SettingCommand extends Command
{
    protected $signature = 'bladewright:setting
        {--locale= : The language new pages are born in (en, ja, …). "" follows the app again}';

    protected $description = "The site's language for new pages";

    public function handle(Settings $settings): int
    {
        $locale = $this->option('locale');

        if ($locale === null) {
            return $this->show($settings);
        }

        if ($locale === '') {
            // **Back to following the app.** The override goes; app.locale —
            // the answer the Laravel developer already gave — speaks again.
            $settings->forget('bladewright.locale');

            $this->components->info('New pages follow the application again: '.Bladewright::locale().'.');

            return self::SUCCESS;
        }

        if (preg_match('/^[A-Za-z]{2,8}(-[A-Za-z0-9]{2,8})*$/', $locale) !== 1) {
            $this->components->error("[{$locale}] does not look like a language code (en, ja, pt-BR …).");

            return self::FAILURE;
        }

        $settings->set('bladewright.locale', $locale);

        $this->components->info("New pages are born in [{$locale}]. Existing pages keep their own language.");

        return self::SUCCESS;
    }

    /** What it is now, and **where the answer comes from**. */
    private function show(Settings $settings): int
    {
        $this->components->twoColumnDetail('locale for new pages', Bladewright::locale());
        $this->components->twoColumnDetail('<fg=gray>decided by</>', match (true) {
            $settings->get('bladewright.locale') !== null => 'this setting (--locale="" follows the app again)',
            (bool) config('bladewright.locale') => 'BLADEWRIGHT_LOCALE in .env',
            default => 'the application (app.locale)',
        });

        return self::SUCCESS;
    }
}
