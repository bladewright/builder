# Bladewright

A CMS for editing a running Laravel site from the browser.

The contents of a page, the Blade, and the logic behind it are all written
from the admin — no editor, no deploy, straight onto the published page.

> **Status:** in development. The repository is private and not on Packagist
> yet, so see [Installing](#installing) for how it goes in today.

---

## Installing

```bash
composer config repositories.bladewright vcs https://github.com/bladewright/bladewright
composer require bladewright/bladewright:dev-main

# Laravel's own welcome page holds /, so remove it first:
#   Route::get('/', fn () => view('welcome')); in routes/web.php

php artisan bladewright:install --sample   # with the sample page
# php artisan bladewright:install --empty  # the frame only (a layout; no page)
```

That one command sets up the tables, the storage, a starter and the first
person who can sign in. With a terminal it asks about the starter and the
first user; from a script, pass `--sample --user=you@example.com
--password=…` and **it asks nothing**.

Afterwards `/` shows the sample page and `/bladewright` is the admin.

## What we promise

Keeping these is what makes it distributable as a package and operable on
Cloud.

| Promise | Why |
|---|---|
| **Never write into the application source tree** | Once the user's code and the package's code mix, updates can no longer be shipped. Everything generated lives under `config('bladewright.root')` |
| **Never rewrite a route file** | One `Route::fallback()` catches the request and the page is resolved from the database. `route:cache` keeps working |
| **No controller per page** | There is one `SitePageController`. Logic specific to a page belongs to its template (a single-file component) |
| **Drafts apart from what is published** | Editing always lands in a draft and goes through a preview before it is published |
| **Never write to `.env` or a config file** | Saved settings live in the database and override `config()` at boot. That coexists with `config:cache` and works on read-only hosting. **By default not a single host setting is touched** |

### The site's own CSS is plain CSS

The starter is built with plain CSS and nothing else. **Having no build step**
is what "fix it in the browser without deploying" rests on: with something
like Tailwind, where a build decides which classes exist, a class added later
quietly does nothing. (Using it while authoring a block is fine.)

The look comes from the tokens at the top of the layout —
see [docs/theming.md](docs/theming.md).

### Living alongside Laravel

Because `.env` is never rewritten, **what a file says and what the app does
can differ** (when someone changed the same key from the admin). Three things
show where a value comes from:

```bash
php artisan about                 # the keys currently overridden (none if there are none)
php artisan bladewright:setting   # key, current value, where it comes from, what it replaced
```

The settings screen also says "The config file (.env) says …" for a key that
is being overridden.

By default only `bladewright.*` and our own media disk can be changed. To
edit a host setting (`app.name`, say) from the screen, add it to
`settings.allow` in `config/bladewright.php`. **Opening it is the owner's
call**, in that order.

## Layout of the package

```
src/
  BladewrightServiceProvider.php   registers the view namespace and the routes
  Models/                          components, routes, revisions, entries, terms
  Routing/                         path → component (cached), and the path guard
  Blocks/                          block expansion, placement, fields, styles
  Entries/                         entries and their terms
  Health/                          checks the HTML that came out
  Support/                         pages, revisions, settings, storage
  Http/Controllers/                the public site, previews, the admin
  Console/                         install / create-page / place / doctor / …
config/bladewright.php
database/migrations/
tests/
```

Where generated files live:

```
storage/app/bladewright/
  views/
    pages/{key}.blade.php     published
    drafts/{key}.blade.php    draft
```

`views/` is registered under the `bw` namespace, so `bw::pages.home`
resolves.

## Development

```bash
composer install
composer test          # phpunit
composer serve         # testbench serve, to look at it in a browser
```

To look at it in a browser:

```bash
export DB_CONNECTION=sqlite
export DB_DATABASE="$(pwd)/workbench/database/database.sqlite"
touch "$DB_DATABASE"

vendor/bin/testbench migrate --force
vendor/bin/testbench bladewright:install --sample
vendor/bin/testbench serve
```

## Adding it to an existing application

```bash
composer require bladewright/bladewright
php artisan bladewright:install --empty
php artisan bladewright:create-page "Home" --path="" --publish
```

The host application's own routes always win. Bladewright only picks up
requests that matched nothing else.

## License

MIT
