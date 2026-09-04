<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Make deleting a page something that does not delete.
 *
 * With a history and a rollback in place, page deletion being the one
 * irreversible act
 * would not add up. **It only leaves the list and frees the URL**; every
 * revision stays.
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
            $table->timestamp('archived_at')->nullable()->after('origin_hash');
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->table('bw_components', function (Blueprint $table) {
            $table->dropColumn('archived_at');
        });
    }
};
