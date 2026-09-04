# Changing the look

**This product has no look of its own.** Nothing in the package decides a
colour, a size or a spacing on your site. What a page wears comes from four
places, and all four are yours.

---

## 1. What the CSS is written in

The site says once — at install — what its stylesheet language is:

| | |
|---|---|
| **Bootstrap** | The framework's own CSS is linked, and a class like `btn btn-primary` means what it means |
| **Pico** | Classless: plain HTML is already dressed |
| **Plain CSS** | Nothing is linked. Everything comes from your own stylesheet |

It is a site-wide declaration rather than a layout's, because a class is
written on a block at the bottom and only means something if the whole site
agrees. **Every preview wears it too**, so what you see while editing is what
the page will be.

It is asked once, at `bladewright:install`. **There is no screen for
changing it afterwards yet** — `bladewright:install --fresh` asks again, and
that wipes the site, so choose it deliberately the first time.

## 2. The palette

**Settings → Colours** holds the site's colours by name — `ink`, `paper`,
`accent`, `rule`, and any you add. Blocks and components refer to them *by
name*, so changing what `accent` means changes every page that uses it, the
moment they are next asked for.

A name may hold a gradient as easily as a colour, since `background` takes
either.

## 3. The site's own stylesheet

**Settings → Stylesheet** is one CSS file for the whole site. It is served
with a version stamp that changes when the file does, so a fix reaches
visitors at once rather than after a cache expires.

This is where anything the cards cannot say belongs: `::before`, `:nth-child`,
keyframes, deep selectors, your own utility classes.

## 4. The cards

Most of the look is set on the parts themselves, and read back from them:

- **Colour** — text and background, from the palette by name or written in
- **Padding** — as a box, the way a browser's inspector shows it
- **Border** — thickness, colour, and which sides
- **Corners, shadow, transition**
- **Type** — size, line height, letter spacing, the four a writer knows
  (bold, italic, underline, strike), and alignment
- **Hover** — a colour or an opacity for when the pointer is on it
- **Arrangement** — stacked, a grid (`auto`, a count, or `1fr 2fr`), or a row
  with its own alignment; and whether small screens fold it into one column

The typeface is the **layout's** word — one font stack for every page wearing
that frame — because setting it block by block would be misery.

### Where a card's answer goes

Straight onto the element, as a `style` attribute. That is deliberate: the
Code face of every layer then shows the whole truth of a part in one place,
and generated code can be copied by hand without leaving anything behind.

The two things a style attribute cannot say — **a hover, and a screen width**
— are collected as the page renders and printed in the document's own
`<style>`, under a class the renderer gives and never stores.

## There is no build step

That is what "fix it in the browser without deploying" rests on. Where a build
decides which classes exist, a class added later quietly does nothing.

Using a build while authoring a block is of course fine — it is the *site's*
CSS that must not need one.

## Writing it yourself instead

Every layer has a **Code** face. Write there and it becomes that layer —
Blade of the site's own, run when the page is asked for. The layout's frame
is a whole HTML document; a page can be one too, DOCTYPE and all.

Empty it again and the arrangement leads once more.
