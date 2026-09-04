<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Drop the containers table.
 *
 * The unit for "add a whole feature later" is being designed again, so this
 * goes for now. **Everything it creates is data** (pages, entry types,
 * routes), and all this table ever held was the fact that it had been
 * installed.
 */
return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('bladewright.database.connection');
    }

    public function up(): void
    {
        Schema::connection($this->getConnection())->dropIfExists('bw_containers');
    }

    public function down(): void
    {
        // The replacement will bring its own shape. Nothing is reversed here.
    }
};
