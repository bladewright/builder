<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * The old world comes down (2026-09-03, on the owner's word: discard it all).
 *
 * `bw_components`, `bw_routes`, `bw_revisions` and `bw_search_index` carried
 * the site before the four-layer model: pages as whole Blade documents,
 * blocks as marker-delimited bakes, layouts as checkouts in storage, a
 * history, a search. The four-layer tables (`bw_pages`, `bw_structures`,
 * `bw_blocks`, `bw_layouts`) carry it now, served straight from the
 * database.
 *
 * **What goes with them is written in HANDOFF**: the history and rollback
 * (until revisions are wired to the new world), the site search, and the
 * error-page editing the owner set aside for a better design.
 */
return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('bladewright.database.connection');
    }

    public function up(): void
    {
        foreach (['bw_search_index', 'bw_revisions', 'bw_routes', 'bw_components'] as $table) {
            Schema::connection($this->getConnection())->dropIfExists($table);
        }
    }

    /** The old world does not come back. The tables would return empty and serve nothing. */
    public function down(): void
    {
        // Nothing: the code that read these tables is gone.
    }
};
