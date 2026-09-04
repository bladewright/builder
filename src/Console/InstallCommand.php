<?php

namespace Bladewright\Console;

use Illuminate\Console\Command;
use Bladewright\Blocks\BlockManager;
use Bladewright\Blocks\LayoutManager;
use Bladewright\Blocks\SitePages;
use Bladewright\Blocks\StructureManager;
use Bladewright\Models\Block;
use Bladewright\Models\Layout;
use Bladewright\Models\Page;
use Bladewright\Models\Structure;
use Bladewright\Support\Settings;
use Bladewright\Support\SiteReset;

/**
 * Get Bladewright ready, **asking as it goes** (the owner's table, 2026-09-02).
 *
 * One command, no options to memorise: it says which database the site will
 * live in, runs **our migrations and nobody else's**, asks what language the
 * site writes in, builds the welcome page at `/` out of the four-layer
 * model's own pieces, and offers to create the first account.
 *
 * `--fresh` is the one flag: wipe the site and install it again. It asks for
 * the site's name to be typed — a yes is far too light for it — and the
 * uploaded files always stay.
 */
class InstallCommand extends Command
{
    protected $signature = 'bladewright:install
        {--fresh : Wipe the site and install it again (pages, parts, accounts — the uploaded files stay)}';

    protected $description = 'Get Bladewright ready (tables, welcome page, first account)';

    public function handle(SiteReset $reset, Settings $settings): int
    {
        // **Which database, before anything touches one.** Saying it after
        // the migrations have run is telling somebody where the train went.
        $this->components->twoColumnDetail('database', $this->databaseLine());

        if (config('bladewright.database.connection') === null) {
            $this->components->warn(
                "The site's content (pages, parts, accounts) goes into the same database as this app, "
                .'and it exists nowhere else — it was written in the browser, not in a repository. '
                .'migrate:fresh / migrate:refresh will take the whole site with them. '
                .'To keep them apart, point BLADEWRIGHT_DB_CONNECTION at another connection.',
            );
        }

        if ($this->option('fresh') && ! $this->startFresh($reset, $settings)) {
            return self::FAILURE;
        }

        // **Our migrations and nobody else's.** A bare `migrate` would carry
        // the host's own pending migrations along with it — half-written
        // work of theirs, run because somebody installed a package.
        // **Every time, not only the first.** `migrate` runs what is pending
        // and nothing else, so this is also how a site that was installed
        // months ago gets the tables a newer Bladewright brought with it.
        // Guarding it on "are we installed?" left those sites broken, with
        // the screens asking for a table nobody had made.
        $this->components->info($this->tablesExist() ? 'Running any new migrations…' : 'Running migrations…');
        $this->call('migrate', [
            '--force' => true,
            '--path' => realpath(__DIR__.'/../../database/migrations'),
            '--realpath' => true,
        ]);

        // **What language the site writes in**, before the first page is born
        // in the wrong one.
        $this->askForLanguage($settings);

        // **What the site's CSS is written in**, before the first frame is
        // born speaking the wrong one.
        $this->askForFramework();

        $result = $this->installWelcome();

        $this->makeFirstUser();

        return $result;
    }

    /**
     * Wipe the site, then let the install put it back.
     *
     * **`migrate:fresh` is not what happens here.** Only what begins with
     * `bw_` goes (`SiteReset`), and **the uploaded files always stay** —
     * they are the one thing nothing can bring back.
     */
    private function startFresh(SiteReset $reset, Settings $settings): bool
    {
        $counts = $reset->counts();

        $this->components->warn('This wipes the site and installs it again. It cannot be undone.');
        $this->components->twoColumnDetail('tables to drop', (string) count($reset->tables()));
        $this->components->twoColumnDetail('pages', (string) $counts['pages']);
        $this->components->twoColumnDetail('layouts / components / blocks', $counts['layouts'].' / '.$counts['components'].' / '.$counts['blocks']);
        $this->components->twoColumnDetail('accounts', (string) $counts['accounts']);
        $this->components->twoColumnDetail('uploaded files', $counts['media'].' — kept');
        $this->components->bulletList(["Nothing outside the site's own bw_ tables is touched"]);

        if (! $this->confirmFresh()) {
            return false;
        }

        $reset->wipe(false);

        // The settings were in a table that has just gone. What was
        // remembered from it has to go with them.
        $settings->flush();

        $this->components->info('The site is wiped.');

        return true;
    }

    /**
     * Make sure the person meant it.
     *
     * **A yes is far too light for this.** The site's name is typed out, the
     * way GitHub asks for a repository's name. With no terminal there is
     * nobody to type it, so nothing is done.
     */
    private function confirmFresh(): bool
    {
        if (! $this->canAsk()) {
            $this->components->error("Nothing was done. Wiping needs the site's name typed, and there is nobody to type it.");

            return false;
        }

        if ($this->getLaravel()->environment('production')) {
            $this->components->warn('This is production.');
        }

        $name = $this->siteName();

        return $this->components->ask("Type the site's name ({$name}) to wipe it") === $name;
    }

    private function siteName(): string
    {
        $name = trim((string) config('app.name'));

        return $name !== '' ? $name : (string) (config('bladewright.database.connection') ?: config('database.default'));
    }

    private function databaseLine(): string
    {
        $chosen = config('bladewright.database.connection');
        $name = $chosen ?: (string) config('database.default');

        return $chosen ? $name.' (separate)' : $name.' (same as the app)';
    }

    /**
     * Is there anyone to ask?
     *
     * **Asking without a terminal simply hangs.** Tests always may ask —
     * their questions are scripted, and a terminal that comes and goes with
     * the CI runner must not decide what a test does.
     */
    private function canAsk(): bool
    {
        if ($this->getLaravel()->runningUnitTests()) {
            return true;
        }

        return $this->input->isInteractive()
            && (! \function_exists('stream_isatty') || @stream_isatty(STDIN));
    }

    /** Are the tables there already? **No connection means no migration.** */
    private function tablesExist(): bool
    {
        try {
            return \Illuminate\Support\Facades\Schema::connection(
                config('bladewright.database.connection')
            )->hasTable('bw_pages');
        } catch (\Throwable) {
            return true;
        }
    }

    /**
     * What language the site writes in.
     *
     * The default is **the answer the Laravel developer already gave**
     * (app.locale); answering the same stores nothing. A different answer
     * goes into the site's own setting. With nobody to ask, the default
     * stands.
     */
    private function askForLanguage(Settings $settings): void
    {
        if (config('bladewright.locale') !== null || $settings->get('bladewright.locale') !== null) {
            return;
        }

        if (! $this->canAsk()) {
            return;
        }

        $default = (string) config('app.locale', 'en');
        $answer = (string) $this->components->ask('What language does this site write in? (en, ja, …)', $default);

        if ($answer === '' || $answer === $default) {
            return;
        }

        if (preg_match('/^[A-Za-z]{2,8}(-[A-Za-z0-9]{2,8})*$/', $answer) !== 1) {
            $this->components->warn("[{$answer}] does not look like a language code; staying with {$default}. bladewright:setting --locale= changes it later.");

            return;
        }

        $settings->set('bladewright.locale', $answer);
        $this->components->twoColumnDetail('language', $answer);
    }

    /**
     * What the site's CSS is written in — **one answer for the whole site.**
     *
     * A block is placed anywhere (bottom-up), but what its classes mean
     * comes from whatever stylesheet wraps the page (top-down) — so the
     * frameworks cannot be a layout's to pick. The frames read this through
     * `@bwframework`, and the editors' previews read the same declaration.
     *
     * Asked every install with the standing answer as the default, because
     * running the install again is how it is changed. With nobody to ask,
     * what stands, stands.
     */
    private function askForFramework(): void
    {
        $framework = app(\Bladewright\Support\Framework::class);

        if (! $this->canAsk()) {
            $this->components->twoColumnDetail('framework', \Bladewright\Support\Framework::NAMES[$framework->get()]);

            return;
        }

        $names = array_values(\Bladewright\Support\Framework::NAMES);

        $answer = (string) $this->choice(
            "What is the site's CSS written in?",
            $names,
            \Bladewright\Support\Framework::NAMES[$framework->get()],
        );

        $framework->save($answer);
        $this->components->twoColumnDetail('framework', $answer);
    }

    /**
     * The welcome page at `/`, built out of the model's own pieces:
     * a layout in the site's framework, sections, Markdown and a button —
     * published.
     *
     * **Not a question.** A site starts with something to look at and to
     * rewrite, the way Laravel itself greets you. A site that already has
     * anything is left alone.
     *
     * **It is made the way the screens would make it**, and nothing else:
     * no template file, no special case in the renderer. Everything here can
     * be pulled apart on the screens the moment it is seen — which is the
     * point of greeting somebody with it.
     */
    private function installWelcome(): int
    {
        if (Page::query()->exists() || Layout::query()->exists()) {
            $this->components->info('The site already has content, so no welcome page goes in.');

            return self::SUCCESS;
        }

        try {
            // **The glow is a palette colour**, not a hard-coded style: the
            // hero wears it by name, so the Colours room can retune the
            // whole first impression later.
            $palette = app(\Bladewright\Support\Palette::class);
            $palette->save($palette->all() + [
                'glow' => 'linear-gradient(135deg, #14163f 0%, #3538cd 55%, #7f56d9 100%)',
            ]);

            $layout = app(LayoutManager::class)->create('bladewright', 'header');

            // **The header and the footer are components**, worn by the
            // frame — so they are changed on their own screen, and every
            // page in the frame changes with them.
            $layouts = app(LayoutManager::class);
            $layouts->wear($layout, 'header', $this->welcomeHeader());
            $layouts->wear($layout, 'footer', $this->welcomeFooter());

            $pages = app(SitePages::class);
            $page = $pages->create('bladewright-home', '', 'bladewright');

            foreach ($this->welcomeSections() as $section) {
                $pages->insertComponent($page, $section);
            }

            $pages->publish($page);
        } catch (\Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info('The welcome page is at /.');
        $this->components->bulletList([
            'The layout, the sections and the words all belong to this site. Rewrite them freely',
            'Nothing on it is special: every piece is a block you can open and change',
        ]);

        $this->sayWhatTheApplicationStillNeeds();

        return self::SUCCESS;
    }

    /**
     * **What the application has not done for itself yet.**
     *
     * We run our own migrations and no more — the application's own are its
     * developer's business, and running them uninvited would be reaching
     * into somebody else's house. But on a database that has never been
     * migrated, the site answers 500 the moment it is opened, and the
     * install just said everything went well. So it is said here, before
     * anybody goes looking.
     */
    private function sayWhatTheApplicationStillNeeds(): void
    {
        $missing = [];

        foreach ($this->tablesTheApplicationNeeds() as $table => $why) {
            if (! $this->tableIsThere($table)) {
                $missing[] = $why;
            }
        }

        if ($missing === []) {
            return;
        }

        $this->components->warn('This application has not run its own migrations yet.');
        $this->components->bulletList([...$missing, 'php artisan migrate makes them, and then the site opens']);
    }

    /**
     * The application's own tables this site cannot be looked at without,
     * and only the ones its own settings actually ask for.
     *
     * @return array<string, string>
     */
    private function tablesTheApplicationNeeds(): array
    {
        $wanted = [];

        if (config('session.driver') === 'database') {
            $wanted[(string) config('session.table', 'sessions')] = 'Sessions are kept in the database, and that table is not there — every page would answer 500';
        }

        if (config('cache.default') === 'database') {
            $table = (string) config('cache.stores.database.table', 'cache');
            $wanted[$table] = 'The cache is kept in the database, and that table is not there';
        }

        return $wanted;
    }

    private function tableIsThere(string $table): bool
    {
        try {
            return \Illuminate\Support\Facades\Schema::hasTable($table);
        } catch (\Throwable) {
            // A connection that cannot be asked is not a missing table, and
            // guessing aloud would only send somebody the wrong way.
            return true;
        }
    }

    /**
     * The band across the top. **The component brings its own tag** — this
     * is the `<header>` itself, its rule drawn on its own Style card, so
     * everything about the band is one screen's business.
     */
    private function welcomeHeader(): Structure
    {
        // Side by side is the grid's job now.
        $header = $this->section('bladewright-header', [
            'padding' => '0.75rem 1.25rem',
            'border-width' => '1px',
            'border-color' => 'rule',
            'border-sides' => 'bottom',
        ], layout: 'grid', gap: '1.5rem', width: '68rem', type: 'header');

        $this->put($header, $this->markdown('bladewright-name', '[**Site name**](/)'));

        // **The menu is a nav** — a component of its own inside the header,
        // the way the four layers say it: the words live in a markdown, the
        // meaning lives on the tag around them.
        $nav = $this->section('bladewright-nav', [], type: 'nav');
        $this->put($nav, $this->markdown('bladewright-menu', '[Home](/)'));

        app(StructureManager::class)->insertComponent($header, $nav);

        return $header;
    }

    /** The band along the bottom — a `<footer>` of its own, rule and all. */
    private function welcomeFooter(): Structure
    {
        $footer = $this->section('bladewright-footer', [
            'padding' => '1.5rem 1.25rem',
            'border-width' => '1px',
            'border-color' => 'rule',
            'border-sides' => 'top',
        ], width: '68rem', type: 'footer');

        $this->put($footer, $this->markdown('bladewright-small-print', '© Site name'));

        return $footer;
    }

    /**
     * The three sections the front page is made of.
     *
     * @return array<int, Structure>
     */
    private function welcomeSections(): array
    {
        $admin = '/'.trim((string) config('bladewright.admin.prefix', 'bladewright'), '/');

        // What it is, and the way in. **Spacing lives on the component**, so
        // the words and the button carry none of their own. The band wears
        // the palette's glow, and its ink turns white to stand on it.
        $hero = $this->section('bladewright-hero', [
            'padding' => '5.5rem 1.25rem 6rem',
            'align' => 'center',
            'background' => 'glow',
            'css' => 'color:#ffffff',
        ], width: '68rem');

        $this->put($hero, $this->markdown('bladewright-title', <<<'MD'
        # Edit a live site from the browser.

        A CMS for Laravel. The pages, the frames around them and the words
        inside them are all written from the admin — no editor, no deploy,
        straight onto the published page.
        MD));

        // **The Class card's words, on the seeded way in.** On a Bootstrap
        // site the button stands light on the glowing band — and the first
        // thing anybody opens shows what the Class card is for. Pico and
        // plain dress a bare <button> themselves, so they get one.
        $this->put($hero, $this->block('bladewright-open-admin', 'button', [
            'label' => 'Open the admin',
            'type' => 'link',
            'url' => $admin,
            'class' => app(\Bladewright\Support\Framework::class)->get() === 'bootstrap' ? 'btn btn-light btn-lg fw-semibold' : '',
        ]));

        // The four layers, side by side — a component laying its contents out
        // in a grid, which is the only spacing in the whole model. The band
        // fades from a whisper of the accent down to plain paper.
        // **A light island stays a light island**: its ink is pinned, so a
        // dark scheme cannot strand pale words on the pale band.
        $layers = $this->section('bladewright-layers', [
            'padding' => '4rem 1.25rem',
            'color' => 'ink',
            'css' => 'background:linear-gradient(180deg,#f6f7fd 0%,#ffffff 100%)',
        ], layout: 'grid', gap: '1.25rem', width: '68rem');

        // **Each layer is a card**, said entirely with the Style card:
        // paper, a rule, corners and padding — nothing a person could not
        // redo from the screens.
        $card = [
            'background' => 'paper',
            'border-width' => '1px',
            'border-color' => 'rule',
            'border-radius' => '0.75rem',
            'padding' => '1.5rem',
        ];

        $this->put($layers, $this->markdown('bladewright-layer-layout', style: $card, body: <<<'MD'
        ### Layout

        Where the parts of a page sit — header, nav, main, footer. **This
        frame is one**, and every page wearing it follows when it changes.
        MD));

        $this->put($layers, $this->markdown('bladewright-layer-component', style: $card, body: <<<'MD'
        ### Component

        A structure that means something: a section, an article, a form. It
        arranges blocks, and **the spacing lives here and nowhere else**.
        MD));

        $this->put($layers, $this->markdown('bladewright-layer-block', style: $card, body: <<<'MD'
        ### Block

        One element, and only one — an image, a button, a field. **A block
        edited once changes every page showing it.**
        MD));

        $this->put($layers, $this->markdown('bladewright-layer-markdown', style: $card, body: <<<'MD'
        ### Markdown

        The words themselves — headings, lists, links, emphasis. **You are
        reading one now**, and it is four presses away from being different.
        MD));

        $next = $this->section('bladewright-next', ['padding' => '3.5rem 1.25rem 4.5rem'], width: '68rem');

        $this->put($next, $this->markdown('bladewright-next-steps', <<<MD
        ## Change this page

        1. Open [the admin]({$admin}) and sign in
        2. **Blocks** holds these words. Open `bladewright-title` and write your own
        3. **Components** holds the sections; drag what is inside them into order
        4. **Pages** is where this page lives, and where it is published

        Every screen shows the HTML it comes out as, and lets you write over
        it — the button above is written that way. Nothing here is a template
        file: it is all yours from the first minute.
        MD));

        return [$hero, $layers, $next];
    }

    /**
     * A section: its look on the Style card's terms, and a grid when its
     * contents stand side by side.
     *
     * @param  array<string, string>  $style
     */
    private function section(string $name, array $style, string $layout = 'stack', string $gap = '', string $width = '', string $type = 'section'): Structure
    {
        $section = app(StructureManager::class)->create($name, $type, $layout);

        app(StructureManager::class)->saveArrangement($section, ['gap' => $gap, 'width' => $width], $layout);

        return app(StructureManager::class)->saveStyle($section, $style);
    }

    /** @param  array<string, string>  $style */
    private function markdown(string $name, string $body, array $style = []): Block
    {
        return $this->block($name, 'markdown', ['body' => $body, 'style' => $style]);
    }

    /** @param  array<string, string>  $data */
    private function block(string $name, string $type, array $data): Block
    {
        $block = app(BlockManager::class)->create($name, $type);

        return app(BlockManager::class)->saveContent($block, $data);
    }

    private function put(Structure $section, Block $block): void
    {
        app(StructureManager::class)->insertBlock($section, $block);
    }

    /**
     * Somebody to sign in with.
     *
     * **Our own accounts** (`bw_users`). On a site that has none there is
     * nobody who can open the admin at all, so the offer is made here.
     */
    private function makeFirstUser(): void
    {
        if ($this->hasAUser()) {
            $this->components->warn(
                'Everyone with a Bladewright account can open the admin and write code. '
                .'Roles are being designed again.',
            );

            return;
        }

        if (! $this->canAsk()) {
            $this->components->warn('There is nobody to sign in as yet.');
            $this->components->bulletList(['bladewright:user --create --email=you@example.com creates one']);

            return;
        }

        if (! $this->components->confirm('Create somebody to sign in with?', true)) {
            $this->components->bulletList(['bladewright:user --create --email=you@example.com creates one later']);

            return;
        }

        $this->call('bladewright:user', [
            '--create' => true,
            '--email' => (string) $this->components->ask('Email address'),
        ]);
    }

    private function hasAUser(): bool
    {
        try {
            return \Bladewright\Models\User::query()->exists();
        } catch (\Throwable) {
            return false;
        }
    }
}
