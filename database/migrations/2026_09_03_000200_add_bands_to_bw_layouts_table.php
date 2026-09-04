<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The header and the footer a frame wears (2026-09-03).
 *
 * A layout is three bands: **a header, the page, a footer.** The middle one
 * is the page's own and nothing else may stand there; the other two are
 * components — the same components as anywhere else, arranged on their own
 * screen, so a header is a logo block and a nav block put together.
 *
 * **The uuid, never the name**, as everywhere: renaming the component the
 * site wears breaks nothing.
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
            $table->uuid('header_uuid')->nullable()->after('type');
            $table->uuid('footer_uuid')->nullable()->after('header_uuid');
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->table('bw_layouts', function (Blueprint $table) {
            $table->dropColumn(['header_uuid', 'footer_uuid']);
        });
    }
};
