<?php

namespace Bladewright\Media;

use Illuminate\Support\Collection;
use Bladewright\Models\Block;

/**
 * Which blocks show this file.
 *
 * **Media is the one thing the database cannot restore**, so before deleting
 * a file, where it is used has to be visible. In the four-layer model an
 * image, a video or an audio block holds the media path in its data; those
 * blocks are what a deleted file would go missing from.
 */
class MediaUsage
{
    /**
     * @return Collection<int, Block>
     */
    public function using(string $path): Collection
    {
        $path = ltrim($path, '/');

        if ($path === '') {
            return collect();
        }

        return Block::query()
            ->get()
            ->filter(fn (Block $block) => str_contains(json_encode($block->data ?? []), $path))
            ->values();
    }
}
