<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Laravel configuration that can be changed from the admin.
 *
 * Keys are plain config paths (app.timezone / mail.from.address …). The
 * values override config at boot, so **from the outside it is
 * indistinguishable from editing config/app.php.**
 *
 * v3 rewrote .env by string replacement from the admin. That was the same
 * mistake as rewriting routes/web.php: it did nothing on a config:cache'd
 * production (so optimize:clear was run every time) and could not write at
 * all on a read-only container. Overriding has neither problem.
 */
return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('bladewright.database.connection');
    }

    public function up(): void
    {
        Schema::connection($this->getConnection())->create('bw_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists('bw_settings');
    }
};
