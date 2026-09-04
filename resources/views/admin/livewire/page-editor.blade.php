<?php

use Livewire\Component;
use Bladewright\Access\Abilities;
use Bladewright\Blocks\SitePages;
use Bladewright\Blocks\StructureManager;
use Bladewright\Models\Page;
use Bladewright\Models\Structure;
use Bladewright\Support\Toasts;

/*
 * Building a page: **the preview on the left, the arrangement on the right.**
 *
 * The preview is the real page in an iframe — `PublicSite::page`, published
 * or not — so what is being built is looked at, never imagined. The right
 * column is the row of components: add one, move one, take one off. The
 * words themselves are edited on the block screens; here they are arranged.
 */
new class extends Component
{
    use Toasts;

    public Page $page;

    /** Which component the picker would add. */
    public string $adding = '';

    /** The block being edited in the panel, by uuid — null is the tree. */
    public ?string $editing = null;

    /** Which tree row's words are open, by row key — one at a time. */
    public ?string $treeOpen = null;

    /** Whose words that row holds, for dropping its overlay on close. */
    public ?string $treeOpenUuid = null;

    /**
     * A block's title in the tree: **the words open under it, and the
     * panel opens beside it, in one press.** The same press closes both.
     * While the panel holds the block, the panel's editor is THE editor —
     * the tree's slim face steps aside rather than stand as a second copy
     * whose stale words could win the save.
     */
    public function openRow(string $key, string $uuid): void
    {
        if ($this->treeOpen === $key) {
            $this->closeEditor();

            return;
        }

        $this->select($uuid);
        $this->treeOpen = $key;
        $this->treeOpenUuid = $uuid;
    }

    /**
     * **Nothing lands until Save.** The preview's placing, dragging and
     * taking-off all work on these drafts; the page in the database — the
     * live page — does not move until the Save button says so.
     *
     * @var array<int, string>|null ordered component uuids; null = as saved
     */
    public ?array $draftRows = null;

    /** @var array<string, array<int, array{kind: string, uuid: string}>> unsaved rows per component uuid */
    public array $draftComponents = [];

    /**
     * What the open panels say right now, as data — **unsaved, worn over
     * the stored parts** so every face shows the typing as it happens.
     * PublicSite renders it in full draft context, stamps and all.
     *
     * @var array<string, array{kind: string, data: array<string, mixed>}> per part uuid
     */
    public array $draftParts = [];

    /** An open panel's every edit, heard and worn. */
    #[\Livewire\Attributes\On('bw-part-drafted')]
    public function partDrafted(string $uuid, string $kind, array $data): void
    {
        $this->draftParts[$uuid] = ['kind' => $kind, 'data' => $data];
        $this->refreshCode();
    }

    /**
     * While the arrangement leads, the Code pill follows it — **both ways
     * round**: type and the preview shows it, place and the code says it.
     */
    private function refreshCode(): void
    {
        if (! $this->authored) {
            $this->markup = $this->generated();
        }
    }

    /** Is there anything Save would land? **The written code counts too.** */
    public function dirty(): bool
    {
        return $this->draftRows !== null
            || $this->draftComponents !== []
            || $this->draftParts !== []
            || trim($this->authoredMarkup()) !== trim((string) (($this->page->data ?? [])['markup'] ?? ''));
    }

    /** The page's rows as the editor sees them: the draft, else the saved. @return array<int, string> */
    private function pageRows(): array
    {
        return $this->draftRows
            ?? $this->page->children()->orderBy('position')->pluck('child_uuid')->all();
    }

    /**
     * A component's rows as the editor sees them.
     *
     * @return array<int, array{kind: string, uuid: string}>
     */
    private function componentRows(string $uuid): array
    {
        if (isset($this->draftComponents[$uuid])) {
            return $this->draftComponents[$uuid];
        }

        $component = Structure::query()->where('uuid', $uuid)->first();

        return $component === null ? [] : $component->children
            ->map(fn ($child) => ['kind' => $child->child_kind, 'uuid' => $child->child_uuid])
            ->values()
            ->all();
    }



    /** Bumped when a block is saved from the panel, so the preview reloads. */
    public int $nonce = 0;

    /** The page's own HTML, as it stands on the Code pill. */
    public string $markup = '';

    /**
     * Has anybody actually written that markup?
     *
     * **Until they have, the arrangement still makes the page**, and the
     * Code pill shows what it makes.
     */
    public bool $authored = false;

    public function mount(): void
    {
        $this->markup = (string) (($this->page->data ?? [])['markup'] ?? '');
        $this->authored = $this->markup !== '';

        $this->refreshCode();
    }

    /**
     * What the four layers make of it — **the whole document**, DOCTYPE to
     * closing tag. The Code pill's starting point.
     */
    public function generated(): string
    {
        // The Code pill shows the same draft the preview does — one truth.
        return app(\Bladewright\Site\PublicSite::class)
            ->draft($this->draftRows, $this->draftComponents, $this->draftParts)
            ->assembledDocument($this->page);
    }

    /**
     * The page as the editor sees it: **the draft, stamped, whole** — what
     * srcdoc puts in the frame, so nothing lands in the database just to be
     * looked at.
     */
    public function preview(): string
    {
        // **From what is on the screen right now, saved or not** — the
        // drafted arrangement, and the Code pill's own words: a ghost page
        // wears the unsaved markup, the way the block editor's ghost does.
        $ghost = clone $this->page;
        $ghost->data = array_merge($this->page->data ?? [], ['markup' => $this->authoredMarkup()]);

        return app(\Bladewright\Site\PublicSite::class)
            ->draft($this->draftRows, $this->draftComponents, $this->draftParts)
            ->pageForEditing($ghost);
    }

    /** Typing in the Code pill makes the markup somebody's own. */
    public function updatedMarkup(): void
    {
        $this->authored = true;
    }

    /**
     * The preview was pressed: **the panel opens on what was pressed**,
     * where the tree stood — the words open their block, the space around
     * them opens their component. Nothing navigates.
     */
    public function select(string $uuid): void
    {
        // **Only the leaving panel's unsaid words go back** — a tree row's
        // own whispers stand until its editor truly closes.
        if ($this->editing !== null) {
            unset($this->draftParts[$this->editing]);
        }

        $this->editing = \Bladewright\Models\Block::query()->where('uuid', $uuid)->exists()
            || \Bladewright\Models\Structure::query()->where('uuid', $uuid)->exists()
            ? $uuid
            : null;

        $this->refreshCode();
    }

    /** Back from the panel to the tree. **Unsaid words go back too.** */
    public function closeEditor(): void
    {
        if ($this->editing !== null) {
            unset($this->draftParts[$this->editing]);
        }

        // The tree row rode with the panel; it closes with it, or its slim
        // editor would stand orphaned, whispering to nobody's panel.
        if ($this->treeOpen !== null) {
            unset($this->draftParts[$this->treeOpenUuid ?? '']);
            $this->treeOpen = null;
        }

        $this->editing = null;
        $this->refreshCode();
    }

    /**
     * The shelf the + opens in the preview: **every placeable component,
     * rendered small** — through the real renderer, inside the iframe, so
     * each miniature wears the site's own CSS. The layout's kinds are not
     * offered, the same as every other shelf.
     */
    public function componentShelf(): string
    {
        $tiles = [];

        $components = Structure::query()
            ->whereNotIn('type', \Bladewright\Models\Layout::BANDS_TYPES)
            ->orderBy('name')
            ->get();

        foreach ($components as $component) {
            $tiles[] = '<div data-bw-mini="'.e($component->name).'" role="button" tabindex="0"'
                .' style="flex:0 0 auto;width:240px;cursor:pointer;border:1px solid #d0d5dd;border-radius:10px;background:#fff;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.08)">'
                .'<div style="height:140px;overflow:hidden;pointer-events:none">'
                .'<div style="width:1200px;transform:scale(0.2);transform-origin:top left">'
                .app(\Bladewright\Site\PublicSite::class)->structure($component)
                .'</div></div>'
                .'<div style="padding:6px 10px;font:600 12px ui-monospace,monospace;color:#344054;background:#f9fafb;border-top:1px solid #e4e7ec;pointer-events:none">'.e($component->name).'</div>'
                .'</div>';
        }

        if ($tiles === []) {
            return '<div style="font:13px ui-sans-serif,system-ui;color:#667085;padding:8px 4px">'.e(__('There is nothing to put in yet. Make a component first.')).'</div>';
        }

        return implode('', $tiles);
    }

    /**
     * The shelf a block's + opens: **every block, rendered small** through
     * the real renderer, inside the iframe, wearing the site's own CSS.
     */
    public function blockShelf(): string
    {
        $tiles = [];

        foreach (\Bladewright\Models\Block::query()->orderBy('name')->get() as $block) {
            $render = app(\Bladewright\Site\PublicSite::class)->block($block);

            if (trim($render) === '') {
                continue;
            }

            $tiles[] = '<div data-bw-mini="'.e($block->name).'" role="button" tabindex="0"'
                .' style="flex:0 0 auto;width:240px;cursor:pointer;border:1px solid #d0d5dd;border-radius:10px;background:#fff;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.08)">'
                .'<div style="height:100px;overflow:hidden;pointer-events:none">'
                .'<div style="width:640px;transform:scale(0.35);transform-origin:top left;padding:8px">'
                .$render
                .'</div></div>'
                .'<div style="padding:6px 10px;font:600 12px ui-monospace,monospace;color:#344054;background:#f9fafb;border-top:1px solid #e4e7ec;pointer-events:none">'.e($block->name).'</div>'
                .'</div>';
        }

        if ($tiles === []) {
            return '<div style="font:13px ui-sans-serif,system-ui;color:#667085;padding:8px 4px">'.e(__('There is nothing to put in yet. Make a block first.')).'</div>';
        }

        return implode('', $tiles);
    }

    /** A block's miniature was pressed: **into that component's draft.** */
    public function placeBlock(string $componentUuid, string $name, int $at, bool $below): void
    {
        $this->authorize(Abilities::gate(Abilities::EDIT_CONTENT));

        $block = app(\Bladewright\Blocks\BlockManager::class)->find($name);
        $rows = $this->componentRows($componentUuid);

        if ($block === null || $at < 1 || $at > count($rows)) {
            return;
        }

        array_splice($rows, $below ? $at : $at - 1, 0, [['kind' => \Bladewright\Models\StructureChild::KIND_BLOCK, 'uuid' => $block->uuid]]);

        $this->draftComponents[$componentUuid] = array_values($rows);
        $this->refreshCode();

        $this->toast(__('Placed. It lands when the page is saved.'));
    }

    /** A miniature was pressed: **into the draft, right there.** */
    public function placeComponent(string $name, int $slot, bool $below): void
    {
        $this->authorize(Abilities::gate(Abilities::EDIT_CONTENT));

        $component = app(StructureManager::class)->find($name);
        $rows = $this->pageRows();

        if ($component === null || $slot < 1 || $slot > count($rows)) {
            return;
        }

        // The layout's own kinds stand in no page, drafted or not.
        if (in_array($component->type, \Bladewright\Models\Layout::BANDS_TYPES, true)) {
            $this->toastError(__('[:name] is a :type — it is placed on a layout, not in here.', ['name' => $component->name, 'type' => $component->type]));

            return;
        }

        array_splice($rows, $below ? $slot : $slot - 1, 0, [$component->uuid]);

        $this->draftRows = array_values($rows);
        $this->refreshCode();

        $this->toast(__('Placed. It lands when the page is saved.'));
    }

    /**
     * The × on a band: **off this page's draft** — the live page moves at
     * Save, and the component itself stays on the shelf either way.
     */
    public function removeSlot(int $slot): void
    {
        $this->authorize(Abilities::gate(Abilities::EDIT_CONTENT));

        $rows = $this->pageRows();

        if (! isset($rows[$slot - 1])) {
            return;
        }

        array_splice($rows, $slot - 1, 1);

        $this->draftRows = array_values($rows);
        $this->refreshCode();

        $this->toast(__('Taken off. It lands when the page is saved.'));
    }

    /**
     * The × on a block: **out of its component's draft** — the block itself
     * stays on the shelf, and nothing lands until Save.
     */
    public function removeBlockAt(string $componentUuid, int $at): void
    {
        $this->authorize(Abilities::gate(Abilities::EDIT_CONTENT));

        $rows = $this->componentRows($componentUuid);

        if (! isset($rows[$at - 1])) {
            return;
        }

        array_splice($rows, $at - 1, 1);

        $this->draftComponents[$componentUuid] = array_values($rows);
        $this->refreshCode();

        $this->toast(__('Taken out. It lands when the page is saved.'));
    }

    /**
     * A block was dragged in the preview: **put it where it was dropped,
     * within its own component** — the same rule as everywhere: a row moves
     * only inside its own parent. Places are 1-based, as the stamps carry
     * them; the destination is said as before or after another block.
     */
    public function moveBlock(string $componentUuid, int $from, int $to, bool $after): void
    {
        $this->authorize(Abilities::gate(Abilities::EDIT_CONTENT));

        $moved = $this->shifted($this->componentRows($componentUuid), $from, $to, $after);

        if ($moved !== null) {
            $this->draftComponents[$componentUuid] = $moved;
            $this->refreshCode();
        }
    }

    /**
     * One list, one move: **take it out, put it back where the hand said.**
     * 1-based places, the destination said as before or after another row.
     *
     * @template T
     *
     * @param  array<int, T>  $rows
     * @return array<int, T>|null null when nothing may move
     */
    private function shifted(array $rows, int $from, int $to, bool $after): ?array
    {
        $count = count($rows);

        if ($from < 1 || $from > $count || $to < 1 || $to > $count || $from === $to) {
            return null;
        }

        $landing = ($after ? $to : $to - 1) - ($from < $to ? 1 : 0);

        [$moving] = array_splice($rows, $from - 1, 1);
        array_splice($rows, $landing, 0, [$moving]);

        return array_values($rows);
    }

    /**
     * A band was dragged in the preview: **put it where it was dropped.**
     * Slots are 1-based, the way the stamps carry them; the destination is
     * said as above or below another band, the way a hand says it.
     */
    public function moveSlot(int $from, int $to, bool $below): void
    {
        $this->authorize(Abilities::gate(Abilities::EDIT_CONTENT));

        $moved = $this->shifted($this->pageRows(), $from, $to, $below);

        if ($moved !== null) {
            $this->draftRows = $moved;
            $this->refreshCode();
        }
    }

    /** What the panel holds — a block or a component — if it is still there. */
    public function editingPart(): \Bladewright\Models\Block|Structure|null
    {
        if ($this->editing === null) {
            return null;
        }

        return \Bladewright\Models\Block::query()->where('uuid', $this->editing)->first()
            ?? Structure::query()->where('uuid', $this->editing)->first();
    }

    /** The panel saved: the preview follows. */
    #[\Livewire\Attributes\On('bw-block-saved')]
    #[\Livewire\Attributes\On('bw-component-saved')]
    public function blockSaved(): void
    {
        $this->draftParts = [];
        $this->nonce++;
        $this->refreshCode();
    }

    /**
     * Words typed straight into the preview, written back to their block.
     *
     * **Markdown only, and only when it is unmistakable**: the words live in
     * markdown (the four-layer rule), and the rendered text must be found
     * exactly once in the source — bold, links or a repeat make it ambiguous,
     * and nothing ambiguous is ever guessed at. Then the panel opens instead,
     * where the change can be said precisely.
     */
    public function inlineText(string $uuid, string $was, string $now): void
    {
        $this->authorize(Abilities::gate(Abilities::EDIT_CONTENT));

        $block = \Bladewright\Models\Block::query()->where('uuid', $uuid)->first();

        if ($block === null) {
            return;
        }

        $body = (string) (($block->data ?? [])['body'] ?? '');
        $was = trim($was);

        $sayable = $block->type === 'markdown'
            && trim((string) (($block->data ?? [])['markup'] ?? '')) === ''
            && $was !== ''
            && substr_count($body, $was) === 1;

        if (! $sayable) {
            // The preview reloads (the typed words revert), and the panel
            // opens where it can be done properly.
            $this->editing = $uuid;
            $this->nonce++;
            $this->toastError(__('That spot carries formatting or repeats itself — say it on the card.'));

            return;
        }

        app(\Bladewright\Blocks\BlockManager::class)->saveContent($block, array_merge(
            $block->data ?? [],
            ['body' => str_replace($was, trim($now), $body)],
        ));

        $this->nonce++;
        $this->refreshCode();
        $this->toast(__('Saved.'));
    }

    /**
     * What is stored: nothing while the arrangement still leads, and
     * nothing when the box was emptied — never a render-and-compare, which
     * both cost a full document render on every dirty check and let Save
     * silently erase stored markup that happened to equal the generated.
     */
    private function authoredMarkup(): string
    {
        return $this->authored && trim($this->markup) !== '' ? $this->markup : '';
    }

    /**
     * What the Blade in the Code pill went wrong with, if anything.
     *
     * **Said where it can be fixed.** On the site a broken page leaves a
     * comment in the frame rather than taking it down.
     */
    public function codeError(): ?string
    {
        if (! $this->authored || trim($this->markup) === '') {
            return null;
        }

        try {
            \Bladewright\Site\PublicSite::runBlade($this->markup);
        } catch (\Throwable $e) {
            return $e->getMessage();
        }

        return null;
    }

    /**
     * **Save is where everything lands**: the drafted arrangement — the
     * page's rows and every drafted component's — and the markup, in that
     * order. Until it is pressed, the live page has not moved.
     */
    public function save(): void
    {
        $this->authorize(Abilities::gate(Abilities::EDIT_CONTENT));

        if ($this->draftRows !== null) {
            $this->page->children()->delete();

            foreach (array_values($this->draftRows) as $index => $uuid) {
                \Bladewright\Models\PageChild::create([
                    'page_id' => $this->page->id,
                    'child_uuid' => $uuid,
                    'position' => $index + 1,
                ]);
            }

            $this->draftRows = null;
        }

        foreach ($this->draftComponents as $uuid => $rows) {
            $component = Structure::query()->where('uuid', $uuid)->first();

            if ($component === null) {
                continue;
            }

            $component->children()->delete();

            foreach (array_values($rows) as $index => $row) {
                \Bladewright\Models\StructureChild::create([
                    'structure_id' => $component->id,
                    'child_kind' => $row['kind'],
                    'child_uuid' => $row['uuid'],
                    'position' => $index + 1,
                ]);
            }
        }

        $this->draftComponents = [];

        app(SitePages::class)->saveMarkup($this->page, $this->authoredMarkup());

        // The open panel's cards land with the same press. **The nonce must
        // not move here**: it stands in the panels' keys, and bumping it in
        // this same response would replace the very editors holding the
        // unsaved cards with fresh ones before the event could reach them —
        // the fresh copy would then "save" the database over the typing.
        // The editors answer with bw-block-saved, and *that* bumps it.
        $this->dispatch('bw-save-part');

        $this->page->refresh();

        $this->toast(__('Saved.'));
    }

    /**
     * The whole arrangement, as a tree: the page's components, and inside
     * each one its blocks (and nested components, should they come).
     *
     * @return array<int, array<string, mixed>>
     */
    public function tree(): array
    {
        // **The tree reads the same draft the preview shows** — one truth,
        // unsaved or not.
        $own = collect($this->pageRows())
            ->map(fn ($uuid, $index) => ($node = $this->node(
                \Bladewright\Models\StructureChild::KIND_COMPONENT,
                $uuid,
                (string) $index,
            )) === null
                ? null
                : ['remove' => 'removeSlot('.($index + 1).')'] + $node)
            ->filter()
            ->values()
            ->all();

        // **The whole page, not only its own rows**: the header and footer
        // the frame wears stand where a visitor meets them — read here,
        // dressed on the layout's screen.
        $layout = $this->page->layout_uuid === null
            ? null
            : \Bladewright\Models\Layout::query()->where('uuid', $this->page->layout_uuid)->first();

        if ($layout === null) {
            return $own;
        }

        return [$this->band($layout, 'header'), ...$own, $this->band($layout, 'footer')];
    }

    /** One of the frame's bands, as a row the tree can show. */
    private function band(\Bladewright\Models\Layout $layout, string $band): array
    {
        $component = app(\Bladewright\Blocks\LayoutManager::class)->worn($layout, $band);

        return [
            'kind' => $component === null ? 'empty' : 'component',
            'type' => $band,
            'name' => $component?->name ?? __('Nothing here yet.'),
            // **A row opens its cards here**, the way a press on the preview
            // does; a bare band still links to the frame's own screen.
            'url' => $component === null ? route('bladewright.admin.layouts.edit', $layout) : null,
            'press' => $component === null ? null : "select('".$component->uuid."')",
            'path' => null,
            'movable' => false,
            'remove' => null,
            'children' => $component === null ? [] : collect($this->componentRows($component->uuid))
                ->map(fn ($row, $index) => $this->node($row['kind'], $row['uuid'], 'band-'.$band.'-'.$index, live: false))
                ->filter()
                ->values()
                ->all(),
        ];
    }

    /**
     * Put what was dragged where it was dropped — **at whatever depth it was
     * dragged at.** A path is "2" for the page's own row and "2.1" for what
     * stands inside it, so the last step says where in its own parent it
     * lands and the steps before it say whose parent that is.
     *
     * Only within one parent: the browser refuses a drop across two, and so
     * does this.
     */
    public function moveTo(string $from, string $to): void
    {
        $this->authorize(Abilities::gate(Abilities::EDIT_CONTENT));

        $fromParts = explode('.', $from);
        $toParts = explode('.', $to);

        foreach ([...$fromParts, ...$toParts] as $part) {
            if (! ctype_digit($part)) {
                return;
            }
        }

        if (array_slice($fromParts, 0, -1) !== array_slice($toParts, 0, -1)) {
            return;
        }

        $at = (int) end($fromParts);
        $onto = (int) end($toParts);
        $parent = array_slice($fromParts, 0, -1);

        // **Into the draft, at either depth** — the splice semantics the
        // reorder always had: the moving row lands at the target's index.
        if ($parent === []) {
            $rows = $this->pageRows();

            if (isset($rows[$at], $rows[$onto]) && $at !== $onto) {
                [$moving] = array_splice($rows, $at, 1);
                array_splice($rows, $onto, 0, [$moving]);
                $this->draftRows = array_values($rows);
                $this->refreshCode();
            }
        } elseif (($uuid = $this->componentUuidAt($parent)) !== null) {
            $rows = $this->componentRows($uuid);

            if (isset($rows[$at], $rows[$onto]) && $at !== $onto) {
                [$moving] = array_splice($rows, $at, 1);
                array_splice($rows, $onto, 0, [$moving]);
                $this->draftComponents[$uuid] = array_values($rows);
                $this->refreshCode();
            }
        }
    }

    /**
     * The component a path points at, **walking the drafts**.
     *
     * @param  array<int, string>  $path
     */
    private function componentUuidAt(array $path): ?string
    {
        $uuid = $this->pageRows()[(int) array_shift($path)] ?? null;

        foreach ($path as $index) {
            if ($uuid === null) {
                return null;
            }

            $row = $this->componentRows($uuid)[(int) $index] ?? null;

            $uuid = ($row['kind'] ?? null) === \Bladewright\Models\StructureChild::KIND_COMPONENT
                ? $row['uuid']
                : null;
        }

        return $uuid;
    }

    /**
     * One place in the arrangement, and everything under it.
     *
     * **Everything can be dragged, at every depth** — the order is what the
     * page shows, and it is changed by moving the thing itself. A row moves
     * only within its own parent; taking something out is still done where it
     * belongs (the page here, the component's own screen below).
     *
     * @return array<string, mixed>|null
     */
    private function node(string $kind, string $uuid, string $path, bool $live = true): ?array
    {
        if ($kind === \Bladewright\Models\StructureChild::KIND_BLOCK) {
            $block = \Bladewright\Models\Block::query()->where('uuid', $uuid)->first();

            return $block === null ? null : [
                'kind' => 'block',
                'name' => $block->name,
                'type' => $block->type,
                'url' => null,
                'press' => "openRow('tree-".$block->uuid.'-'.$path."', '".$block->uuid."')",
                // **The words open under the title on its press** — the slim
                // face, keyed by the row so the same block twice stays two.
                'inline' => $block->uuid,
                'inline_key' => 'tree-'.$block->uuid.'-'.$path.'-'.$this->nonce,
                'inline_open' => $this->treeOpen === 'tree-'.$block->uuid.'-'.$path
                    && $this->editing !== $block->uuid,
                'children' => [],
                'path' => $live ? $path : null,
                'movable' => $live,
                'remove' => null,
            ];
        }

        $structure = Structure::query()->where('uuid', $uuid)->first();

        if ($structure === null) {
            return null;
        }

        return [
            'kind' => 'component',
            'name' => $structure->name,
            'type' => $structure->type,
            'url' => null,
            'press' => "select('".$structure->uuid."')",
            'path' => $live ? $path : null,
            'movable' => $live,
            'remove' => null,
            'children' => collect($this->componentRows($structure->uuid))
                ->map(fn ($row, $index) => $this->node($row['kind'], $row['uuid'], $path.'.'.$index, $live))
                ->filter()
                ->values()
                ->all(),
        ];
    }

    /** @return array<int, string> */
    public function available(): array
    {
        // The layout's own kinds are not on this shelf.
        return Structure::query()->whereNotIn('type', \Bladewright\Models\Layout::BANDS_TYPES)->orderBy('name')->pluck('name')->all();
    }

    public function add(): void
    {
        $this->authorize(Abilities::gate(Abilities::EDIT_CONTENT));

        $component = app(StructureManager::class)->find($this->adding);

        if ($component === null) {
            $this->toastError(__('Choose a component first.'));

            return;
        }

        if (in_array($component->type, \Bladewright\Models\Layout::BANDS_TYPES, true)) {
            $this->toastError(__('[:name] is a :type — it is placed on a layout, not in here.', ['name' => $component->name, 'type' => $component->type]));

            return;
        }

        $this->draftRows = [...$this->pageRows(), $component->uuid];
        $this->adding = '';
        $this->refreshCode();

        $this->toast(__('Placed. It lands when the page is saved.'));
    }


};
?>

<div class="flex flex-col gap-4 lg:flex-row lg:items-start">
    {{-- **One card, two faces.** The pills live in the card's own header at
         the left edge, so switching happens inside the thing being switched
         rather than floating above it. --}}
    {{-- **The preview follows.** The column beside it is long, and what is
         being changed has to stay in sight while it is changed. --}}
    <div class="min-w-0 flex-1 overflow-hidden rounded-xl border border-gray-200 bg-white lg:sticky lg:top-4 lg:self-start dark:border-gray-800 dark:bg-gray-900">
        <div class="flex flex-wrap items-center gap-3 border-b border-gray-100 px-3 py-2 dark:border-gray-800">
            {{-- Two faces, the same pills as everywhere: what a visitor
                 meets, and the HTML the arrangement makes — **and that last
                 one can be written**, the bargain every layer makes. --}}
            <div class="inline-flex gap-1 rounded-lg bg-gray-200/70 p-0.5 dark:bg-gray-800">
                <button type="button" class="bw-pill inline-flex cursor-pointer items-center gap-1.5 rounded-md border-0 bg-transparent px-3 py-1 text-[0.8125rem]/5 font-medium text-gray-500 transition hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100"
                        data-bw-pills="page" data-bw-pill="preview"><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg>{{ __('Preview') }}</button>
                <button type="button" class="bw-pill inline-flex cursor-pointer items-center gap-1.5 rounded-md border-0 bg-transparent px-3 py-1 text-[0.8125rem]/5 font-medium text-gray-500 transition hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100"
                        data-bw-pills="page" data-bw-pill="structure"><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12h-8"/><path d="M21 6H8"/><path d="M21 18h-8"/><path d="M3 6v4c0 1.1.9 2 2 2h3"/><path d="M3 10v6c0 1.1.9 2 2 2h3"/></svg>{{ __('Structure') }}</button>
                <button type="button" class="bw-pill inline-flex cursor-pointer items-center gap-1.5 rounded-md border-0 bg-transparent px-3 py-1 text-[0.8125rem]/5 font-medium text-gray-500 transition hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100"
                        data-bw-pills="page" data-bw-pill="code"><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m16 18 6-6-6-6"/><path d="m8 6-6 6 6 6"/></svg>{{ __('Code') }}</button>
            </div>

            <span class="flex-1"></span>

            {{-- **Only the frame narrows**, so nothing reloads and no state is
                 rebuilt — the iframe is left alone (admin.js keeps the choice
                 in the browser). --}}
            <span class="flex" data-bw-pills="page" data-bw-panel="preview">
                @include('bladewright::admin.scheme-pills')
            </span>

            <div data-bw-pills="page" data-bw-panel="preview" class="inline-flex gap-0.5 rounded-lg bg-gray-200/70 p-0.5 dark:bg-gray-800">
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

            {{-- The width being looked at, in plain numbers. --}}
            <span data-bw-pills="page" data-bw-panel="preview" class="bw-device-size font-mono text-[0.75rem] text-gray-400"></span>

            {{-- **Nothing lands until Save** — not a drag, not a +, not an ×.
                 The dot says something is waiting. --}}
            @can(\Bladewright\Access\Abilities::gate(\Bladewright\Access\Abilities::EDIT_CONTENT))
                <button type="button" wire:click="save"
                        class="relative inline-flex cursor-pointer items-center gap-1.5 rounded-lg bg-linear-to-tl from-bw-accent-2 to-bw-accent px-3 py-1.5 text-[0.8125rem] font-semibold text-white shadow-xs transition hover:brightness-110">
                    {{-- lucide: save --}}
                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/>
                        <path d="M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7"/>
                        <path d="M7 3v4a1 1 0 0 0 1 1h7"/>
                    </svg>
                    {{ __('Save') }}
                    @if ($this->dirty())
                        <span class="absolute -top-1 -right-1 h-2.5 w-2.5 rounded-full border-2 border-white bg-amber-400 dark:border-gray-900"></span>
                    @endif
                </button>
            @endcan
        </div>

        {{-- **The real page, in an iframe.** The site's CSS never touches
             the admin's; what is in the frame is PublicSite over today's
             state. The arrangement is not a face of this card: it is the
             column beside it, where it can be dragged. --}}
        <div class="bw-preview-stage bg-gray-100 dark:bg-gray-950"
             data-bw-pills="page" data-bw-panel="preview">
            {{-- **srcdoc, so the draft can be looked at without landing.**
                 Every block and band is stamped with its uuid; `data-bw-editable`
                 is what admin.js reaches in through: hover to see a piece,
                 press to open its cards, press twice to type. --}}
            {{-- **Down to the bottom of the window at rest.** The height is
                 fixed — growing it on scroll eats the sticky card's slack
                 and unpins the toolbar, which is worse than the gap. --}}
            <iframe class="block h-[calc(100dvh-10.5rem)] min-h-[30rem] w-full border-0 bg-white" data-bw-editable
                    title="{{ __('Preview') }}"
                    srcdoc="{{ $this->preview() }}"></iframe>
        </div>

        {{-- **The arrangement, as a face of the same card.** What it used to
             do alone — placing, dragging, taking out — the preview does in
             place now; this face is the page read as a list. --}}
        <div class="p-4" data-bw-pills="page" data-bw-panel="structure" hidden>
            <p class="m-0 mb-3 text-[0.8125rem] text-gray-500 dark:text-gray-400">
                {{ __('Components, top to bottom. Drag to change the order; what is inside them is arranged on their own screens.') }}
            </p>

            @if ($authored)
                {{-- **Said, not hidden.** What is on the Code pill is what the
                     page shows; this is still the way back. --}}
                <div class="mb-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-[0.8125rem] text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-100">
                    {{ __('The code is written by hand, so neither this arrangement nor the layout is what the page shows. Empty the Code pill to hand it back.') }}
                </div>
            @endif

            @php($tree = $this->tree())

            @if ($tree === [])
                <p class="text-[0.8125rem] text-gray-500 dark:text-gray-400">{{ __('Nothing on it yet.') }}</p>
            @else
                {{-- **Dragged into place.** The order is what a visitor meets,
                     so it is changed by moving the thing itself. What is
                     inside a component is read here and arranged there. --}}
                <ul class="m-0 list-none space-y-1.5 p-0">
                    @foreach ($tree as $node)
                        @include('bladewright::admin.arrange-node', ['node' => $node, 'depth' => 0])
                    @endforeach
                </ul>
            @endif

            @can(\Bladewright\Access\Abilities::gate(\Bladewright\Access\Abilities::EDIT_CONTENT))
                @php($available = $this->available())
                <div class="mt-4 border-t border-gray-100 pt-4 dark:border-gray-800">
                    @if ($available === [])
                        <p class="m-0 text-[0.8125rem] text-gray-500 dark:text-gray-400">
                            {{ __('There is nothing to put in yet. Make a component first.') }}
                        </p>
                    @else
                        <div class="flex max-w-md items-center gap-2">
                            <select class="min-w-0 flex-1 rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-[0.8125rem] shadow-xs transition focus:border-bw-accent focus:ring-2 focus:ring-bw-accent/20 focus:outline-none dark:border-gray-700 dark:bg-gray-950"
                                    wire:model="adding">
                                <option value="">{{ __('Choose a component…') }}</option>
                                @foreach ($available as $name)
                                    <option value="{{ $name }}">{{ $name }}</option>
                                @endforeach
                            </select>
                            <button type="button" wire:click="add"
                                    class="inline-flex shrink-0 cursor-pointer items-center gap-1.5 rounded-lg bg-linear-to-tl from-bw-accent-2 to-bw-accent px-3 py-1.5 text-[0.8125rem] font-semibold text-white shadow-xs transition hover:brightness-110">
                                {{ __('Place it') }}
                            </button>
                        </div>
                    @endif
                </div>
            @endcan
        </div>

        {{-- **The whole document** — DOCTYPE to closing tag, frame and all.
             It starts as what the four layers make; write it and this is
             the page, whole. --}}
        <div class="p-4" data-bw-pills="page" data-bw-panel="code" hidden
             data-bw-code-doc="{{ $markup }}">
            <p class="m-0 mb-2 text-[0.8125rem] text-gray-500 dark:text-gray-400">
                {{ $authored
                    ? __('Written by hand. Neither the arrangement nor the layout reaches this page; empty this out to hand it back.')
                    : __('The whole document the four layers make. Change it and it becomes the page — all of it, frame included.') }}
            </p>

            @if ($error = $this->codeError())
                <div class="mb-2 rounded-lg border border-red-200 bg-red-50 px-3 py-2 font-mono text-[0.8125rem] text-red-900 dark:border-red-900 dark:bg-red-950 dark:text-red-100">
                    {{ $error }}
                </div>
            @endif

            <div wire:ignore>
                <textarea rows="20" data-bw-code="html" spellcheck="false"
                          class="w-full resize-y rounded-lg border border-gray-200 bg-gray-100 p-4 font-mono text-[0.8125rem]/6 focus:outline-2 focus:outline-offset-1 focus:outline-bw-accent dark:border-gray-700 dark:bg-gray-800"
                          wire:model.live.debounce.500ms="markup">{{ $markup }}</textarea>
            </div>
        </div>
    </div>

    {{-- **The column exists only while something is pressed.** The preview
         is the whole desk otherwise — placing, dragging and taking out all
         happen on it, and the Structure face reads the page as a list. --}}
    @if (($editingPart = $this->editingPart()) !== null)
    {{-- Slides in on its own (the CSS animates fresh arrivals); sliding out
         is the button's doing below, a beat before the close. --}}
    <div class="bw-panel w-full shrink-0 lg:w-[26rem]" data-bw-panel-card>
            {{-- **The pressed thing's own cards, in place.** The same editor
                 as its own screen, without a second preview: the page beside
                 it is the preview. The words open their block; the space
                 around them opens their component. --}}
            <div class="space-y-4">
                {{-- **A header bar the same height as the preview's toolbar**,
                     so the two columns start on one line: the way back, whose
                     cards these are, and the door to their own screen. --}}
                <div class="flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-3 py-2 dark:border-gray-800 dark:bg-gray-900">
                    {{-- The slide happens first, the close a beat later —
                         Livewire removes the node the moment the state says
                         so, and a removed node cannot animate. --}}
                    <button type="button" aria-label="{{ __('Back to the page') }}"
                            x-on:click="$el.closest('[data-bw-panel-card]').classList.add('bw-panel-leaving'); setTimeout(() => $wire.closeEditor(), 200)"
                            class="bw-tip shrink-0 cursor-pointer rounded-md p-1.5 text-gray-500 transition hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-100"
                            data-tip="{{ __('Back to the page') }}">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
                    </button>

                    <span class="min-w-0 flex-1 truncate text-sm font-semibold">{{ $editingPart->name }}</span>

                    <a href="{{ $editingPart instanceof \Bladewright\Models\Block
                            ? route('bladewright.admin.blocks.edit', $editingPart)
                            : route('bladewright.admin.components.edit', $editingPart) }}"
                       class="shrink-0 text-[0.8125rem] text-gray-500 hover:underline dark:text-gray-400">{{ __('its own screen') }}</a>
                </div>

                {{-- The nonce rides in the key: **whatever changed the page
                     reseeds the panel too**, so words typed on the preview
                     stand on the cards the moment they land. --}}
                @if ($editingPart instanceof \Bladewright\Models\Block)
                    <livewire:bladewright::block-editor :block="$editingPart" :embedded="true" :key="'panel-'.$editingPart->uuid.'-'.$nonce" />
                @else
                    <livewire:bladewright::component-editor :component="$editingPart" :embedded="true" :key="'panel-'.$editingPart->uuid.'-'.$nonce" />
                @endif
            </div>
    </div>
    @endif

    {{-- **Publishing is its own errand, so it is its own window**, opened from
         the header's arrow. --}}
    <div data-bw-modal="publish" wire:ignore.self class="fixed inset-0 z-50 hidden items-center justify-center overflow-y-auto p-4">
        <div class="absolute inset-0 bg-black/50" data-bw-modal-close></div>

        <div class="relative z-10 my-auto w-full max-w-2xl">
            <livewire:bladewright::page-publish :page="$page" :key="'publish-'.$page->id" />
        </div>
    </div>
</div>
