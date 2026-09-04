<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The language a page is born in — its <html lang>.
 *
 * The site-wide answer lives in `bladewright.locale` (falling back to
 * app.locale, the answer the Laravel developer already gave); each page
 * carries the language it was made with, and keeps it.
 */
return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('bladewright.database.connection');
    }

    public function up(): void
    {
        Schema::connection($this->getConnection())->table('bw_pages', function (Blueprint $table) {
            $table->string('locale', 12)->default('en')->after('layout_uuid');
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->table('bw_pages', function (Blueprint $table) {
            $table->dropColumn('locale');
        });
    }
};
