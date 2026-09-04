<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Layouts, in the four-layer model (the owner's table, 2026-09-02).
 *
 * A layout is answerable for **where the parts of a page sit**: header, nav,
 * main, aside, footer. One is born from a recipe — a preset (what the CSS is
 * written in: Bootstrap, or plain CSS) crossed with a type (header across
 * the top, or a sidebar) — and from then on the frame is the site's own to
 * rewrite, on the screens.
 *
 * Like the blocks and the components: **the name is unique because it is how
 * a person addresses one; the uuid is what everything else will hold.**
 */
return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('bladewright.database.connection');
    }

    public function up(): void
    {
        Schema::connection($this->getConnection())->create('bw_layouts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name')->unique();

            // What it was born from. The frame diverges freely afterwards.
            $table->string('preset', 20);
            $table->string('type', 20);

            // The frame itself: a whole HTML document with {{ $slot }} where
            // the page goes. Edited on the screens.
            $table->longText('content');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists('bw_layouts');
    }
};
