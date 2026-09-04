<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Everything written from the browser is a component.
 *
 * Pages, blocks and layouts are managed by exactly the same mechanism. What
 * differs is how they are reached, and that lives in bw_routes: a component
 * with a route assigned to it appears as a page.
 */
return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('bladewright.database.connection');
    }

    public function up(): void
    {
        Schema::connection($this->getConnection())->create('bw_components', function (Blueprint $table) {
            $table->id();

            // page / block / layout. Future kinds join them here.
            $table->string('kind', 20)->default('page');

            $table->string('key');
            $table->string('name');
            $table->string('locale', 12)->default('ja');

            // Which revision is checked out. The templates under storage are
            // written from here, and bladewright:sync rebuilds them if they
            // are lost.
            $table->unsignedBigInteger('draft_revision_id')->nullable();
            $table->unsignedBigInteger('published_revision_id')->nullable();

            // When a packaged block was taken over, its fingerprint at that
            // moment. It shows when composer update has moved upstream.
            $table->string('origin_hash', 64)->nullable();

            $table->timestamps();

            $table->unique(['kind', 'key']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists('bw_components');
    }
};
