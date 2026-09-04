<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Drop the entries, the types and the terms.
 *
 * **"Add a news feature" is a plugin, not something the core carries.** The
 * engine was built before that was decided, and keeping it would mean
 * designing plugins around a shape nobody had chosen. It comes back with them.
 *
 * The revisions of the entries go too: they are revisions of a thing that no
 * longer exists, and nothing can read them.
 */
return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('bladewright.database.connection');
    }

    public function up(): void
    {
        $connection = $this->getConnection();
        $schema = Schema::connection($connection);

        // The revisions and the search rows point at entries by name, so they
        // go before the tables they point at.
        try {
            \Illuminate\Support\Facades\DB::connection($connection)
                ->table('bw_revisions')
                ->where('subject_type', 'like', '%\\\\Entry')
                ->delete();

            \Illuminate\Support\Facades\DB::connection($connection)
                ->table('bw_search_index')
                ->where('subject_type', 'entry')
                ->delete();
        } catch (\Throwable) {
            // Not there yet, and nothing to clear.
        }

        $schema->dropIfExists('bw_entry_term');
        $schema->dropIfExists('bw_terms');
        $schema->dropIfExists('bw_entries');

        // The entry types were components. So were their routes.
        try {
            $ids = \Illuminate\Support\Facades\DB::connection($connection)
                ->table('bw_components')
                ->where('kind', 'entry-type')
                ->pluck('id');

            if ($ids->isNotEmpty()) {
                \Illuminate\Support\Facades\DB::connection($connection)->table('bw_routes')->whereIn('component_id', $ids)->delete();
                \Illuminate\Support\Facades\DB::connection($connection)->table('bw_revisions')
                    ->where('subject_type', 'like', '%\\\\Component')
                    ->whereIn('subject_id', $ids)
                    ->delete();
                \Illuminate\Support\Facades\DB::connection($connection)->table('bw_components')->whereIn('id', $ids)->delete();
            }
        } catch (\Throwable) {
            // Nothing to clear.
        }
    }

    public function down(): void
    {
        // Nothing to reverse. What comes back will be a plugin's own tables,
        // designed with it.
    }
};
