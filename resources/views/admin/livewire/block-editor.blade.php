<?php

use Livewire\Component;
use Bladewright\Access\Abilities;
use Bladewright\Blocks\BlockManager;
use Bladewright\Models\Block;
use Bladewright\Site\PublicSite;
use Bladewright\Support\Toasts;

/*
 * Writing what a block says.
 *
 * **The form comes from one list** (`BlockManager::fieldsFor`), the same
 * keys the renderer reads — so the editor cannot drift from what the site
 * serves. The preview beside it is the block through the real renderer,
 * before anything is saved: **what you see is what the page will show.**
 */
new class extends Component
{
    use Toasts;
    use \Bladewright\Support\StyleCard;

    public Block $block;

    /**
     * Standing inside another screen (the page editor's panel): **the cards
     * only** — the preview beside it is that screen's own, and Save says so
     * to whoever holds it.
     */
    public bool $embedded = false;

    /**
     * Slimmer still: **the Contents card alone** — the face a block shows
     * inside its component's panel, where the words are the whole errand.
     */
    public bool $slim = false;

    /** @var array<string, mixed> */
    public array $data = [];

    /** Which media field is being chosen for, if any. */
    public ?string $picking = null;

    /** What kind of file that field wants — the picker offers only that. */
    public function pickingAccepts(): string
    {
        foreach ($this->fields() as $field) {
            if ($field['key'] === $this->picking) {
                return (string) ($field['accept'] ?? '');
            }
        }

        return '';
    }

    /** The element's class attribute, whole — the Class card's one field. */
    public string $class = '';

    /** The block's own HTML, as it stands on the Code pill. */
    public string $markup = '';

    /**
     * Is what is written still this block's own element, attributes and all?
     *
     * **Then the two are one thing** and each follows the other. Written into
     * something else, the code goes its own way.
     */
    public bool $mirrored = false;

    /** The one element each type is, for reading the code back. */
    private const TAGS = [
        'div' => 'div',
        'image' => 'img',
        'video' => 'video',
        'audio' => 'audio',
        'button' => 'button',
        'input' => 'input',
        'textarea' => 'textarea',
        'select' => 'select',
        'embed' => 'iframe',
    ];

    /**
     * Has anybody actually written that markup?
     *
     * **Until they have, the fields still make the block**, and the Code pill
     * shows what they make. The moment somebody types in there it becomes
     * theirs, and the fields stop reaching the page.
     */
    public bool $authored = false;

    public function mount(): void
    {
        $fields = app(BlockManager::class)->fieldsFor($this->block->type);
        $stored = $this->block->data ?? [];

        foreach ($fields as $field) {
            $value = $stored[$field['key']] ?? null;

            $this->data[$field['key']] = match ($field['kind']) {
                'options' => implode("\n", (array) ($value ?? [])),
                'pairs' => array_values(array_map(fn ($row) => [
                    'value' => (string) ($row['value'] ?? (is_string($row) ? $row : '')),
                    'label' => (string) ($row['label'] ?? (is_string($row) ? $row : '')),
                    'disabled' => ! empty($row['disabled']),
                ], (array) ($value ?? []))),
                // **A choice always stands on one of its own.** Nothing is
                // ever chosen from an empty dropdown.
                'choice' => isset($field['choices'][(string) ($value ?? '')])
                    ? (string) $value
                    : (string) array_key_first($field['choices']),
                default => (string) ($value ?? ''),
            };
        }

        $this->seedStyleCard((array) ($stored['style'] ?? []));

        $this->class = (string) ($stored['class'] ?? '');

        // **It starts from what is inside the element right now.** An empty
        // editor reads as "the contents are gone", when nothing was ever
        // written here — so the plain text is put in as the starting point.
        $this->markup = (string) ($stored['markup'] ?? '');
        $this->authored = $this->markup !== '';

        if (! $this->authored) {
            $this->markup = $this->generated();
        }

        $this->mirrored = ! $this->authored;
    }

    /** The one list the style card is drawn from — this type's slice of it. @return array<int, array<string, mixed>> */
    public function styleFields(): array
    {
        return app(BlockManager::class)->styleFieldsFor($this->block->type);
    }

    /** The Class card reaches the code the way every card does. */
    public function updatedClass(): void
    {
        $this->reseed();
    }

    /** Typing in the Code pill makes the markup somebody's own. */
    public function updatedMarkup(): void
    {
        $this->authored = true;

        // **The code and the fields are the same block.** What is written
        // there is read back into them, so nobody is looking at a card that
        // says one thing while the page says another.
        $this->readMarkup();
        $this->whisper();
    }

    /**
     * Read what is written back into the fields.
     *
     * **Only when it is still recognisably this block** — one element of the
     * type, with attributes on it. The moment somebody writes something else
     * (a loop, two elements, a wrapper of their own), the code is theirs
     * alone and the fields stop pretending to speak for it.
     */
    private function readMarkup(): void
    {
        $this->mirrored = false;

        if (trim($this->markup) === '') {
            return;
        }

        // **Blade is not an element.** A loop or a value worked out at render
        // time cannot be said by any field, so the code is its own from the
        // moment one appears.
        if (preg_match('/\{\{|\{!!|@[a-z]/i', $this->markup) === 1) {
            return;
        }

        if (in_array($this->block->type, ['radio', 'checkbox'], true)) {
            $this->readChoices();

            return;
        }

        $tag = self::TAGS[$this->block->type] ?? null;

        if ($tag === null) {
            return;
        }

        // **A meta tag rather than an XML prelude**: the prelude carries a
        // `?` and a `>`, and this file is cut in two at the first of those.
        $document = new \DOMDocument;
        $ok = @$document->loadHTML(
            '<meta charset="utf-8"><body>'.$this->markup.'</body>',
            LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_HTML_NODEFDTD,
        );

        if (! $ok) {
            return;
        }

        // **The first element of the kind speaks for the block** — best
        // effort, always: whatever else stands around it stays the code's
        // own, and the code wins where the fields cannot reach.
        $element = $document->getElementsByTagName($tag)->item(0);

        if ($element === null) {
            return;
        }

        // A field's label is an element of its own, inside the same block.
        $names = $document->getElementsByTagName('label');
        $naming = in_array($this->block->type, BlockManager::FIELDS, true) && $names->length >= 1
            ? $names->item(0)
            : null;

        foreach ($this->fields() as $field) {
            $key = $field['key'];

            $this->data[$key] = match (true) {
                $key === 'name' => preg_replace('/\[\]$/', '', $element->getAttribute('name')),
                // A textarea's value is its inside, not an attribute.
                $key === 'value' && $this->block->type === 'textarea' => trim($element->textContent),
                // The words that name a field are the label's; a button's
                // are its own.
                $key === 'label' && in_array($this->block->type, BlockManager::FIELDS, true) => $naming === null ? '' : trim($naming->textContent),
                $key === 'label', $key === 'text' => trim($element->textContent),
                $key === 'source' => $element->getAttribute('src'),
                $key === 'poster' => $element->getAttribute('poster'),
                // The link a picture is wrapped in.
                $key === 'href' && $this->block->type === 'image' => $element->parentNode?->nodeName === 'a'
                    ? $element->parentNode->getAttribute('href')
                    : '',
                $key === 'url' && $this->block->type === 'embed' => $element->getAttribute('src'),
                // A button that walks somewhere says so in its onclick.
                $key === 'url' => preg_match("/location\.href='(.*)'/", $element->getAttribute('onclick'), $said) === 1 ? stripslashes($said[1]) : '',
                $key === 'type' && $this->block->type === 'button' => $element->getAttribute('onclick') !== '' ? 'link' : $element->getAttribute('type'),
                $field['kind'] === 'flag' => $element->hasAttribute($key) ? '1' : '',
                $field['kind'] === 'pairs' => array_values(array_map(
                    fn ($option) => [
                        'value' => $option->getAttribute('value') !== '' ? $option->getAttribute('value') : trim($option->textContent),
                        'label' => trim($option->textContent),
                        'disabled' => $option->hasAttribute('disabled'),
                    ],
                    iterator_to_array($element->getElementsByTagName('option')),
                )),
                default => $element->getAttribute($key),
            };
        }

        // The look travels with it: what is on the element is what the Style
        // card should be showing — and the class what the Class card should.
        // Sanitized on the way back in: the machine's own classes in
        // generated code never reach the Class card.
        $this->class = app(BlockManager::class)->sanitizeClass($element->getAttribute('class'));
        $this->css = trim($element->getAttribute('style'));
        $this->updatedCss();

        $this->mirrored = true;
    }

    /**
     * What each type owns of its element's attributes.
     *
     * **The line between the fields and the code**: patching writes these
     * and only these, so a class, an aria word, anything the cards never
     * grew a control for, stays exactly as somebody wrote it.
     */
    private const OWNED = [
        'div' => ['class', 'style'],
        'image' => ['src', 'alt', 'width', 'height', 'loading', 'class', 'style'],
        'video' => ['src', 'poster', 'width', 'height', 'preload', 'controls', 'autoplay', 'loop', 'muted', 'playsinline', 'class', 'style'],
        'audio' => ['src', 'preload', 'controls', 'autoplay', 'loop', 'muted', 'class', 'style'],
        'button' => ['type', 'onclick', 'class', 'style'],
        'input' => ['type', 'name', 'placeholder', 'value', 'minlength', 'maxlength', 'pattern', 'title', 'min', 'max', 'step', 'accept', 'required', 'disabled', 'class', 'style'],
        'textarea' => ['name', 'placeholder', 'rows', 'minlength', 'maxlength', 'required', 'disabled', 'class', 'style'],
        'select' => ['name', 'required', 'disabled', 'multiple', 'class', 'style'],
        'embed' => ['src', 'title', 'width', 'height', 'loading', 'referrerpolicy', 'allowfullscreen', 'class', 'style'],
    ];

    /**
     * Write the fields into the written code, touching only what they own.
     *
     * **The code wins.** The cards cannot say everything, so they never
     * rewrite the whole: the element of the kind is replaced by a fresh one
     * carrying the fields' answers, every attribute the cards do not own is
     * carried over from the old, and everything around it — a class, a
     * `<br>`, a wrapper somebody wrote — stands untouched. Blade is the one
     * boundary: code that is worked out at render time cannot be patched,
     * and stands whole.
     */
    private function patchMarkup(): void
    {
        if (preg_match('/\{\{|\{!!|@[a-z]/i', $this->markup) === 1) {
            return;
        }

        $document = new \DOMDocument;
        $ok = @$document->loadHTML(
            '<meta charset="utf-8"><body>'.$this->markup.'</body>',
            LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_HTML_NODEFDTD,
        );

        if (! $ok) {
            return;
        }

        $fresh = new \DOMDocument;
        $made = $this->generated();

        if (trim($made) === '' || ! @$fresh->loadHTML(
            '<meta charset="utf-8"><body>'.$made.'</body>',
            LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_HTML_NODEFDTD,
        )) {
            return;
        }

        $patched = in_array($this->block->type, ['radio', 'checkbox'], true)
            ? $this->patchChoices($document, $fresh)
            : $this->patchElement($document, $fresh);

        if (! $patched) {
            return;
        }

        // The box's own look (where it sits, a group's colours) follows too.
        $this->patchBox($document, $fresh);

        $body = $document->getElementsByTagName('body')->item(0);
        $out = '';

        foreach ($body->childNodes as $node) {
            $out .= $document->saveHTML($node);
        }

        $this->markup = $out;
    }

    private function patchElement(\DOMDocument $document, \DOMDocument $fresh): bool
    {
        $tag = self::TAGS[$this->block->type] ?? null;

        if ($tag === null) {
            return false;
        }

        $old = $document->getElementsByTagName($tag)->item(0);
        $new = $fresh->getElementsByTagName($tag)->item(0);

        if ($old === null || $new === null) {
            return false;
        }

        // The machine's class is the renderer's, given fresh each serve —
        // never patched into somebody's written code.
        $stripped = app(BlockManager::class)->sanitizeClass($new->getAttribute('class'));
        $stripped === '' ? $new->removeAttribute('class') : $new->setAttribute('class', $stripped);

        // **Patched in place, never replaced**: the cards write only the
        // attributes they own, so a class or an aria word stands as written.
        foreach (self::OWNED[$this->block->type] ?? [] as $attribute) {
            if (! $new->hasAttribute($attribute)) {
                $old->removeAttribute($attribute);
            } elseif ($new->getAttribute($attribute) !== $old->getAttribute($attribute)) {
                $old->setAttribute($attribute, $new->getAttribute($attribute));
            }
        }

        // The inside is touched only when the cards changed what it says —
        // a <span> around unchanged words is the code's own business. A
        // select's options carry attributes too, so its inside is compared
        // whole rather than by its words.
        $changed = $this->block->type === 'select'
            ? $this->insideOf($old) !== $this->insideOf($new)
            : trim($old->textContent) !== trim($new->textContent);

        if ($changed) {
            foreach (iterator_to_array($old->childNodes) as $child) {
                $old->removeChild($child);
            }

            foreach (iterator_to_array($new->childNodes) as $child) {
                $old->appendChild($document->importNode($child, true));
            }
        }

        // A picture's link is a wrapper of the block's own: put on, taken
        // off and retargeted from the card, around whatever stands written.
        if ($this->block->type === 'image') {
            $href = trim((string) ($this->data['href'] ?? ''));
            $wrapped = $old->parentNode?->nodeName === 'a';

            if ($wrapped && $href === '') {
                $old->parentNode->parentNode->replaceChild($old, $old->parentNode);
            } elseif ($wrapped) {
                $old->parentNode->setAttribute('href', $href);
            } elseif ($href !== '') {
                $link = $document->createElement('a');
                $link->setAttribute('href', $href);
                $old->parentNode->replaceChild($link, $old);
                $link->appendChild($old);
            }
        }

        // The words that name a field live on its wrapping label, when the
        // written code has one; where it has none, none is invented.
        if (in_array($this->block->type, BlockManager::FIELDS, true)
            && $old->parentNode?->nodeName === 'label') {
            $wrapWords = trim(str_replace($old->textContent, '', $old->parentNode->textContent));

            if ($wrapWords !== trim((string) ($this->data['label'] ?? ''))) {
                $this->rewriteLabelWords($old->parentNode, $old, trim((string) ($this->data['label'] ?? '')));
            }
        }

        return true;
    }

    /** @return bool */
    private function patchChoices(\DOMDocument $document, \DOMDocument $fresh): bool
    {
        $freshInputs = [];

        foreach ($fresh->getElementsByTagName('input') as $one) {
            if ($one->getAttribute('type') === $this->block->type) {
                $freshInputs[] = $one;
            }
        }

        $oldInputs = [];

        foreach ($document->getElementsByTagName('input') as $one) {
            if ($one->getAttribute('type') === $this->block->type) {
                $oldInputs[] = $one;
            }
        }

        if ($oldInputs === [] || $freshInputs === []) {
            return false;
        }

        $count = min(count($oldInputs), count($freshInputs));

        for ($i = 0; $i < $count; $i++) {
            $old = $oldInputs[$i];
            $new = $freshInputs[$i];

            foreach (['name', 'value'] as $attribute) {
                $new->getAttribute($attribute) === ''
                    ? $old->removeAttribute($attribute)
                    : $old->setAttribute($attribute, $new->getAttribute($attribute));
            }

            foreach (['required', 'disabled'] as $flag) {
                $new->hasAttribute($flag)
                    ? $old->setAttribute($flag, '')
                    : $old->removeAttribute($flag);
            }

            if ($old->parentNode?->nodeName === 'label') {
                $this->rewriteLabelWords($old->parentNode, $old, trim($new->parentNode->textContent ?? ''));
            }
        }

        // Rows added on the card grow the code; rows taken away shrink it.
        for ($i = $count; $i < count($freshInputs); $i++) {
            $row = $document->importNode($freshInputs[$i]->parentNode, true);
            $anchorRow = $oldInputs[count($oldInputs) - 1];
            $anchor = $anchorRow->parentNode?->nodeName === 'label' ? $anchorRow->parentNode : $anchorRow;

            $anchor->parentNode->insertBefore($document->createTextNode("\n    "), $anchor->nextSibling);
            $anchor->parentNode->insertBefore($row, $anchor->nextSibling->nextSibling);
        }

        for ($i = count($freshInputs); $i < count($oldInputs); $i++) {
            $gone = $oldInputs[$i]->parentNode?->nodeName === 'label' ? $oldInputs[$i]->parentNode : $oldInputs[$i];
            $gone->parentNode?->removeChild($gone);
        }

        return true;
    }

    /** An element's inside, serialized for comparing. */
    private function insideOf(\DOMNode $element): string
    {
        $out = '';

        foreach ($element->childNodes as $child) {
            $out .= $element->ownerDocument->saveHTML($child);
        }

        return $out;
    }

    /** The words of a wrapping label, rewritten around the control it holds. */
    private function rewriteLabelWords(\DOMNode $label, \DOMNode $control, string $words): void
    {
        foreach (iterator_to_array($label->childNodes) as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                $label->removeChild($child);
            }
        }

        if ($words !== '') {
            $text = $label->ownerDocument->createTextNode(
                in_array($this->block->type, ['radio', 'checkbox'], true) ? ' '.$words : $words."\n    "
            );

            in_array($this->block->type, ['radio', 'checkbox'], true)
                ? $label->appendChild($text)
                : $label->insertBefore($text, $label->firstChild);
        }
    }

    /** The box's style — where it sits, a group's colours — follows the cards. */
    private function patchBox(\DOMDocument $document, \DOMDocument $fresh): void
    {
        $old = $this->rootBox($document);
        $new = $this->rootBox($fresh);

        if ($old === null || $new === null) {
            return;
        }

        $new->getAttribute('style') === ''
            ? $old->removeAttribute('style')
            : $old->setAttribute('style', $new->getAttribute('style'));

        // **Only where the box is the block's dress** — anywhere else, a
        // class on a wrapper is the code's own and stands untouched.
        if (in_array($this->block->type, ['markdown', 'radio', 'checkbox'], true)) {
            $stripped = app(BlockManager::class)->sanitizeClass($new->getAttribute('class'));

            $stripped === ''
                ? $old->removeAttribute('class')
                : $old->setAttribute('class', $stripped);
        }
    }

    private function rootBox(\DOMDocument $document): ?\DOMElement
    {
        $body = $document->getElementsByTagName('body')->item(0);

        foreach ($body?->childNodes ?? [] as $node) {
            if ($node->nodeName === 'div') {
                return $node;
            }
        }

        return null;
    }

    /**
     * Read a choice group back: every input of the kind is a row.
     *
     * **Best effort, always** — the name, the rows, the group's required and
     * each row's disabled are read into the card even when the code carries
     * something the fields cannot say (a `<br>`, a wrapper). The fields only
     * *lead* again (`$mirrored`) when nothing of that kind is there, so what
     * was written by hand is never quietly regenerated away.
     */
    private function readChoices(): void
    {
        $document = new \DOMDocument;
        $ok = @$document->loadHTML(
            '<meta charset="utf-8"><body>'.$this->markup.'</body>',
            LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_HTML_NODEFDTD,
        );

        if (! $ok) {
            return;
        }

        $inputs = [];

        foreach ($document->getElementsByTagName('input') as $one) {
            if ($one->getAttribute('type') === $this->block->type) {
                $inputs[] = $one;
            }
        }

        if ($inputs === []) {
            return;
        }

        $this->data['name'] = preg_replace('/\[\]$/', '', $inputs[0]->getAttribute('name'));

        $rows = [];
        $required = false;

        foreach ($inputs as $one) {
            $required = $required || $one->hasAttribute('required');

            $inLabel = $one->parentNode?->nodeName === 'label' ? trim($one->parentNode->textContent) : '';

            $rows[] = [
                'value' => $one->getAttribute('value'),
                'label' => $inLabel,
                'disabled' => $one->hasAttribute('disabled'),
            ];
        }

        $this->data['options'] = $rows;

        // A group wears its class on its box, so it is read from there.
        $this->class = app(BlockManager::class)->sanitizeClass($this->rootBox($document)?->getAttribute('class') ?? '');

        if (array_key_exists('required', $this->data)) {
            $this->data['required'] = $required ? '1' : '';
        }

        // The group heading, where the kind carries one: a label holding no
        // input of its own.
        if (array_key_exists('label', $this->data)) {
            foreach ($document->getElementsByTagName('label') as $label) {
                if ($label->getElementsByTagName('input')->length === 0) {
                    $this->data['label'] = trim($label->textContent);

                    break;
                }
            }
        }

        // Nothing here the rows cannot say again? Then they lead.
        $allowed = ['html', 'head', 'meta', 'body', 'div', 'label', 'input'];

        foreach ($document->getElementsByTagName('*') as $one) {
            if (! in_array($one->nodeName, $allowed, true)) {
                return;
            }
        }

        $this->mirrored = true;
    }

    /**
     * A card edit reaches the code, whatever state the code is in.
     *
     * **The code wins where the cards cannot say it** — a class, a wrapper,
     * a `<br>` all stand — and the cards win over exactly what they own.
     * Blade is the one boundary, in both directions.
     */
    public function updatedData(): void
    {
        // reseed() carries the change into the code — regenerating it while
        // nobody has written any, patching it in place while somebody has.
        $this->reseed();
    }

    /** What the fields make of this block — the Code pill's starting point. */
    public function generated(): string
    {
        return app(PublicSite::class)->generated($this->ghost(''));
    }

    /**
     * What is stored: nothing while the fields still lead, and nothing when
     * the box was emptied — never a render-and-compare, which could hand a
     * block back to its fields just because the code happened to match.
     */
    private function authoredMarkup(): string
    {
        return $this->authored && trim($this->markup) !== '' ? $this->markup : '';
    }

    /**
     * What the Blade in the Code pill went wrong with, if anything.
     *
     * **Shown where it can be fixed, not swallowed.** On the site a broken
     * block leaves a comment behind; here it has to be readable.
     */
    public function codeError(): ?string
    {
        if (! $this->authored || trim($this->markup) === '') {
            return null;
        }

        try {
            PublicSite::runBlade($this->markup);
        } catch (\Throwable $e) {
            return $e->getMessage();
        }

        return null;
    }

    /** One more choice to write in. */
    public function addChoice(string $key): void
    {
        $this->data[$key][] = ['value' => '', 'label' => '', 'disabled' => false];
    }

    public function removeChoice(string $key, int $index): void
    {
        unset($this->data[$key][$index]);

        $this->data[$key] = array_values($this->data[$key]);
        $this->reseed();
    }

    /**
     * The fields, with those sharing a `row` standing together.
     *
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function fieldRows(): array
    {
        $rows = [];

        foreach ($this->fields() as $field) {
            $key = $field['row'] ?? '_'.$field['key'];

            $rows[$key][] = $field;
        }

        return array_values($rows);
    }

    /**
     * The form. **Only what bears on the block as it stands** — a field that
     * carries `when` waits until the field it depends on says so.
     *
     * @return array<int, array{key: string, kind: string, label: string, choices?: array<string, string>, when?: array<string, string>}>
     */
    public function fields(): array
    {
        return array_values(array_filter(
            app(BlockManager::class)->fieldsFor($this->block->type),
            function (array $field): bool {
                foreach ($field['when'] ?? [] as $key => $wanted) {
                    if (! in_array(trim((string) ($this->data[$key] ?? '')), (array) $wanted, true)) {
                        return false;
                    }
                }

                return true;
            },
        ));
    }

    /** **One Save on the whole desk**: the page's button reaches in here. */
    #[\Livewire\Attributes\On('bw-save-part')]
    public function saveWithTheHolder(): void
    {
        if ($this->embedded) {
            $this->save();
        }
    }

    public function save(): void
    {
        $this->authorize(Abilities::gate(Abilities::EDIT_CONTENT));

        app(BlockManager::class)->saveContent($this->block, $this->data + [
            'markup' => $this->authoredMarkup(),
            'style' => $this->style,
            'class' => $this->class,
        ]);

        // **The reach is said with the result.** Editing here changes every
        // component that shows this block.
        $places = app(\Bladewright\Blocks\StructureManager::class)->placesShowing($this->block);

        $this->toast($places > 1
            ? __('Saved. It changed in :n places at once.', ['n' => $places])
            : __('Saved.'));

        // Whoever holds this panel refreshes their own preview on it.
        $this->dispatch('bw-block-saved');
    }

    public function pick(string $key): void
    {
        $this->authorize(Abilities::gate(Abilities::EDIT_CONTENT));

        $this->picking = $key;
    }

    /** Reached through the style card's listener — the one for the event. */
    public function mediaChosen(string $path): void
    {
        if ($this->picking === null) {
            return;
        }

        $this->data[$this->picking] = $path;
        $this->picking = null;

        // Setting the value by hand does not go through `updatedData`.
        $this->reseed();
    }

    public function clearMedia(string $key): void
    {
        $this->data[$key] = '';

        $this->reseed();
    }

    /** While nobody has written the markup, it follows the fields. */
    private function reseed(): void
    {
        $this->seedCss();

        if (! $this->authored) {
            $this->markup = $this->generated();
        } else {
            // **The code wins, and the cards reach into it**: what they own
            // is written into the written code, and the rest stands.
            $this->patchMarkup();
        }

        $this->whisper();
    }

    /**
     * **The holder hears every unsaved edit.** Embedded beside a page, the
     * block's current rendering rides up so the preview and the Code pill
     * show what is being typed before anything is saved.
     */
    private function whisper(): void
    {
        if ($this->embedded) {
            // Data, never HTML: the page renders the ghost in its own draft
            // context, stamps intact, nothing client-forged.
            $this->dispatch('bw-part-drafted',
                uuid: $this->block->uuid,
                kind: 'block',
                data: $this->ghost()->data,
            );
        }
    }

    /**
     * The block as the screen has it this moment — never saved.
     *
     * The markup is handed in rather than looked up: **what the fields make
     * is worked out from a ghost too**, and a ghost that asked would go round
     * for ever.
     */
    private function ghost(?string $markup = null): Block
    {
        $markup ??= $this->authoredMarkup();

        $ghost = new Block;
        $ghost->setRawAttributes($this->block->getAttributes());
        $ghost->data = collect($this->data)
            ->map(fn ($value, $key) => $key === 'options' && ! is_array($value)
                ? array_values(array_filter(array_map('trim', explode("\n", (string) $value))))
                : $value)
            ->all() + ['markup' => $markup, 'style' => $this->style, 'class' => $this->class];

        return $ghost;
    }

    /** The block as the site would serve it this moment. */
    private function asServed(Block $block): string
    {
        return app(PublicSite::class)->block($block);
    }

    /**
     * The block as the site will serve it, **rendered from what is on the
     * screen right now** — saved or not. The preview cannot lie about the
     * output, because it is the output.
     *
     * It comes back as a whole document for the frame: **inside an iframe
     * the admin's own CSS cannot reach it**, so what is shown is the block
     * on the site's terms, and the device widths mean something.
     */
    public function preview(): string
    {
        $inner = $this->asServed($this->ghost());
        // Rendering just gathered the part's hover rules — the frame's
        // head prints them, the way a page's own head would.
        $collected = app(\Bladewright\Support\CollectedCss::class)->styleTag();
        $lang = e(str_replace('_', '-', app()->getLocale()));

        $stylesheet = e(route('bladewright.site.css', ['v' => app(\Bladewright\Support\SiteCss::class)->version()]));

        // **The preview wears what the site wears** — the declared framework
        // — or a class like `btn btn-primary` would look like nothing here
        // while meaning everything on the page.
        $framework = app(\Bladewright\Support\Framework::class)->linkTag();

        return <<<HTML
        <!DOCTYPE html>
        <html lang="{$lang}">
        <head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
        {$framework}
        <link rel="stylesheet" href="{$stylesheet}">
        {$collected}
        <style>
            /* Only enough to read it by. The site's own frame is not here:
               what is being looked at is the block itself. **And no ink of
               this frame's own**: the framework and the colour scheme
               decide it, or a dark preview drowns a hard-coded grey. */
            body { margin: 0; padding: 1.5rem; font-family: ui-sans-serif, system-ui, sans-serif; line-height: 1.7; }
            img, video { max-width: 100%; height: auto; }
        </style>
        </head>
        <body>{$inner}</body>
        </html>
        HTML;
    }
};
?>

<div @class(['flex flex-col gap-4 lg:flex-row lg:items-start' => ! $embedded])>
    {{-- **The preview is the main thing, and it stands where the page does:
         on the left.** The writing tools take the narrower column beside it,
         the way the old editor held its workbench. Embedded in another
         screen, that screen's preview is the preview — only the cards come. --}}
    @unless ($embedded)
    {{-- **The preview follows.** The column beside it is long, and what is
         being changed has to stay in sight while it is changed. --}}
    <div class="min-w-0 flex-1 overflow-hidden rounded-xl border border-gray-200 bg-white lg:sticky lg:top-4 lg:self-start dark:border-gray-800 dark:bg-gray-900">
        <div class="flex flex-wrap items-center gap-3 border-b border-gray-100 px-3 py-2 dark:border-gray-800">
            {{-- What it looks like, and what it comes out as. **Switched in
                 the browser and remembered there**, the way the device widths
                 are: the page is not asked for again, and a reload comes back
                 to the pill that was being worked on. --}}
            <div class="inline-flex gap-1 rounded-lg bg-gray-200/70 p-0.5 dark:bg-gray-800">
                <button type="button" class="bw-pill inline-flex cursor-pointer items-center gap-1.5 rounded-md border-0 bg-transparent px-3 py-1 text-[0.8125rem]/5 font-medium text-gray-500 transition hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100"
                        data-bw-pills="block" data-bw-pill="preview"><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg>{{ __('Preview') }}</button>
                <button type="button" class="bw-pill inline-flex cursor-pointer items-center gap-1.5 rounded-md border-0 bg-transparent px-3 py-1 text-[0.8125rem]/5 font-medium text-gray-500 transition hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100"
                        data-bw-pills="block" data-bw-pill="code"><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m16 18 6-6-6-6"/><path d="m8 6-6 6 6 6"/></svg>{{ __('Code') }}</button>
            </div>

            <span class="hidden text-[0.75rem] text-gray-500 sm:inline dark:text-gray-400">{{ __('before it is saved') }}</span>

            <span class="flex-1"></span>

            {{-- The widths mean nothing beside the code, so they go with it. --}}
            <span class="flex" data-bw-pills="block" data-bw-panel="preview">
                @include('bladewright::admin.scheme-pills')
            </span>

            <div class="inline-flex gap-0.5 rounded-lg bg-gray-200/70 p-0.5 dark:bg-gray-800"
                 data-bw-pills="block" data-bw-panel="preview">
                <button type="button" class="bw-device bw-tip inline-flex h-7 cursor-pointer items-center justify-center rounded-md px-2 text-gray-500 transition dark:text-gray-400"
                        data-bw-device="desktop" data-tip="{{ __('Desktop') }}" aria-label="{{ __('Desktop') }}">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                        <rect x="3" y="4" width="18" height="12" rx="2"/><path stroke-linecap="round" d="M8 20h8m-4-4v4"/>
                    </svg>
                </button>
                <button type="button" class="bw-device bw-tip inline-flex h-7 cursor-pointer items-center justify-center rounded-md px-2 text-gray-500 transition dark:text-gray-400"
                        data-bw-device="tablet" data-tip="{{ __('Tablet') }}" aria-label="{{ __('Tablet') }}">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                        <rect x="5" y="3" width="14" height="18" rx="2"/><path stroke-linecap="round" d="M11 18h2"/>
                    </svg>
                </button>
                <button type="button" class="bw-device bw-tip inline-flex h-7 cursor-pointer items-center justify-center rounded-md px-2 text-gray-500 transition dark:text-gray-400"
                        data-bw-device="phone" data-tip="{{ __('Phone') }}" aria-label="{{ __('Phone') }}">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                        <rect x="7" y="2" width="10" height="20" rx="2.5"/><path stroke-linecap="round" d="M11 18.5h2"/>
                    </svg>
                </button>
            </div>

            <span class="bw-device-size font-mono text-[0.75rem] text-gray-400"
                  data-bw-pills="block" data-bw-panel="preview"></span>

            {{-- **Saving stands where the work is looked at.** The card
                 follows the scroll, so it is in reach wherever you are in the
                 column beside it. --}}
            @can(\Bladewright\Access\Abilities::gate(\Bladewright\Access\Abilities::EDIT_CONTENT))
                <button type="button" wire:click="save"
                        class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg bg-linear-to-tl from-bw-accent-2 to-bw-accent px-3 py-1.5 text-[0.8125rem] font-semibold text-white shadow-xs transition hover:brightness-110 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-bw-accent">
                    {{ __('Save') }}
                </button>
            @endcan
        </div>

        {{-- **The block on the site's terms.** srcdoc rather than a route,
             so it follows what is being typed rather than what was last
             saved. --}}
        <div class="bw-preview-stage bg-gray-100 dark:bg-gray-950"
             data-bw-pills="block" data-bw-panel="preview">
            <iframe class="block h-[60vh] w-full border-0 bg-white"
                    title="{{ __('Preview') }}"
                    srcdoc="{{ $this->preview() }}"></iframe>
        </div>

        {{-- **The block's own HTML.** It starts as what the fields make;
             change it and this is the block — Blade of the site's own,
             run when the page is asked for. --}}
        {{-- **What the server last worked out**, so the editor behind
             `wire:ignore` can catch up without the page being reloaded. --}}
        <div class="p-4" data-bw-pills="block" data-bw-panel="code" hidden
             data-bw-code-doc="{{ $markup }}">
            <p class="m-0 mb-2 text-[0.8125rem] text-gray-500 dark:text-gray-400">
                @if ($authored)
                    {{ __('Written by hand. The fields above no longer reach the page; empty this out to hand it back to them.') }}
                @elseif (trim($markup) === '')
                    {{-- **Empty because it is empty, not because it is broken.**
                         A block with nothing to show puts nothing on the page
                         rather than a half-made element. --}}
                    {{ __('There is nothing to show yet, so this block puts nothing on the page. Fill in the fields beside this, or write the HTML here yourself.') }}
                @else
                    {{ __('What the fields make. Change it and it becomes the block — Blade, so loops, values and queries all work.') }}
                @endif
            </p>

            @if ($error = $this->codeError())
                {{-- **Said where it can be fixed.** On the site the block
                     leaves a comment rather than taking the page down. --}}
                <div class="mb-2 rounded-lg border border-red-200 bg-red-50 px-3 py-2 font-mono text-[0.8125rem] text-red-900 dark:border-red-900 dark:bg-red-950 dark:text-red-100">
                    {{ $error }}
                </div>
            @endif

            {{-- **CodeMirror lives on a plain textarea** and never writes
                 to it, so the value reaches Livewire through this one. --}}
            <div wire:ignore>
                <textarea rows="16" data-bw-code="html" spellcheck="false"
                          class="w-full resize-y rounded-lg border border-gray-200 bg-gray-100 p-4 font-mono text-[0.8125rem]/6 focus:outline-2 focus:outline-offset-1 focus:outline-bw-accent dark:border-gray-700 dark:bg-gray-800"
                          wire:model.live.debounce.500ms="markup">{{ $markup }}</textarea>
            </div>
        </div>
    </div>

    @endunless

    <div @class(['w-full space-y-4', 'shrink-0 lg:w-[26rem]' => ! $embedded])>

    @if ($this->fields() !== [])
    {{-- Slim (inside a component's panel) the card sheds its chrome: the
         title above it already says whose words these are. --}}
    <div @class(['rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900' => ! $slim])>
        @unless ($slim)
        <h2 class="m-0 text-base font-semibold">{{ __('Contents') }}</h2>
        @endunless

        @foreach ($this->fieldRows() as $fields)
            @if (count($fields) > 1 && $fields[0]['kind'] === 'flag')
                {{-- Two little answers on one line, each saying its own name. --}}
                <div class="mt-4 flex flex-wrap items-center gap-5" wire:key="field-{{ $fields[0]['key'] }}">
                    @foreach ($fields as $field)
                        <label class="flex cursor-pointer items-center gap-2 font-mono text-[0.75rem] text-gray-600 dark:text-gray-400">
                            <input type="checkbox" class="rounded border-gray-300 text-bw-accent focus:ring-bw-accent/30 dark:border-gray-600 dark:bg-gray-950"
                                   wire:model.live="data.{{ $field['key'] }}">
                            {{ $field['label'] }}
                        </label>
                    @endforeach
                </div>

                @continue
            @elseif (count($fields) > 1)
                {{-- Three of a kind, said in one line. --}}
                <div class="mt-4 flex gap-2" wire:key="field-{{ $fields[0]['key'] }}">
                    @foreach ($fields as $field)
                        <div class="min-w-0 flex-1">
                            <label class="block font-mono text-[0.75rem] text-gray-600 dark:text-gray-400">{{ $field['label'] }}</label>
                            {{-- The empty box shows the shape the type wants. --}}
                            <input type="text" placeholder="{{ $field['placeholders'][$data['type'] ?? ''] ?? $field['placeholder'] ?? '' }}"
                                   class="mt-1.5 w-full rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 font-mono text-[0.75rem] shadow-xs transition focus:border-bw-accent focus:ring-2 focus:ring-bw-accent/20 focus:outline-none dark:border-gray-700 dark:bg-gray-950"
                                   wire:model.live.debounce.500ms="data.{{ $field['key'] }}">
                        </div>
                    @endforeach
                </div>

                @continue
            @endif

            @php($field = $fields[0])
            <div class="mt-4" wire:key="field-{{ $field['key'] }}">
                <label class="block font-mono text-[0.75rem] text-gray-600 dark:text-gray-400">{{ $field['label'] }}</label>

                @if ($field['kind'] === 'markdown')
                    {{-- The toolbar comes from admin.js (bold, links, lists).
                         Slim, in a tree of many, it starts low and stretches. --}}
                    <textarea rows="{{ $slim ? 4 : 14 }}" data-bw-markdown spellcheck="false"
                              class="mt-1.5 w-full resize-y rounded-lg border border-gray-300 bg-white p-3 font-mono text-[0.8125rem]/6 shadow-xs transition focus:border-bw-accent focus:ring-2 focus:ring-bw-accent/20 focus:outline-none dark:border-gray-700 dark:bg-gray-950"
                              wire:model.live.debounce.500ms="data.{{ $field['key'] }}"></textarea>

                @elseif ($field['kind'] === 'choice')
                    {{-- **A closed set is a dropdown.** Nothing to spell. --}}
                    <select class="w-full cursor-pointer rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-[0.8125rem] shadow-xs transition focus:border-bw-accent focus:ring-2 focus:ring-bw-accent/20 focus:outline-none dark:border-gray-700 dark:bg-gray-950"
                            wire:model.live="data.{{ $field['key'] }}">
                        @foreach ($field['choices'] as $value => $says)
                            <option value="{{ $value }}">{{ $says }}</option>
                        @endforeach
                    </select>

                @elseif ($field['kind'] === 'flag')
                    {{-- **On, or not there at all.** The name above it says
                         everything a word beside it would. --}}
                    <input type="checkbox" class="mt-1.5 rounded border-gray-300 text-bw-accent focus:ring-bw-accent/30 dark:border-gray-600 dark:bg-gray-950"
                           wire:model.live="data.{{ $field['key'] }}">

                @elseif ($field['kind'] === 'pairs')
                    {{-- **One row, one choice**: what is sent, what is read,
                         and the row's own required and disabled. --}}
                    <div class="mt-1.5 space-y-1.5">
                        <div class="flex items-center gap-2 pr-7">
                            <span class="min-w-0 flex-1 font-mono text-[0.6875rem] text-gray-400">value</span>
                            <span class="min-w-0 flex-1 font-mono text-[0.6875rem] text-gray-400">label</span>
                            <span class="w-8 shrink-0 font-mono text-[0.6875rem] text-gray-400">dis</span>
                        </div>

                        @foreach ($data[$field['key']] ?? [] as $index => $row)
                            <div class="flex items-center gap-2" wire:key="pair-{{ $field['key'] }}-{{ $index }}">
                                <input type="text" placeholder="option-{{ $index + 1 }}" aria-label="value"
                                       class="min-w-0 flex-1 rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 font-mono text-[0.75rem] shadow-xs transition focus:border-bw-accent focus:ring-2 focus:ring-bw-accent/20 focus:outline-none dark:border-gray-700 dark:bg-gray-950"
                                       wire:model.live.debounce.500ms="data.{{ $field['key'] }}.{{ $index }}.value">
                                <input type="text" placeholder="{{ __('Option :n', ['n' => $index + 1]) }}" aria-label="label"
                                       class="min-w-0 flex-1 rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-[0.75rem] shadow-xs transition focus:border-bw-accent focus:ring-2 focus:ring-bw-accent/20 focus:outline-none dark:border-gray-700 dark:bg-gray-950"
                                       wire:model.live.debounce.500ms="data.{{ $field['key'] }}.{{ $index }}.label">
                                <span class="flex w-8 shrink-0 items-center">
                                    <input type="checkbox" aria-label="disabled" class="bw-tip rounded border-gray-300 text-bw-accent focus:ring-bw-accent/30 dark:border-gray-600 dark:bg-gray-950"
                                           data-tip="disabled" wire:model.live="data.{{ $field['key'] }}.{{ $index }}.disabled">
                                </span>
                                <button type="button" wire:click="removeChoice('{{ $field['key'] }}', {{ $index }})"
                                        class="shrink-0 cursor-pointer rounded-md p-1 text-gray-400 transition hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950 dark:hover:text-red-400"
                                        aria-label="{{ __('Take it out') }}">
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/></svg>
                                </button>
                            </div>
                        @endforeach

                        <button type="button" wire:click="addChoice('{{ $field['key'] }}')"
                                class="inline-flex cursor-pointer items-center gap-1 rounded-md border border-gray-300 bg-white px-2.5 py-1.5 text-[0.75rem] font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">
                            {{ __('One more') }}
                        </button>
                    </div>

                @elseif ($field['kind'] === 'options')
                    <textarea rows="5"
                              class="mt-1.5 w-full resize-y rounded-lg border border-gray-300 bg-white p-3 text-sm shadow-xs transition focus:border-bw-accent focus:ring-2 focus:ring-bw-accent/20 focus:outline-none dark:border-gray-700 dark:bg-gray-950"
                              wire:model.live.debounce.500ms="data.{{ $field['key'] }}"></textarea>

                @elseif ($field['kind'] === 'media')
                    @php($chosen = trim((string) ($data[$field['key']] ?? '')))
                    <div class="mt-1.5">
                        @if ($chosen !== '' && ($file = app(\Bladewright\Media\MediaLibrary::class)->find($chosen)))
                            <img src="{{ $file->url() }}" alt="" class="mb-2 block max-h-32 rounded-lg">
                        @elseif ($chosen !== '')
                            <div class="mb-2 font-mono text-[0.8125rem] text-gray-500 dark:text-gray-400">{{ $chosen }}</div>
                        @else
                            <div class="mb-2 text-[0.8125rem] text-gray-500 dark:text-gray-400">{{ __('Nothing chosen yet.') }}</div>
                        @endif

                        <div class="flex flex-wrap items-center gap-2">
                            <button type="button" wire:click="pick('{{ $field['key'] }}')"
                                    class="inline-flex cursor-pointer items-center gap-1 rounded-md border border-gray-300 bg-white px-2.5 py-1.5 text-[0.8125rem] font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">
                                {{ $chosen === '' ? __('Choose from the media') : __('Choose another') }}
                            </button>
                            @if ($chosen !== '')
                                <button type="button" wire:click="clearMedia('{{ $field['key'] }}')"
                                        class="inline-flex cursor-pointer items-center gap-1 rounded-md border border-gray-300 bg-white px-2.5 py-1.5 text-[0.8125rem] font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">{{ __('Remove') }}</button>
                            @endif
                        </div>
                    </div>

                @else
                    <input type="text"
                           class="mt-1.5 w-full rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-[0.8125rem] shadow-xs transition focus:border-bw-accent focus:ring-2 focus:ring-bw-accent/20 focus:outline-none dark:border-gray-700 dark:bg-gray-950"
                           wire:model.live.debounce.500ms="data.{{ $field['key'] }}">
                @endif
            </div>
        @endforeach

        @if ($picking !== null)
            {{-- **Its own window.** Choosing a file is an errand of its own,
                 and a library needs more room than a column has. --}}
            <div data-bw-modal="pick-media" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/50" data-bw-modal-close wire:click="$set('picking', null)"></div>

                <div class="relative z-10 flex max-h-[85vh] w-full max-w-3xl flex-col overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xl dark:border-gray-800 dark:bg-gray-900">
                    <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3 dark:border-gray-800">
                        <span class="text-sm font-semibold">{{ __('Choose a file') }}</span>
                        <button type="button" data-bw-modal-close wire:click="$set('picking', null)"
                                class="cursor-pointer rounded-md p-1 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200" aria-label="{{ __('Cancel') }}">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/>
                            </svg>
                        </button>
                    </div>

                    <div class="overflow-y-auto p-5">
                        <livewire:bladewright::media-library :picking="true" :accept="$this->pickingAccepts()" wire:key="picker-{{ $picking }}" />
                    </div>
                </div>
            </div>
        @endif

        </div>
    @endif

        {{-- **How it looks — the block's own, not its spacing.** What stands
             between one block and the next belongs to the component; this is
             the thing itself. A hidden field has no look to speak of, so the
             card goes the way its label did. --}}
        @unless ($slim)
        @if (! ($block->type === 'input' && ($data['type'] ?? '') === 'hidden'))
            @include('bladewright::admin.class-card')
            @include('bladewright::admin.style-card')
        @endif
        @endunless

    </div>
</div>
