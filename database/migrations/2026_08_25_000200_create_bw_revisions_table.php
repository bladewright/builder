<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The edit history. The source of truth for content is here.
 *
 * The templates under storage are only checkouts of one revision. The
 * subject is polymorphic so that pages, blocks and layouts go through the
 * same mechanism — which is also what keeps us out of the rut v3 fell into,
 * copying one implementation three times for News/Post/Event.
 */
return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('bladewright.database.connection');
    }

    public function up(): void
    {
        Schema::connection($this->getConnection())->create('bw_revisions', function (Blueprint $table) {
            $table->id();
            $table->morphs('subject');

            // For a content revision, the one before it; for an event row,
            // the revision it is about.
            $table->unsignedBigInteger('parent_id')->nullable();

            $table->string('event', 20);

            // Null for events that carry no content, such as publishing.
            $table->longText('content')->nullable();
            $table->string('content_hash', 64)->nullable();

            // What validation found. A refusal without a reason is useless,
            // so the revision carries which line broke and how.
            $table->boolean('is_valid')->default(true);
            $table->json('error')->nullable();

            // No dependency on the host's User model: the history stays
            // readable after that user is gone.
            $table->unsignedBigInteger('author_id')->nullable();
            $table->string('author_label')->nullable();

            $table->string('note')->nullable();
            $table->boolean('pinned')->default(false);

            // A revision that was ever published is never pruned. This is
            // both the record and the test.
            $table->timestamp('published_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['subject_type', 'subject_id', 'id'], 'bw_revisions_subject_history_index');
            $table->index('content_hash');
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists('bw_revisions');
    }
};
