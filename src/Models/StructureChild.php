<?php

namespace Bladewright\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One place inside a component, pointing at what stands there.
 *
 * **The uuid, never the name.** Names are for people, and people change
 * them; the reference survives every rename. `position` counts 1, 2, 3 …
 * from the top (from the left, in a grid).
 */
class StructureChild extends Model
{
    public const KIND_BLOCK = 'block';

    public const KIND_COMPONENT = 'component';

    protected $table = 'bw_structure_children';

    public $timestamps = false;

    protected $fillable = ['structure_id', 'child_kind', 'child_uuid', 'position'];

    public function getConnectionName(): ?string
    {
        return config('bladewright.database.connection') ?? parent::getConnectionName();
    }
}
