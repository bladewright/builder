# Changing the look

**This product has no look of its own.** The CSS in the package is structure
that keeps things from breaking — columns line up, what someone types cannot
burst its container — and nothing else. No colours, no spacing.

The design lives entirely in the layout's `<style>`, which is **your site's
own code**. `composer update` never touches it, so rewrite as much of it as
you like.

**There is no build step.** That is what "fix it in the browser without
deploying" rests on: where a build decides which classes exist, a class added
later quietly does nothing.

---

## Change the tokens

Touch the `:root` block at the top of the layout and the look changes without
a single edit to the markup.

| Token | What it changes |
|---|---|
| `--grad` / `--grad-a` / `--grad-b` | Buttons, the closing band, the logo |
| `--grad-heading` | The gradient behind the hero's heading |
| `--ground` / `--surface` / `--surface-2` | The background, and the cards |
| `--ink-strong` / `--ink` / `--muted` / `--faint` | Four weights of text |
| `--rule` | Rules |
| `--link` | Links |
| `--soft` / `--soft-sm` | Shadows |
| `--radius` | Corner rounding (cards, the closing band) |
| `--wrap` | The measure of the body |

The tokens the packaged blocks read live in the same place.

| Token | What it changes |
|---|---|
| `--bw-accent` / `--bw-on-accent` | The lead colour of the packaged blocks, buttons included |
| `--bw-line` | Rules inside the packaged blocks |
| `--bw-radius` | Button corners |
| `--bw-gap` | The gap between columns |
| `--bw-space` | A section's space above and below (when "Normal" is chosen) |
| `--bw-edge` | A section's space at the sides |
| `--bw-tone-muted` | The colour of a band set to "Soft tint" |
| `--bw-card` / `--bw-card-space` | A card's background and its inner spacing |

### Twelve lines, and it is a different site

```css
--grad:         linear-gradient(to top left, #B45309, #F59E0B);
--grad-heading: linear-gradient(to bottom right, #7C2D12, #D97706);
--grad-a: #B45309;   --grad-b: #F59E0B;
--ground: #FBF7F0;   --surface: #FFFDF9;
--ink-strong: #1C1917; --ink: #44403C;
--rule: #E7E0D5;     --link: #B45309;
--radius: .25rem;    --wrap: 62rem;
```

Without changing one character of HTML, an indigo gradient site becomes a
square-cornered warm one.

---

## A dark colour scheme

**Write both entrances.** With only one, whoever is building cannot check
their work.

```css
@media (prefers-color-scheme: dark) {
    :root:not([data-bw-scheme="light"]) { /* override the tokens */ }
}

html[data-bw-scheme="dark"] { /* the same tokens again */ }
```

- `prefers-color-scheme` … the visitor's own device setting
- `[data-bw-scheme]` … **the mark the editor's preview sets**

Turn on "Supports a dark colour scheme" in the settings and the editor lets
you switch between light and dark to check. Without the second entrance,
switching does nothing at all (and the screen says so).

---

## About the class names

The packaged blocks emit `bw-section`, `bw-row`, `bw-col-6`, `bw-card`,
`bw-prose` and so on. `bw-row` and `bw-col-*` follow the way Bootstrap writes
them (twelfths included).

- **These names do not change.** Changing one would be a breaking change
- Bootstrap uses `bs-`, so nothing collides
- **Only if you run Tailwind with `prefix: 'bw-'` does `bw-prose` collide**

## Calling a block

In a page's code, a block is called by this name.

```blade
<livewire:bw::blocks.hero heading="…" />
```

`bw` is configurable (`bladewright.namespace`), but **the name is baked into
the page's code**. If you change it, run `bladewright:reexpand` to bring
existing pages in line (blocks edited by hand are left alone, so search for
those and fix them).

## What not to do

- **Introduce something that needs a build into the site.** Classes added from
  the browser will quietly stop working (using one while authoring a block,
  where the build does see it, is fine)
- **Reach into the packaged markup with deep selectors.** When the tokens are
  not enough, taking the block over (forking it) and rewriting it is safer
