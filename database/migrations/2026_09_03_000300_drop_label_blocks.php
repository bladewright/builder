<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A label is not a block (2026-09-03).
 *
 * It was a type of its own for a while — made beside a field and tied to it
 * by an id. **The label belongs to the field**, and is part of it now, so
 * the type is gone and the blocks made under it go with it: a block of a
 * type nothing renders would sit in the list forever, showing nothing.
 *
 * Whatever held one loses the reference too, the way a deleted block always
 * takes its pointers with it.
 */
return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('bladewright.database.connection');
    }

    public function up(): void
    {
        $connection = DB::connection($this->getConnection());

        $uuids = $connection->table('bw_blocks')->where('type', 'label')->pluck('uuid');

        if ($uuids->isEmpty()) {
            return;
        }

        $connection->table('bw_structure_children')
            ->where('child_kind', 'block')
            ->whereIn('child_uuid', $uuids)
            ->delete();

        $connection->table('bw_blocks')->whereIn('uuid', $uuids)->delete();
    }

    public function down(): void
    {
        // **Nothing to put back.** What they said lives on the fields now.
    }
};
