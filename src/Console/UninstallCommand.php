<?php

namespace Bladewright\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Bladewright\Support\SiteReset;

/**
 * Take Bladewright out (the owner's table, 2026-09-02).
 *
 * Everything of ours goes: the `bw_` tables — pages, history, accounts — the
 * record that our migrations ran, the checkouts in storage, the cached
 * routes. **Nothing of the host's is touched**; there is nothing of ours in
 * their source tree to remove, which is what promise 1 was for.
 *
 * Two questions stand in the way: whether the uploaded files go too (they
 * are the one thing that cannot be brought back), and **the site's name,
 * typed** — this is the heaviest thing the package can do, and a yes is far
 * too light for it. The one step left afterwards is the person's own:
 * `composer remove bladewright/bladewright`.
 */
class UninstallCommand extends Command
{
    protected $signature = 'bladewright:uninstall';

    protected $description = 'Remove the site: tables, history, accounts, storage';

    public function handle(SiteReset $reset, Filesystem $files): int
    {
        $counts = $reset->counts();

        $this->components->warn('This removes the site Bladewright holds. It cannot be undone.');
        $this->components->twoColumnDetail('tables to drop', (string) count($reset->tables()));
        $this->components->twoColumnDetail('pages', (string) $counts['pages']);
        $this->components->twoColumnDetail('layouts / components / blocks', $counts['layouts'].' / '.$counts['components'].' / '.$counts['blocks']);
        $this->components->twoColumnDetail('accounts', (string) $counts['accounts']);
        $this->components->twoColumnDetail('uploaded files', (string) $counts['media']);
        $this->components->bulletList(["Nothing outside Bladewright's own bw_ tables and storage is touched"]);

        if (! $this->canAsk()) {
            $this->components->error("Nothing was done. Removing needs the site's name typed, and there is nobody to type it.");

            return self::FAILURE;
        }

        // **Media is its own question.** It cannot be restored from anything,
        // so it is never bundled into the bigger yes.
        $media = $this->components->confirm('Delete the uploaded files too?', false);

        if ($this->getLaravel()->environment('production')) {
            $this->components->warn('This is production.');
        }

        $name = $this->siteName();

        if ($this->components->ask("Type the site's name ({$name}) to remove Bladewright's data") !== $name) {
            $this->components->error('That is not the name. Nothing was done.');

            return self::FAILURE;
        }

        $reset->wipe($media);

        // An uninstall leaves nothing standing but what was chosen to stay.
        $root = (string) config('bladewright.root');

        if ($media && $root !== '') {
            $files->deleteDirectory($root);
        }

        $this->components->info('Bladewright\'s tables are gone.');

        $this->components->bulletList(array_filter([
            'composer remove bladewright/bladewright finishes it',
            $media ? null : 'The uploaded files are still at '.$root.'/media',
        ]));

        return self::SUCCESS;
    }

    /** What has to be typed. The site's name, or the database's when there is none. */
    private function siteName(): string
    {
        $name = trim((string) config('app.name'));

        return $name !== '' ? $name : (string) (config('bladewright.database.connection') ?: config('database.default'));
    }

    /** Is there anyone to ask? (The same rule as the install's.) */
    private function canAsk(): bool
    {
        if ($this->getLaravel()->runningUnitTests()) {
            return true;
        }

        return $this->input->isInteractive()
            && (! \function_exists('stream_isatty') || @stream_isatty(STDIN));
    }
}
