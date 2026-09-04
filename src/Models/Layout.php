<?php

namespace Bladewright\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * A layout in the four-layer model: **where the parts of a page sit.**
 *
 * Three bands: a header, the page, a footer. **The middle one is the page's
 * own** and nothing else stands there; the other two are components, chosen
 * on the layout's screen and arranged on their own.
 *
 * Born from a recipe (the site's framework × a type), and the site's own from then on: the
 * frame in `content` is a whole HTML document with `{{ $slot }}` where the
 * page goes, edited on the screens. **People say the name; everything else
 * holds the uuid.**
 */
class Layout extends Model
{
    use HasUuids;

    /** What the CSS is written in. */
    public const PRESETS = ['bootstrap', 'pico', 'plain'];

    /** Where the navigation stands: across the top, or at the side. */
    public const TYPES = ['header', 'sidebar'];

    /** The bands a component can be worn in. **The page's own is not one.** */
    public const BANDS = ['header', 'footer'];

    /**
     * The component types that are the layout's own.
     *
     * **Layout-only, both ways round**: a header or a footer stands in its
     * band and nowhere else, and each band takes only its own kind — so a
     * band always starts from its tag.
     */
    public const BANDS_TYPES = ['header', 'footer'];

    protected $table = 'bw_layouts';

    protected $fillable = ['name', 'type', 'content', 'header_uuid', 'footer_uuid', 'font_family'];

    public function getConnectionName(): ?string
    {
        return config('bladewright.database.connection') ?? parent::getConnectionName();
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

}
