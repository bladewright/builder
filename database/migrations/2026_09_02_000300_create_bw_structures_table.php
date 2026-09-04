<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Components, in the four-layer model (the owner's table, 2026-09-02).
 *
 * A component is **a structure that means something — a collection of
 * blocks**: Markdown and Blocks put together into something with a purpose.
 * Spacing belongs here and here only (`padding` and `gap`), which is why a
 * block never carries any.
 *
 * **The table is `bw_structures` for now**, because the old world still
 * holds `bw_components` (pages, layouts, the Blade block definitions) and
 * 105 files say its model's name. When that world is dismantled, this table
 * and its model take the real name. The word on the screens and in the
 * commands is `component` throughout — only the machinery is waiting.
 *
 * What a component holds is **references**: rows in `bw_structure_children`
 * point at blocks (and later at components) **by uuid**, resolved from the
 * name at the moment of insertion. Editing a block reaches every component
 * that shows it; renaming anything breaks nothing.
 */
return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('bladewright.database.connection');
    }

    public function up(): void
    {
        Schema::connection($this->getConnection())->create('bw_structures', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name')->unique();

            // section / article / nav / table / figure / form / field
            $table->string('type', 20);

            // With it, the arrangement gets a grid (an inner container is
            // rendered when the contents call for one — one tag is not
            // promised).
            $table->boolean('container')->default(false);

            // The arrangement the screens edit: direction, alignment, width,
            // padding, gap. **padding and gap are the only spacing anywhere.**
            $table->json('data')->nullable();

            $table->timestamps();
        });

        Schema::connection($this->getConnection())->create('bw_structure_children', function (Blueprint $table) {
            $table->id();
            $table->foreignId('structure_id')->constrained('bw_structures')->cascadeOnDelete();

            // block or component. **The uuid, never the name** — names are
            // for people, and people change them.
            $table->string('child_kind', 12);
            $table->uuid('child_uuid');

            // 1, 2, 3 … from the top (from the left, in a grid).
            $table->unsignedInteger('position');

            $table->index(['structure_id', 'position']);
            $table->index('child_uuid');
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists('bw_structure_children');
        Schema::connection($this->getConnection())->dropIfExists('bw_structures');
    }
};
