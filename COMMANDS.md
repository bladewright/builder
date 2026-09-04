# The commands

The nine core commands (the owner's table, 2026-09-02). One noun each, the
verbs as options, everything addressed by name — **people say names; the
machinery holds uuids**, so renaming anything breaks nothing. Deleting always
warns and asks. The content itself is edited on the screens; the terminal
builds the skeleton.

Run `php artisan <command> --help` for any of them.

---

## Getting started

| Command | What it does |
|---|---|
| `bladewright:install` | Everything, asking as it goes: which database (said first), our migrations only, the site's language, the welcome page at `/` built from the four-layer pieces, and the offer of a first account. `--fresh` wipes the site and installs it again — the site's name is typed to confirm, and the uploaded files always stay |
| `bladewright:user` | The admin's accounts, in **our own `bw_users`** — an email and a password, no name. `--create --email= --password=`, `--update=old@example.com --email= --password=`, `--delete --email=` (warns and asks; the last account is refused) |
| `bladewright:setting` | The site's language for new pages. Bare, it says what it is and where the answer comes from; `--locale=ja` sets it, `--locale=""` follows the app again |
| `bladewright:uninstall` | Take Bladewright out: the `bw_` tables, accounts, storage. Asks whether the uploaded files go too, then wants the site's name typed. Finish with `composer remove bladewright/bladewright` |

## Pages

| Command | What it does |
|---|---|
| `bladewright:pages` | List them: name, URL, state, how many components. `--search=` narrows |
| `bladewright:pages --create=About --url=about --layout=site` | A new page at its URL (`--url=""` is the front page). The layout is optional — a bare page is allowed, and told so |
| `bladewright:pages --copy=About --to="About B"` | One more page. **It shows the same components**, takes a free URL, and is never published |
| `bladewright:pages --rename=About --to=Company` | A new name; the URL and the uuid stay |
| `bladewright:pages --publish=About --from="2026-10-01 09:00" --until=` | Publish, at once or for a window. A closed window serves 410 |
| `bladewright:pages --delete=About` | Warns and asks. The components it showed stay |

Error pages are set aside for a better design.

## Layouts

| Command | What it does |
|---|---|
| `bladewright:layouts` | List them: name, preset, type. `--search=` narrows |
| `bladewright:layouts --create=site --preset=plain --type=sidebar` | A new frame from a recipe. Presets: bootstrap (default) or plain; types: header (default) or sidebar. **The recipe's job ends at birth** — the frame is the site's own |
| `bladewright:layouts --copy=site --to=campaign` | One more frame, its own from birth |
| `bladewright:layouts --rename=site --to=frame` | A new name; the uuid stays |
| `bladewright:layouts --delete=site` | Warns and asks |

## Components

| Command | What it does |
|---|---|
| `bladewright:components` | List them: name, type, grid or not, how many blocks. `--search=` narrows |
| `bladewright:components --create=hero --type=section` | A new component. Types: section / article / nav / table / figure / form / field. `--container` gives the arrangement a grid |
| `bladewright:components --insert=hero --to=About --order=1` | Put a component onto a page |
| `bladewright:components --copy=hero --to=hero-b` | One more arrangement. **It shows the same blocks** — copying a block is what diverges the words |
| `bladewright:components --rename=hero --to=banner` | A new name; the uuid stays |
| `bladewright:components --delete=hero` | Warns with how many pages show it. **The blocks in it stay** |

## Blocks

| Command | What it does |
|---|---|
| `bladewright:blocks` | List them: name, type, when they changed. `--search=` narrows |
| `bladewright:blocks --create=intro --type=markdown` | A new block. Types: markdown / image / video / audio / button / input / select / radio / checkbox / textarea / embed / div. **The content is written on the screens** |
| `bladewright:blocks --create=email --type=input` | A field. **Its label is part of it** — written on the screens, like the rest of the content |
| `bladewright:blocks --insert=intro --to=hero --order=2` | Put a block into a component |
| `bladewright:blocks --copy=intro --to=intro-en` | One more like it, its own from birth — this is how a block diverges |
| `bladewright:blocks --rename=intro --to=welcome` | A new name; the uuid stays |
| `bladewright:blocks --delete=intro` | Warns with how many components show it, then sweeps every one |

## Media

| Command | What it does |
|---|---|
| `bladewright:media` | Everything in the library, whichever folder it is in |
| `bladewright:media --search=` | Narrow it: part of a file name, or an exact path |
| `bladewright:media --upload=brochures/spring.pdf --from=~/spring.pdf` | Put one in — where it goes, and which local file to take |
| `bladewright:media --delete=` | Delete one. It warns (with how many blocks show it) and asks |
