<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Drop the media table.
 *
 * **Storage was the source of truth and the database only a copy.** A copy
 * always drifts, and then something has to detect the drift (we had started
 * building exactly that).
 *
 * From here on **the path carries the original name**
 * (`bw/<hash>/…/<original name>.jpg`). Who uploaded a file is dropped:
 * nobody asked for it, and it is no reason to keep a table.
 */
return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('bladewright.database.connection');
    }

    public function up(): void
    {
        Schema::connection($this->getConnection())->dropIfExists('bw_media');
    }

    public function down(): void
    {
        // Nothing to reverse. Storage holds the truth, and there is nothing
        // in the table worth restoring.
    }
};
