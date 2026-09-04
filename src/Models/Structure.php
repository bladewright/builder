<?php

namespace Bladewright\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A component in the four-layer model: **a structure that means something —
 * a collection of blocks.**
 *
 * (Named `Structure` for now: the old world still holds the `Component`
 * name in 105 files. The screens and commands say `component`; this class
 * takes that name when the old world is dismantled.)
 *
 * It holds **references, not copies**: children point at blocks by uuid, so
 * editing a block reaches every component that shows it, and renaming
 * anything breaks nothing. Spacing (`padding`, `gap`) lives in `data` here
 * and nowhere else in the model.
 */
class Structure extends Model
{
    use HasUuids;

    /** The types a person can create. */
    public const TYPES = ['section', 'article', 'header', 'footer', 'nav', 'table', 'figure', 'form', 'field'];

    /** How the contents stand: on top of each other, in a grid, or in a row. */
    public const LAYOUTS = ['stack', 'grid', 'row'];

    /** Where a row's spare room goes. */
    public const JUSTIFIES = ['start', 'center', 'end', 'space-between'];

    /** How a row's children meet each other's height. */
    public const ALIGNS = ['stretch', 'start', 'center', 'end'];

    protected $table = 'bw_structures';

    protected $fillable = ['name', 'type', 'data'];

    public function getConnectionName(): ?string
    {
        return config('bladewright.database.connection') ?? parent::getConnectionName();
    }

    protected function casts(): array
    {
        return ['data' => 'array'];
    }

    /**
     * The layout is arrangement, so it lives in `data` with the rest of the
     * arrangement — which also lets an unsaved ghost carry a different one.
     */
    public function getLayoutAttribute(): string
    {
        $layout = ($this->data ?? [])['layout'] ?? 'stack';

        return in_array($layout, self::LAYOUTS, true) ? $layout : 'stack';
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** What it shows, in order. */
    public function children(): HasMany
    {
        return $this->hasMany(StructureChild::class, 'structure_id')->orderBy('position');
    }
}
