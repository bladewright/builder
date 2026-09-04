<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An index of the text to search.
 *
 * The contents come in different shapes, so they cannot be searched as they
 * are: an entry is JSON in `data`, a page is a Blade template. **The text of
 * the rendered result, with the tags stripped**, is
 * gathered here.
 *
 * **It is derived, never the source of truth.** Lose it and
 * `bladewright:reindex` builds it again (the same standing as a checkout in
 * storage). Moving to full-text search or an external service later means
 * replacing this and nothing else.
 */
return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('bladewright.database.connection');
    }

    public function up(): void
    {
        Schema::connection($this->getConnection())->create('bw_search_index', function (Blueprint $table) {
            $table->id();

            // 'page' or 'entry'.
            $table->string('subject_type', 20);
            $table->unsignedBigInteger('subject_id');

            // For an entry, the type key. Used for filtering.
            $table->string('type_key')->nullable();

            $table->string('title');
            $table->longText('body')->nullable();
            $table->string('url');

            // **The publishing window is kept on the index too**, so that
            // expired things stay out of the results without going back to
            // the record on every query.
            $table->timestamp('published_at')->nullable();
            $table->timestamp('unpublished_at')->nullable();

            $table->timestamps();

            $table->unique(['subject_type', 'subject_id']);
            $table->index(['subject_type', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists('bw_search_index');
    }
};
