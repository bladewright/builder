<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which URL maps to which component.
 *
 * **These are not Laravel routes.** A single Route::fallback() catches the
 * request and one controller resolves it against this table. Nothing is ever
 * written to a route file, so route:cache keeps working (v3 appended to
 * routes/web.php and took every request down with a 500).
 *
 * The point is that "published" means two separate things:
 *   a component's published_revision_id … which revision is live
 *   a route's is_published              … whether that URL can be reached
 * A block has only the first, and no route, so it appears only through a
 * page.
 */
return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('bladewright.database.connection');
    }

    public function up(): void
    {
        Schema::connection($this->getConnection())->create('bw_routes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('component_id');

            $table->string('path')->unique();
            $table->string('locale', 12)->default('ja');

            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('unpublished_at')->nullable();

            $table->timestamps();

            $table->index(['is_published', 'published_at']);
            $table->index('component_id');
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists('bw_routes');
    }
};
