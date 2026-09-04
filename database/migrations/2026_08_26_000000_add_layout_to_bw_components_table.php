<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The layout a page uses, and a component's own field values.
 *
 * layout_key … which layout wraps it. **Null means a complete document**
 *               (as it always was). It holds a key rather than an id so it
 *               survives being taken off the site and published again.
 * data       … the layout's own field values (site name, logo, footer line).
 *               A page's blocks keep their values in the markers, but a
 *               layout does the wrapping and needs somewhere of its own.
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
            $table->string('layout_key')->nullable()->after('locale');
            $table->json('data')->nullable()->after('layout_key');
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->table('bw_components', function (Blueprint $table) {
            $table->dropColumn(['layout_key', 'data']);
        });
    }
};
