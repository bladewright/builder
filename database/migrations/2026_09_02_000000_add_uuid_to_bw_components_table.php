<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * A name for the admin's URLs that is neither the row number nor the key.
 *
 * `/bladewright/pages/1` put the database's own counting on screen. The key
 * could not take its place either: it is unique **per kind**, so a page keyed
 * `404` and the 404 error page — which is edited through the same screen —
 * would both answer to `/bladewright/pages/404`.
 *
 * **The row id stays the primary key.** Everything that points at a component
 * (`bw_routes.component_id`, `bw_revisions.subject_id`, the search index)
 * keeps pointing the same way; this is one more column, for addressing a
 * screen and nothing else.
 */
return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('bladewright.database.connection');
    }

    public function up(): void
    {
        Schema::connection($this->getConnection())->table('bw_components', function (Blueprint $table) {
            // **Nullable, then filled.** A unique column cannot be added to a
            // table that already has rows without something to put in it.
            $table->uuid('uuid')->nullable()->after('id');
        });

        foreach (DB::connection($this->getConnection())->table('bw_components')->pluck('id') as $id) {
            DB::connection($this->getConnection())
                ->table('bw_components')
                ->where('id', $id)
                ->update(['uuid' => (string) Str::uuid()]);
        }

        Schema::connection($this->getConnection())->table('bw_components', function (Blueprint $table) {
            $table->unique('uuid');
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->table('bw_components', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
            $table->dropColumn('uuid');
        });
    }
};
