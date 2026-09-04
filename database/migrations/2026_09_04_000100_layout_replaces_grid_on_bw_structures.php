<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The grid flag grows up into a layout: stack, grid, or row. **It moves into
 * `data` with the rest of the arrangement** — gap and width already live
 * there, and a layout is arrangement through and through.
 */
return new class extends Migration
{
    public function up(): void
    {
        $connection = config('bladewright.database.connection');

        foreach (DB::connection($connection)->table('bw_structures')->where('grid', true)->get() as $row) {
            $data = json_decode($row->data ?? '[]', true) ?: [];
            $data['layout'] = 'grid';

            DB::connection($connection)->table('bw_structures')
                ->where('id', $row->id)
                ->update(['data' => json_encode($data)]);
        }

        Schema::connection($connection)->table('bw_structures', function (Blueprint $table) {
            $table->dropColumn('grid');
        });
    }

    public function down(): void
    {
        $connection = config('bladewright.database.connection');

        Schema::connection($connection)->table('bw_structures', function (Blueprint $table) {
            $table->boolean('grid')->default(false);
        });

        foreach (DB::connection($connection)->table('bw_structures')->get() as $row) {
            $data = json_decode($row->data ?? '[]', true) ?: [];

            if (($data['layout'] ?? '') === 'grid') {
                DB::connection($connection)->table('bw_structures')
                    ->where('id', $row->id)
                    ->update(['grid' => true]);
            }
        }
    }
};
