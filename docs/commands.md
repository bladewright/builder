# The commands

**Everything the screen can do, a command can do.** So it can be checked on
the server as it is, so volume can be automated, and so no rule is written
twice.

Each rule lives in exactly one place, and **the screen and the command go down
the same road**: `BlockPlacer` (where things are placed), `PageManager`
(pages), `PartManager` (parts), `MediaLibrary` (files).

Everything below is `php artisan`. In the local workbench, read that as
`vendor/bin/testbench`.

---

## Common flows

**Make one page and publish it**

```bash
php artisan bladewright:create-page "About" --path=about
php artisan bladewright:place about --add=section
php artisan bladewright:place about --add=heading --into=0
php artisan bladewright:place about --set=0.0.0:text=Hello
php artisan bladewright:publish about
php artisan bladewright:page about --publish
```

**Place a shape and swap the words (volume)**

```bash
php artisan bladewright:pattern                          # see the patterns
php artisan bladewright:pattern --apply="Three pricing cards" --to=pricing
php artisan bladewright:tree pricing                     # check the paths
php artisan bladewright:place pricing --set=1.1.0.0:text=Starter
```

**Another page like this one**

```bash
php artisan bladewright:copy-page about --path=about-2026 --name="About 2026"
```

**After a package update (when a block's markup changed)**

```bash
php artisan bladewright:reexpand --pretend   # count what would change
php artisan bladewright:reexpand --publish   # bring them in line and publish
```

**Bring in a block written locally**

```bash
php artisan bladewright:create-block "Notice band" --key=notice \
    --file=./notice.blade.php --group=text --publish
```

**Check things after a deploy**

```bash
php artisan bladewright:sync --check     # database and storage out of step
php artisan bladewright:media --missing  # images referenced but gone
php artisan bladewright:verify about     # render it for real and catch runtime errors
php artisan bladewright:doctor           # what is off in the HTML that came out
```

---

## Pages

| Command | What it does |
|---|---|
| `bladewright:pages` | The list. `--search=` `--sort=updated\|name\|path` |
| `bladewright:create-page {name}` | Create one. `--path=` `--key=` `--publish` |
| `bladewright:copy-page {key}` | Duplicate it (**never published**). `--path=` `--name=` |
| `bladewright:page {key}` | The name, URL, layout, publishing. `--name=` (**what it is called on the screens; never the URL**) `--path=` `--layout=` `--publish` `--unpublish` `--from=` `--until=` |
| `bladewright:page {key} --archive` | Tidy it away (**reversible**: the URL is freed, the revisions stay) |
| `bladewright:page {key} --restore=` | Bring a tidied-away page back at that URL |
| `bladewright:page {key} --delete` | **Delete it for good** (revisions, index and files). `--force` skips the confirmation |
| `bladewright:pages --archived` | The pages that were tidied away |
| `bladewright:create-error {code}` | **Take an error page over from Laravel** (404 / 500 …), starting from the page it serves today. `--publish`. Refused when the application has its own `resources/views/errors/{code}.blade.php` — that file wins and is not ours to edit |
| `bladewright:meta {key}` | How it looks in search and when shared. `--title=` `--description=` `--image=` |

## What is on a page

**Places are named by the paths `bladewright:tree` prints** (`0` is the first
section, `0.0.0` the first part inside it).

| Command | What it does |
|---|---|
| `bladewright:tree {key}` | Print the contents as a tree. `--published` for the published version |
| `bladewright:place {key} --add=` | Place a part. `--into=` inside / `--after=` below / `--before=` above. **The heading level follows the place** (one deeper inside the same section, h2 in a new one; `--set` overrides it) |
| `bladewright:place {key} --set=` | Set a value: `0.0.0:text=Hello` (repeatable) |
| `bladewright:place {key} --split=` | Split a section: `0:2` splits path 0 into two |
| `bladewright:place {key} --duplicate=` | Duplicate it, contents and all |
| `bladewright:place {key} --remove=` | Remove it |
| `bladewright:place {key} --revert=` | **Take a hand-edited block back to what its definition expands to** (what was written by hand is lost, but the revision keeps it) |
| `bladewright:place {key} --move=` | Move one: `0.0.0:0.1` puts the first at the bottom of the second |

The rules are the screen's rules: **a section is always top level**, content
at the top level **gets a section around it**, and pointing at a holder puts
it **inside** (for a section, inside its column).

## Patterns

| Command | What it does |
|---|---|
| `bladewright:pattern` | The list |
| `bladewright:pattern --save={key}` | Keep a section of this page. `--at=0` `--name=` |
| `bladewright:pattern --apply={name} --to={key}` | Place it (**what you place is a copy**: editing the pattern leaves pages that already use it alone) |
| `bladewright:pattern --rename={name} --name={new name}` | Rename it |
| `bladewright:pattern --forget={name}` | Throw it away (what was placed stays) |

## Parts (blocks and layouts)

| Command | What it does |
|---|---|
| `bladewright:create-block {name}` | Create one. `--key=` `--holder` (a holder) `--kind=layout` `--group=` (which drawer) |
| `bladewright:create-block … --file= --publish` | **Bring in something written in an editor, and publish it** |
| `bladewright:create-block --fork={key}` | Take over a packaged part (**composer update no longer reaches it**) |
| `bladewright:edit {key} --kind=block` | Edit the contents. `--file=` from a file, `--note=`, `--publish` |
| `bladewright:parts` | The list (**packaged ones are in it too**). `--kind=layout` `--search=` |
| `bladewright:part {key}` | Where it is from, and how much it is used (**a packaged part can be looked at as well**). `--kind=layout` |
| `bladewright:part {key} --used` | The pages that use it |
| `bladewright:part {key} --revert` | Back to the packaged part (**the revisions made here are dropped**). `--force` |
| `bladewright:part {key} --delete` | Delete a part this site made (cannot be undone). `--force`. **A packaged part cannot be deleted** |
| `bladewright:part {key} --kind=layout --set=siteName=Acme` | A value on the layout itself (site name, logo, footer line) |

**A layout in use cannot be deleted.** Pages name their layout, so deleting it
would leave them without a frame. Change the layout on those pages first.

## Media

| Command | What it does |
|---|---|
| `bladewright:media` | The list. `--folder=` narrows it |
| `bladewright:media --add={file}` | Upload (repeatable). `--folder=` |
| `bladewright:media --new-folder={name}` | Make a folder |
| `bladewright:media --remove={path}` | Remove one (**it disappears from the pages that used it**) |
| `bladewright:media --remove-folder={path}` | Remove a folder (**only when it is empty**) |
| `bladewright:media --used={path}` | The pages and parts that use this file |
| `bladewright:media --missing` | Files referenced but gone |

**Media is the one thing revisions cannot restore.** Check `--used=` before
removing anything, and note that `bladewright:install --fresh` keeps the files
unless `--media` says otherwise.

## Revisions and history

| Command | What it does |
|---|---|
| `bladewright:publish {key}` | Publish the draft. `--kind=page\|block\|layout` |
| `bladewright:history {key}` | The history. `--kind=` `--limit=` |
| `bladewright:restore {key} {revision}` | Go back to an earlier revision, **by the number the history shows** (each page, block and layout counts its own from 1). `--publish` replaces the live version too |
| `bladewright:verify {key}` | Render it for real and catch runtime errors. `--revision=` `--json` |
| `bladewright:sync` | Write the templates in storage out from the database again. `--check` `--key=` `--kind=` |
| `bladewright:sync --prune` | **Remove templates storage has but the database does not know** (ghosts of deleted pages) |
| `bladewright:reexpand` | **Expand placed blocks again with today's definitions.** `--key=` `--kind=` `--publish` `--pretend`. Blocks edited by hand are left alone |
| `bladewright:doctor` | **Read the HTML that came out and list what is off.** `{key?}` `--kind=` `--published` `--json`. The draft by default. **It stops nothing** (only `--strict` exits as a failure, for CI) |
| `bladewright:styles` | Print the CSS the parts need. `--layout=` shows **only what that layout is missing**; `--add` puts it in the draft (never published) |

What `doctor` looks at: tags left open; heading levels (one h1, no level
skipped); headings with nothing in them; an image's `alt` (`alt=""` is right
for decoration, so it says nothing); links and buttons with no destination or
no words; a link inside a link; a repeated `id`; a page's `lang`, `title` and
`main`.

**Rendering a part on its own does not blame it for empty values** (the
heading's text and a link's destination arrive when it is placed). With
`--kind=block` it also looks at packaged blocks nobody has taken over.

On a site that started from a starter, the parts' CSS was written into the
layout once and is the site's own code from then on. After a package update
brings new parts, `bladewright:styles --layout=<key>` shows what is missing.

## Running a site

| Command | What it does |
|---|---|
| `bladewright:install` | Tables, storage, a starter and the first user. `--sample` with the sample page / `--empty` **the frame only (a layout; no page)** / `--preset=plain\|bootstrap` **what the frame is written in** |
| `bladewright:install --fresh` | **Wipe the site and install it again** (pages and history go). `--media` deletes the uploaded files too; `--force` skips the question. **Only the `bw_` tables are dropped** — never `migrate:fresh`, which would take the app's own tables |
| `bladewright:user --add --user=` | Create somebody to sign in with. `--name=` `--password=`. **Anybody who can sign in can open the admin and write code for now** — roles are being designed again |
| `bladewright:setting {key} {value}` | See or change configuration. **It also shows what the value replaced (.env or the config file).** `--forget` restores the default |
| `php artisan about` | The Bladewright section lists **which keys are currently overridden** (none if there are none) |
| `bladewright:reindex` | Build the search index again |
| `bladewright:transfer --to=` | Copy to another database connection. `--fresh` `--pretend` `--force` |

---

## Worth remembering

- **Publishing has two halves.** `bladewright:publish` publishes the contents
  (a revision); `bladewright:page --publish` publishes the URL.
- **`"0"` is falsy.** Paths start at 0, so compare against `null` in anything
  you write yourself.
- **`.env` is never rewritten.** Settings in the database override `config()`
  at boot. When they disagree, `php artisan about` says which is winning.
- **Tidying away and deleting are different.** `--archive` can be undone;
  `--delete` takes the revisions with it.
- **There is no gap between the screen and the commands.** Every action on
  screen has a command, and both go through the same rules.
- **Roles are gone while they are designed again.** Until they come back,
  **everybody the host application can sign in can do everything**, write-code
  included. Close it from your own provider:
  `Gate::define('bladewright.write-code', fn ($user) => $user->is_staff);` —
  yours is registered after ours, so it wins.
- **Not there yet:** commands for plugins (the shelf exists, but nothing is in
  it), roles, and entries — **"add a news feature" is a plugin**, and the engine
  that was in the core came out to be designed with them.
