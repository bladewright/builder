<?php

namespace Bladewright\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One place on a page, pointing at the component that stands there.
 * **The uuid, never the name** — the reference survives every rename.
 */
class PageChild extends Model
{
    protected $table = 'bw_page_children';

    public $timestamps = false;

    protected $fillable = ['page_id', 'child_uuid', 'position'];

    public function getConnectionName(): ?string
    {
        return config('bladewright.database.connection') ?? parent::getConnectionName();
    }
}
