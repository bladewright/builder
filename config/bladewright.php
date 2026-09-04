<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Site storage root
    |--------------------------------------------------------------------------
    |
    | Where templates edited from the browser are written.
    | Keeping them outside the application source tree (app/, resources/)
    | is what stops package updates and user content from colliding.
    |
    */

    'root' => env('BLADEWRIGHT_ROOT', storage_path('app/bladewright')),

    /*
    |--------------------------------------------------------------------------
    | Site language
    |--------------------------------------------------------------------------
    |
    | The language new pages are born in: their <html lang> and their route's
    | locale. Existing pages keep the language they were made with.
    |
    | **Left null it follows app.locale** — the answer the Laravel developer
    | already gave. Someone who has never touched .env changes it with
    | `bladewright:setting --locale=…` instead (kept in the database, so no
    | file of theirs is written).
    |
    */

    'locale' => env('BLADEWRIGHT_LOCALE'),

    /*
    |--------------------------------------------------------------------------
    | Database connection
    |--------------------------------------------------------------------------
    |
    | The connection that holds the bw_* tables. Null means the app's own
    | default connection, so the site lives in the customer's database.
    | Point this elsewhere only if the site's content should be kept apart.
    |
    */

    'database' => [
        'connection' => env('BLADEWRIGHT_DB_CONNECTION'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Edit history
    |--------------------------------------------------------------------------
    |
    | Every save adds one revision. The source of truth for content is the
    | database (bw_revisions); the templates under storage are checkouts
    | written out from it.
    |
    | keep: how many revisions to keep per subject. Revisions that have been
    |       published, that are pinned, and the ones currently in use are
    |       never pruned, whatever the count.
    |
    */

    'history' => [
        'keep' => env('BLADEWRIGHT_HISTORY_KEEP', 50),
    ],

    /*
    |--------------------------------------------------------------------------
    | Settings
    |--------------------------------------------------------------------------
    |
    | Laravel configuration that may be changed from the admin. Keys are
    | plain config paths. The values here override config at boot, so from
    | the outside it is indistinguishable from editing config/app.php.
    |
    | No file is ever written. v3 rewrote .env by string replacement, which
    | did nothing on a config:cache'd production, could not write at all on
    | read-only hosting, and silently did nothing when the line was missing.
    |
    | **By default not a single host setting is touched.** A package that
    | installs alongside an existing app has no business changing how that
    | app behaves. Someone edits "site name", app.name changes with it, and
    | suddenly the host's outgoing mail has a different sender — with
    | config/app.php still showing the original value, so the cause lives in
    | one database row and nowhere else.
    |
    | If the site is entirely yours, add keys here and they become editable
    | from the screen. **Opening them is the owner's call**, in that order.
    |
    |   'app.name', 'app.timezone', 'app.locale', 'mail.*',
    |   'filesystems.disks.*',    // replaces the host's own disks
    |
    | Whatever is listed, database.* / app.key / app.debug / app.env can
    | never be overridden (the code forbids it).
    |
    */

    'settings' => [
        'allow' => [
            // Only our own media disk, so S3 / R2 / MinIO / NAS can be
            // connected from the screen. **The host's s3 or local are left
            // alone** — swapping a disk of the same name would move the
            // host's own uploads.
            'filesystems.disks.bladewright',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Starters
    |--------------------------------------------------------------------------
    |
    | Where starter recipes (a layout and an introduction page) are kept.
    | Besides the packaged ones, an agency can drop in its own house style.
    | Same key means last wins, so packaged recipes can be replaced.
    |
    */

    'starters' => [
        'paths' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Media
    |--------------------------------------------------------------------------
    |
    | Where uploaded files live.
    |
    | **Media is the one thing the database cannot restore.** Pages and
    | blocks can be rebuilt from their revisions; the bytes of an image
    | exist nowhere else. Do not apply the "storage is disposable"
    | assumption here. If your deploys give each release a fresh directory,
    | move the root outside the application.
    |
    | disk may name any disk in the host's filesystems.php (s3, r2, …).
    | By default we register the bladewright disk below.
    |
    | direct_urls: link straight to the disk when it exposes public URLs.
    | A CDN then works, but **changing where files live changes the URLs
    | baked into pages**, so they have to be published again.
    |
    */

    'media' => [
        'disk' => env('BLADEWRIGHT_MEDIA_DISK', 'bladewright'),
        'root' => env('BLADEWRIGHT_MEDIA_ROOT'),
        'direct_urls' => env('BLADEWRIGHT_MEDIA_DIRECT_URLS', false),
        'max_size' => env('BLADEWRIGHT_MEDIA_MAX_SIZE', 10 * 1024 * 1024),
        'mimes' => [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml', 'image/avif',
            // The video and audio blocks play what is in here, so the door
            // has to let their files through.
            'video/mp4', 'video/webm', 'video/quicktime',
            'audio/mpeg', 'audio/mp4', 'audio/ogg', 'audio/wav',
            'application/pdf',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Markdown
    |--------------------------------------------------------------------------
    |
    | How body text is stored. We keep Markdown rather than the HTML a
    | WYSIWYG spits out **because the history stays readable**. HTML gains
    | and loses line breaks and classes on its own, which makes "what
    | changed since Tuesday" impossible to answer in practice.
    |
    | The safe choices (strip raw HTML, disable dangerous links) are not
    | exposed here. Make them settings and somebody will open them.
    | If you need HTML, use an html field rather than markdown (developers
    | only).
    |
    | allow: syntax permitted by default. Each block field can narrow it.
    | heading_offset: how far to push headings down, so a page never ends up
    | with two h1s.
    |
    */

    'markdown' => [
        'allow' => ['heading', 'link', 'image', 'list', 'quote', 'code', 'rule', 'bold', 'italic'],
        'heading_offset' => 2,
        'max_nesting_level' => 4,
    ],

    /*
    |--------------------------------------------------------------------------
    | Routing
    |--------------------------------------------------------------------------
    |
    | Nothing is ever written to a route file. Unmatched requests arrive at
    | our fallback, which resolves the page from the database. The host
    | application's own routes always win.
    |
    */

    'routing' => [
        'enabled' => true,
        'middleware' => ['web'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Admin
    |--------------------------------------------------------------------------
    |
    | Where the admin lives.
    |
    | **The default is not /admin.** This installs into a running site with
    | composer require, and Filament, Nova and Backpack all default to
    | /admin. On a collision the one registered first wins, so which of the
    | two breaks is unpredictable.
    |
    | Setting domain moves the admin onto a subdomain (which suits an agency
    | running several sites on Cloud).
    |
    | Being able to change the URL is not a security measure in itself —
    | authentication is. It does cut down the brute-force noise.
    |
    */

    'admin' => [
        'prefix' => env('BLADEWRIGHT_ADMIN_PREFIX', 'bladewright'),
        'domain' => env('BLADEWRIGHT_ADMIN_DOMAIN'),

        // Authentication uses Bladewright's own guard over bw_users
        // (bladewright:user --create makes the accounts).
        //
        // The reason for our own middleware rather than `auth` is to pin
        // where an unauthenticated visitor is sent: /bladewright/login.
        // `auth` redirects to `route('login')`, which fails outright when
        // the host has no such route.
        'middleware' => [
            'web',
            \Bladewright\Http\Middleware\AdminAuthenticate::class,
            'can:bladewright.access-admin',
        ],

        // Applied to the login screen itself, which sits outside auth.
        'guest_middleware' => ['web'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Auth
    |--------------------------------------------------------------------------
    |
    | The guard the admin logs in with. Null means the app's default.
    | Session and user come from the host, so someone already logged in on
    | the host walks straight into the admin.
    |
    | On a site that keeps its members in a separate guard (a separate
    | table), point this at the staff guard: it decides who the admin treats
    | as signed in.
    |
    */

    /*
    | Which guard the admin signs in with. **`bladewright` — our own — by
    | default**: the admin's people live in `bw_users` (made with
    | `bladewright:user --create`), so a developer let into the admin never
    | becomes a row among the host's own members. Point this at the host's
    | guard (`web`, say) to let the host's users in instead.
    */
    'auth' => [
        'guard' => env('BLADEWRIGHT_AUTH_GUARD', 'bladewright'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Abilities — temporarily open
    |--------------------------------------------------------------------------
    |
    | Decisions are made on abilities, and the screens ask for them by name
    | (edit-content to write a page, write-code to touch a block's
    | definition, publish to delete one). That much is unchanged.
    |
    | **What decides them is gone for now.** Roles were taken out to be
    | designed again, so **everybody signed in can do everything** — and
    | write-code is the RCE boundary in practice, because Blade takes PHP.
    | On a site whose members sign in, that means the members.
    |
    | Until roles come back, close it in the application's own provider:
    |
    |     Gate::define('bladewright.write-code', fn ($user) => $user->is_staff);
    |
    | Yours is registered after ours, so it wins.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Resolver cache
    |--------------------------------------------------------------------------
    */

    'cache' => [
        'enabled' => env('BLADEWRIGHT_CACHE', true),
        'store' => env('BLADEWRIGHT_CACHE_STORE'),
        'ttl' => 3600,
        'prefix' => 'bladewright:page:',
    ],

];
