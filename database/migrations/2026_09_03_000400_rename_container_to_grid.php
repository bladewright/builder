<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The word container gets its real meaning back (2026-09-03).
 *
 * The flag on `bw_structures` said `container` but meant **a grid**. Now
 * that a component can hold its contents to a width — which is what
 * container means everywhere else — the flag is renamed to what it is,
 * before the two meanings tangle.
 */
return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('bladewright.database.connection');
    }

    public function up(): void
    {
        Schema::connection($this->getConnection())->table('bw_structures', function (Blueprint $table) {
            $table->renameColumn('container', 'grid');
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->table('bw_structures', function (Blueprint $table) {
            $table->renameColumn('grid', 'container');
        });
    }
};
