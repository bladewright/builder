<?php

namespace Bladewright\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A page in the four-layer model: **a URL, its publishing, and a row of
 * components** — referenced by uuid, the way components reference blocks.
 *
 * Not served yet: the old world still answers the site's requests, and
 * wiring the router to this table is the integration step.
 */
class Page extends Model
{
    use HasUuids;

    protected $table = 'bw_pages';

    protected $fillable = ['name', 'url', 'layout_uuid', 'locale', 'is_published', 'published_from', 'published_until', 'data'];

    public function getConnectionName(): ?string
    {
        return config('bladewright.database.connection') ?? parent::getConnectionName();
    }

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_from' => 'datetime',
            'published_until' => 'datetime',
            'data' => 'array',
        ];
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** The components it shows, in order. */
    public function children(): HasMany
    {
        return $this->hasMany(PageChild::class, 'page_id')->orderBy('position');
    }
}
