<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The framework stops being a layout's property (2026-09-03).
 *
 * A block is used bottom-up — placed anywhere — but what its classes mean
 * comes top-down, from whatever stylesheet wraps the page. Per-layout
 * presets left that question with no one answer, so **the framework is the
 * site's declaration now** (`bw_settings`), and a layout is born from the
 * site's framework and a type. The frames themselves stay exactly as
 * written: they are the site's own content.
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
            $table->dropColumn('preset');
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->table('bw_layouts', function (Blueprint $table) {
            $table->string('preset', 20)->default('bootstrap');
        });
    }
};
