<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bladewright's own people (decided 2026-09-02).
 *
 * Until now the admin signed in against **the host application's** users
 * table, and `bladewright:user` was the one place we wrote to it. That broke
 * two ways at once: a developer let into the admin became a row in the
 * customer's own membership data, sitting between their real members, and
 * everything the customer's tables hang off a user made deleting one too
 * dangerous to offer at all.
 *
 * So the admin's people live here, in a `bw_` table like everything else of
 * ours. **An email address and a password — no name**: the owner's rule.
 * The host's users table goes back to being entirely the host's.
 */
return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('bladewright.database.connection');
    }

    public function up(): void
    {
        Schema::connection($this->getConnection())->create('bw_users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists('bw_users');
    }
};
