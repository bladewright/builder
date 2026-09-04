<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Patterns are gone. **They were built without being asked for.**
 *
 * A section kept with its values, to paste somewhere else. It came from what
 * other systems have, not from anything this site needed, and it put a third
 * idea — neither a page nor a part, but material — on screens that already
 * asked too much of the person reading them.
 *
 * **Nothing that was placed from one is affected.** What was pasted was always
 * a copy, sitting in the page's own Blade with its own history; that is what a
 * pattern was, and it is why the shelf can go without anything going with it.
 */
return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('bladewright.database.connection');
    }

    public function up(): void
    {
        Schema::connection($this->getConnection())->dropIfExists('bw_patterns');
    }

    /** The table comes back empty. What was on the shelf is not kept anywhere. */
    public function down(): void
    {
        Schema::connection($this->getConnection())->create('bw_patterns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->longText('content');
            $table->string('created_by')->nullable();
            $table->timestamps();
        });
    }
};
