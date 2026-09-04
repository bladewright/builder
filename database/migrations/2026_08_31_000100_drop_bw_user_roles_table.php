<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Drop the roles table.
 *
 * **Roles were taken out to be designed again.** One role per person, the
 * abilities fixed in the configuration, and a user nameable only by id: not
 * the shape anybody should be learning, so it came out rather than being kept
 * as the thing people build habits on.
 *
 * Until they come back, **everybody signed in can do everything** (see
 * `BladewrightServiceProvider::registerAbilities`). The abilities themselves,
 * and the screens that ask for them, are untouched — what a screen needs is a
 * decision worth keeping.
 */
return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('bladewright.database.connection');
    }

    public function up(): void
    {
        Schema::connection($this->getConnection())->dropIfExists('bw_user_roles');
    }

    public function down(): void
    {
        // Nothing to reverse. The roles that were in it are not the roles that
        // will come back.
    }
};
