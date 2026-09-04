<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pages, in the four-layer model (the owner's table, 2026-09-02) — the top
 * of the stack.
 *
 * A page is answerable for **its URL, its publishing, its SEO and its
 * settings**; what it shows is a row of components, referenced by uuid the
 * way components reference blocks. Error pages are deliberately absent —
 * the owner set them aside for a better design.
 *
 * **These pages are not served yet.** The old world still answers the
 * site's requests; wiring the router to this table is the integration step.
 */
return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('bladewright.database.connection');
    }

    public function up(): void
    {
        Schema::connection($this->getConnection())->create('bw_pages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name')->unique();

            // '' is the front page. Unique: two pages cannot answer one URL.
            $table->string('url')->unique();

            // The frame it wears. Nullable — a page may stand bare, warned.
            $table->uuid('layout_uuid')->nullable();

            $table->boolean('is_published')->default(false);
            $table->timestamp('published_from')->nullable();
            $table->timestamp('published_until')->nullable();

            // SEO and the page's settings, edited on the screens.
            $table->json('data')->nullable();

            $table->timestamps();
        });

        Schema::connection($this->getConnection())->create('bw_page_children', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained('bw_pages')->cascadeOnDelete();

            // A page holds components; blocks stand inside those.
            $table->uuid('child_uuid');
            $table->unsignedInteger('position');

            $table->index(['page_id', 'position']);
            $table->index('child_uuid');
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists('bw_page_children');
        Schema::connection($this->getConnection())->dropIfExists('bw_pages');
    }
};
