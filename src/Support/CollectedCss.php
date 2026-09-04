<?php

namespace Bladewright\Support;

/**
 * The rules a render gathers on its way through the parts.
 *
 * **A hover cannot be said inline**, and neither can a screen width — they
 * need a stylesheet. So the renderer collects what each part needs under a
 * machine class of its own, and the document prints the lot in one `<style>`
 * in its head: the page carries exactly the rules its parts asked for,
 * drafts included, and nothing has to be invalidated when a part changes.
 *
 * One collector a request (a singleton), drained once per document — the
 * body renders before any head that prints it.
 */
class CollectedCss
{
    /** @var array<string, true> Keyed by the rule itself: the same part twice is one rule. */
    private array $rules = [];

    public function rule(string $rule): void
    {
        // The one letter that could ever leave CSS: refused, not cleaned.
        if (str_contains($rule, '<')) {
            return;
        }

        $this->rules[$rule] = true;
    }

    /** Everything gathered since the last drain, and the bucket emptied. */
    public function flush(): string
    {
        $rules = array_keys($this->rules);
        $this->rules = [];

        return implode("\n", $rules);
    }

    /** The `<style>` a document's head prints — or nothing at all. */
    public function styleTag(): string
    {
        $css = $this->flush();

        return $css === '' ? '' : "<style>\n".$css."\n</style>";
    }
}
