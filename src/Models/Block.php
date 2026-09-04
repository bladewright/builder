<?php

namespace Bladewright\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * A block: **one named thing with its own content** (the four-layer model).
 *
 * The smallest unit a page is built from — one HTML block-level element,
 * with the Markdown block as the single sanctioned exception. A block is
 * answerable for Markdown, and for the editable things Markdown cannot
 * express: an image, a video, a button, a form's fields, an embed.
 *
 * **People say the name; the machinery holds the uuid.** Whatever uses a
 * block refers to it by uuid — resolved from the name once, at the moment of
 * insertion — so renaming one breaks nothing, and editing one reaches every
 * place that uses it. A copy is how a block is made to diverge.
 */
class Block extends Model
{
    use HasUuids;

    /**
     * The types a person can create.
     *
     * **One element each**, but for two: Markdown, which is words and comes
     * out as whatever they say, and a field, which carries the label that
     * names it — a label on its own labels nothing, so it is no type of its
     * own.
     */
    public const TYPES = [
        'markdown', 'image', 'video', 'audio', 'button',
        'input', 'select', 'radio', 'checkbox', 'textarea', 'embed',
        // One empty element, for writing into: **the blank page of the
        // types.** Everything it says is said on the Code pill.
        'div',
    ];

    protected $table = 'bw_blocks';

    protected $fillable = ['name', 'type', 'data'];

    public function getConnectionName(): ?string
    {
        return config('bladewright.database.connection') ?? parent::getConnectionName();
    }

    protected function casts(): array
    {
        return ['data' => 'array'];
    }

    /** The uuid is filled on creation; the row id stays the primary key. */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
