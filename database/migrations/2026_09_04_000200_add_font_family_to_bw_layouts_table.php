<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The typeface is the frame's word. **Top-down, like the framework** —
 * setting it block by block would be misery; every page wearing the frame
 * reads in it, and empty leaves the framework's own standing.
 */
return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('bladewright.database.connection');
    }

    public function up(): void
    {
        Schema::connection($this->getConnection())->table('bw_layouts', function (Blueprint $table) {
            $table->string('font_family')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->table('bw_layouts', function (Blueprint $table) {
            $table->dropColumn('font_family');
        });
    }
};
