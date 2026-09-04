<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Uploaded files.
 *
 * **This alone cannot be restored from the database.** Pages and blocks can
 * be rebuilt from their revisions; the bytes of an image exist nowhere else.
 * Lose storage and they are gone. Do not apply the "storage is disposable"
 * assumption here.
 *
 * Files are **never overwritten**: replacing one makes a new file, because
 * going back to an older revision has to find the image that revision
 * refers to.
 */
return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('bladewright.database.connection');
    }

    public function up(): void
    {
        Schema::connection($this->getConnection())->create('bw_media', function (Blueprint $table) {
            $table->id();

            $table->string('disk', 40);
            $table->string('path');

            $table->string('name');
            $table->string('mime', 120);
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();

            // Identical contents become one file.
            $table->string('content_hash', 64);

            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->string('uploaded_label')->nullable();

            // Only hidden from the list. **The file stays** — older revisions
            // refer to it.
            $table->timestamp('hidden_at')->nullable();

            $table->timestamps();

            $table->unique(['disk', 'content_hash']);
            $table->index('hidden_at');
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists('bw_media');
    }
};
