<?php

namespace Bladewright\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One setting. The key is a plain config path.
 */
class Setting extends Model
{
    protected $table = 'bw_settings';

    protected $fillable = ['key', 'value'];

    protected function casts(): array
    {
        return ['value' => 'json'];
    }

    public function getConnectionName(): ?string
    {
        return config('bladewright.database.connection') ?? parent::getConnectionName();
    }
}
