<?php

namespace Bladewright\Blocks;

use InvalidArgumentException;
use Bladewright\Blocks\BlockManager;
use Bladewright\Models\Block;
use Bladewright\Models\Structure;
use Bladewright\Models\StructureChild;

/**
 * Creating, copying, renaming, deleting components — and putting blocks into
 * them. **The rule lives here, once**; the commands and the screens both
 * come through.
 *
 * (It manages `Structure` rows: the model's transitional name while the old
 * world holds `Component`. Everything user-facing says component.)
 */
class StructureManager
{
    public function find(string $name): ?Structure
    {
        return Structure::query()->where('name', trim($name))->first();
    }

    public function create(string $name, string $type, string $layout = 'stack'): Structure
    {
        $name = $this->assertNameIsFree($name);

        if (! in_array($type, Structure::TYPES, true)) {
            throw new InvalidArgumentException(__('[:type] is not a component type. One of: :types.', [
                'type' => $type,
                'types' => implode(', ', Structure::TYPES),
            ]));
        }

        if (! in_array($layout, Structure::LAYOUTS, true)) {
            throw new InvalidArgumentException(__('[:layout] is not a layout. One of: :layouts.', [
                'layout' => $layout,
                'layouts' => implode(', ', Structure::LAYOUTS),
            ]));
        }

        return Structure::create(['name' => $name, 'type' => $type, 'data' => ['layout' => $layout]]);
    }

    /**
     * One more arrangement under its own name.
     *
     * **The copy shows the same blocks.** What is copied is the arrangement —
     * the references — so editing a block still reaches both. To make the
     * words diverge, copy the block.
     */
    public function copy(Structure $structure, string $to): Structure
    {
        $to = $this->assertNameIsFree($to);

        $copy = Structure::create([
            'name' => $to,
            'type' => $structure->type,
            'data' => $structure->data,
        ]);

        foreach ($structure->children as $child) {
            StructureChild::create([
                'structure_id' => $copy->id,
                'child_kind' => $child->child_kind,
                'child_uuid' => $child->child_uuid,
                'position' => $child->position,
            ]);
        }

        return $copy;
    }

    /** Change what it is called. **Nothing that uses it notices.** */
    public function rename(Structure $structure, string $to): Structure
    {
        $to = $this->assertNameIsFree($to);

        $structure->forceFill(['name' => $to])->save();

        return $structure;
    }

    /**
     * Delete the component. **The blocks in it stay** — what goes is the
     * arrangement, and any place that pointed at this component loses the
     * pointer rather than keeping a dangling one.
     */
    public function delete(Structure $structure): void
    {
        // Its own list, and any place that pointed at it. **Not left to the
        // database's cascade** — a connection with foreign keys off would
        // quietly keep the ghosts.
        $structure->children()->delete();

        StructureChild::query()
            ->where('child_kind', StructureChild::KIND_COMPONENT)
            ->where('child_uuid', $structure->uuid)
            ->delete();

        $structure->delete();
    }

    /**
     * Put a block into a component.
     *
     * **The name is resolved to the uuid here, once** — from now on the
     * component holds the uuid, and the block can be renamed freely.
     *
     * @param  int|null  $position  1, 2, 3 … from the top; null is the end
     */
    public function insertBlock(Structure $structure, Block $block, ?int $position = null): StructureChild
    {
        return $this->place($structure, StructureChild::KIND_BLOCK, $block->uuid, $position);
    }

    /** One place in the list, with everything below it moving down. */
    private function place(Structure $structure, string $kind, string $uuid, ?int $position): StructureChild
    {
        $count = $structure->children()->count();

        if ($position === null || $position > $count) {
            $position = $count + 1;
        }

        if ($position < 1) {
            throw new InvalidArgumentException(__('The order counts from 1.'));
        }

        $structure->children()
            ->where('position', '>=', $position)
            ->increment('position');

        return StructureChild::create([
            'structure_id' => $structure->id,
            'child_kind' => $kind,
            'child_uuid' => $uuid,
            'position' => $position,
        ]);
    }

    /**
     * Put a component inside another one. **A component cannot hold itself**,
     * nor anything that already holds it — a cycle would render forever.
     */
    public function insertComponent(Structure $structure, Structure $child, ?int $position = null): StructureChild
    {
        // **A band's component is the layout's own** — a header inside a
        // section would put two headers on the page, so it is placed on the
        // layout screen and nowhere else.
        if (in_array($child->type, \Bladewright\Models\Layout::BANDS_TYPES, true)) {
            throw new InvalidArgumentException(__('[:name] is a :type — it is placed on a layout, not in here.', [
                'name' => $child->name,
                'type' => $child->type,
            ]));
        }

        if ($this->wouldLoop($structure, $child)) {
            throw new InvalidArgumentException(__('[:child] already holds [:parent], so putting it inside would go round forever.', [
                'child' => $child->name,
                'parent' => $structure->name,
            ]));
        }

        return $this->place($structure, StructureChild::KIND_COMPONENT, $child->uuid, $position);
    }

    /** Is `$child` the same as `$structure`, or somewhere above it already? */
    private function wouldLoop(Structure $structure, Structure $child): bool
    {
        if ($structure->uuid === $child->uuid) {
            return true;
        }

        foreach ($child->children as $row) {
            if ($row->child_kind !== StructureChild::KIND_COMPONENT) {
                continue;
            }

            $inner = Structure::query()->where('uuid', $row->child_uuid)->first();

            if ($inner !== null && $this->wouldLoop($structure, $inner)) {
                return true;
            }
        }

        return false;
    }

    /** Take one child out. **What it pointed at stays** — only its place goes. */
    public function removeChild(Structure $structure, int $childId): void
    {
        $child = $structure->children()->whereKey($childId)->first();

        if ($child === null) {
            return;
        }

        $position = $child->position;
        $child->delete();

        $structure->children()->where('position', '>', $position)->decrement('position');
    }

    /** Move one place up (-1) or down (+1). At the edge, nothing moves. */
    public function moveChild(Structure $structure, int $childId, int $delta): void
    {
        $child = $structure->children()->whereKey($childId)->first();

        if ($child === null || ! in_array($delta, [-1, 1], true)) {
            return;
        }

        $neighbour = $structure->children()->where('position', $child->position + $delta)->first();

        if ($neighbour === null) {
            return;
        }

        $position = $child->position;
        $child->forceFill(['position' => $neighbour->position])->save();
        $neighbour->forceFill(['position' => $position])->save();
    }

    /**
     * Put the child now standing at :from into place :to.
     *
     * **Dragging says where a thing lands, not which way it moved**, so the
     * whole row is renumbered from what is left after taking it out.
     */
    public function reorderChild(Structure $structure, int $from, int $to): void
    {
        $children = $structure->children()->get()->values();

        if (! isset($children[$from]) || ! isset($children[$to]) || $from === $to) {
            return;
        }

        $moving = $children[$from];
        $rest = $children->reject(fn ($child) => $child->id === $moving->id)->values();
        $ordered = $rest->splice(0, $to)->push($moving)->concat($rest);

        foreach ($ordered as $index => $child) {
            $child->forceFill(['position' => $index + 1])->save();
        }
    }

    /**
     * The arrangement — how the contents stand. **Stacked, in a grid, or in
     * a row**, with the words that layout then needs: a grid's columns, a
     * row's alignment, and the gap either one keeps. Everything else about
     * how a component looks is the Style card's.
     *
     * @param  array<string, mixed>  $values
     */
    public function saveArrangement(Structure $structure, array $values, string $layout): Structure
    {
        if (! in_array($layout, Structure::LAYOUTS, true)) {
            throw new InvalidArgumentException(__('[:layout] is not a layout. One of: :layouts.', [
                'layout' => $layout,
                'layouts' => implode(', ', Structure::LAYOUTS),
            ]));
        }

        $kept = ['layout' => $layout];

        // **Only what reads as a CSS length reaches a style attribute.**
        // `columns` is held to the same letters: `auto`, a count, or a
        // template like `1fr 2fr`.
        foreach (['gap', 'width', 'columns'] as $key) {
            $value = trim((string) ($values[$key] ?? ''));

            if ($value !== '' && preg_match('/^[0-9a-z.% ]+$/i', $value) !== 1) {
                throw new InvalidArgumentException(__('[:value] does not read as a size (1.5rem, 24px, 60%).', ['value' => $value]));
            }

            $kept[$key] = $value;
        }

        // A row's words come from short lists, so anything else is refused
        // rather than written out.
        foreach (['justify' => Structure::JUSTIFIES, 'align' => Structure::ALIGNS] as $key => $words) {
            $value = trim((string) ($values[$key] ?? ''));

            if ($value !== '' && ! in_array($value, $words, true)) {
                throw new InvalidArgumentException(__('[:value] is not one of: :words.', ['value' => $value, 'words' => implode(', ', $words)]));
            }

            $kept[$key] = $value;
        }

        $kept['wrap'] = (bool) ($values['wrap'] ?? true);

        // Small screens stack, unless this component says its arrangement
        // holds at every width.
        $kept['collapse'] = (bool) ($values['collapse'] ?? true);

        // The markup and the look are not arrangement settings: they ride
        // through untouched.
        $structure->forceFill(['data' => array_merge($structure->data ?? [], $kept)])->save();

        return $structure;
    }

    /**
     * How the component looks — the same card the blocks wear, kept under
     * the same rule: **nothing but what reads as what it says.**
     *
     * @param  array<string, mixed>  $style
     */
    public function saveStyle(Structure $structure, array $style): Structure
    {
        $structure->forceFill([
            'data' => array_merge($structure->data ?? [], [
                'style' => app(BlockManager::class)->sanitizeStyle($style),
            ]),
        ])->save();

        return $structure;
    }

    /** The tag's class attribute — the words of whatever stylesheet is loaded. */
    public function saveClass(Structure $structure, string $class): Structure
    {
        $structure->forceFill([
            'data' => array_merge($structure->data ?? [], [
                'class' => app(BlockManager::class)->sanitizeClass($class),
            ]),
        ])->save();

        return $structure;
    }

    /**
     * Write the component's own markup. **When there is any, that is the
     * component** — Blade of the site's own, run when the page is asked for,
     * and the arrangement stops reaching the page until it is emptied again.
     */
    public function saveMarkup(Structure $structure, string $markup): Structure
    {
        $structure->forceFill([
            'data' => array_merge($structure->data ?? [], ['markup' => trim($markup)]),
        ])->save();

        return $structure;
    }

    /** How many components show this block. What deleting it would reach. */
    public function placesShowing(Block $block): int
    {
        return StructureChild::query()
            ->where('child_kind', StructureChild::KIND_BLOCK)
            ->where('child_uuid', $block->uuid)
            ->count();
    }

    /** A deleted block leaves no dangling pointers behind. */
    public function forgetBlock(Block $block): void
    {
        StructureChild::query()
            ->where('child_kind', StructureChild::KIND_BLOCK)
            ->where('child_uuid', $block->uuid)
            ->delete();
    }

    private function assertNameIsFree(string $name): string
    {
        $name = trim($name);

        if ($name === '') {
            throw new InvalidArgumentException(__('A name cannot be empty.'));
        }

        if (mb_strlen($name) > 100) {
            throw new InvalidArgumentException(__('A name can be at most 100 characters.'));
        }

        if (Structure::query()->where('name', $name)->exists()) {
            throw new InvalidArgumentException(__('[:name] is already a component.', ['name' => $name]));
        }

        return $name;
    }
}
