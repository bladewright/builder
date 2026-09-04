# Bladewright Builder

Build and edit a Laravel site from the browser.

Pages, layouts, components and blocks — arranged in a preview that *is* the
page. Press a part to open it, type into the words where they stand, drag a
section into place. Nothing lands until Save.

```bash
composer require bladewright/builder
php artisan migrate            # the application's own tables, if it has not yet
php artisan bladewright:install
```

That one command makes our tables, a layout, a welcome page and the first
person who can sign in. Then `/bladewright` is the admin and `/` is the site.

**We run our own migrations and no more** — the application's own are yours,
and running them uninvited would be reaching into your house. On a database
that has never been migrated (a new MySQL, say) Laravel's `sessions` table is
missing and every page answers 500, so `bladewright:install` looks at the end
and says so if anything is still wanted.

> Laravel's own welcome route holds `/`. Remove
> `Route::get('/', fn () => view('welcome'));` from `routes/web.php` first, or
> the sample page will not show.

---

## The four layers

A site is made of four things, and each one is a screen.

| | What it is |
|---|---|
| **Layout** | The frame: a whole HTML document with `{{ $slot }}` where the page goes, plus the header and footer components it wears. The site's typeface is set here |
| **Page** | A URL, a layout to wear, and a row of components. Carries its own title and description, and a publishing window |
| **Component** | A structure that means something — a `section`, `nav`, `article`, `form` — holding blocks, and other components |
| **Block** | One element: markdown, an image, a video, a button, a form field. **One block is one element**, and markdown is the only one that is many |

Everything is held by uuid, so renaming anything breaks nothing, and editing a
block reaches every page showing it the moment that page is next asked for.

## Editing in the preview

The page editor's preview is the page itself, wearing marks only the admin can
see. In it:

- **Press** a block to open it, or the space around it to open the component
- **Double-press** words to type into them where they stand
- **+** between parts opens a shelf of what can go there — left and right when
  the parts stand side by side
- **Drag** by the corner to move a part; the page scrolls when you reach its
  edge, and a seam shows where it would land
- **×** takes a part off the page. The part itself stays on its shelf

**Nothing lands until Save.** Until then the page in the database has not
moved, and an amber dot says something is waiting.

## Three faces, one truth

Every layer can be looked at three ways, and they stay in step before anything
is saved:

- **Preview** — what a visitor would see
- **Structure** — the same thing as a tree, for changing words without hunting
  for them on the page
- **Code** — the HTML it comes out as. **Write here and it becomes the
  layer**: Blade of the site's own, run when the page is asked for. Empty it
  and the arrangement leads again

The road out is always open, at every layer, without leaving the browser.

## How a part looks

On cards, not in a stylesheet:

- **Colour** from the site's own palette, by name — change a name in Colours
  and every page wearing it follows
- **Spacing, borders, corners, shadows** — a border is thickness, colour and
  which sides, said as the box it draws on
- **Hover** — a colour or an opacity for when the pointer is on it
- **How the contents stand** — stacked, in a grid (`auto`, a count, or
  `1fr 2fr`), or in a row with its own alignment. Small screens fold it back
  into one column unless told otherwise
- **A class of its own**, for whatever stylesheet the site declares

The site says once what its CSS is written in — **Bootstrap, Pico, or plain
CSS** — and every preview wears it, so a class means on the screen what it
will mean on the page.

## Taking the site as files

**Settings → Take the site as files** writes every published page out as plain
HTML, in a folder you can put on any host that serves files — or open straight
off a disk.

What the application was serving becomes a file: the stylesheet lands beside
the pages as `site.css`, and every picture the pages show lands under
`media/`, with the pages pointed at where they landed. What is fetched from
somewhere else stays fetched from there — Bootstrap on a CDN is a link either
way.

```
index.html                 /
about/index.html           /about
company/people/index.html  /company/people
site.css
media/…
```

A page whose URL is a shape (`news/{slug}`) is left out and said so on the
screen: it stands for many paths, and a copy cannot know which of them exist.

The zip is made when you press the button and handed straight over. **Nothing
of it is kept on the server.**

## What it promises

| Promise | Why |
|---|---|
| **Never writes into the application's tree** | `app/`, `resources/` and `routes/` are yours. Once the two mix, updates cannot be shipped |
| **Never rewrites a route file** | One `Route::fallback()` catches what matched nothing else. `route:cache` keeps working, and your own routes always win |
| **Never writes to `.env`** | Settings live in the database and override `config()` at boot — which works with `config:cache` and on read-only hosting. **By default not one host setting is touched** |
| **Nothing lands until Save** | The arrangement in the database does not move while you are moving things around |
| **An error page never says what runs the site** | A site's software is nobody else's business |

## Commands

Everything a screen does, a command does — so it can be scripted, and checked.

```bash
php artisan bladewright:pages        # list, create, copy, rename, publish, delete
php artisan bladewright:layouts
php artisan bladewright:components
php artisan bladewright:blocks
php artisan bladewright:media
php artisan bladewright:user
php artisan bladewright:setting      # key, value, where it comes from
php artisan bladewright:install --fresh
php artisan bladewright:uninstall
```

See [docs/commands.md](docs/commands.md) for what each one takes.

## Living alongside Laravel

Because `.env` is never rewritten, a file and the running application can
differ — when somebody changed the same key from the admin. Two things say
where a value came from:

```bash
php artisan about                 # the keys currently overridden
php artisan bladewright:setting   # key, value, where it comes from, what it replaced
```

The settings screen says it too. By default only `bladewright.*` and our own
media disk may be changed from the browser; to open a host setting (`app.name`,
say) add it to `settings.allow` in `config/bladewright.php`. **Opening it is
the owner's call.**

## Requirements

PHP 8.3+, Laravel 13, Livewire 4. Any database Laravel supports.

## Development

```bash
composer install
composer test

# to look at it in a browser
npm install && npm run build
vendor/bin/testbench migrate
vendor/bin/testbench bladewright:install
vendor/bin/testbench serve
```

## License

MIT. See [LICENSE](LICENSE).
