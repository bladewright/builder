<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Blocks, in the four-layer model (the owner's table, 2026-09-02).
 *
 * A block is **one named thing with its own content**: a Markdown block holds
 * its prose, an Image block its file and words, a Button its label and where
 * it goes. The types are the product's own fixed set — there is no Blade
 * definition behind one — and what a block is answerable for is Markdown,
 * and the editable things Markdown cannot express.
 *
 * **People say the name; the machinery holds the uuid.** A component that
 * uses a block refers to it by uuid, which is why renaming one breaks
 * nothing, and why the name must be unique — it is how a person addresses
 * the block, on the screens and in the commands alike.
 *
 * The content lives in `data` and is edited on the screens; the commands
 * build the skeleton (the owner's rule: screen work is too fine for a
 * terminal).
 */
return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('bladewright.database.connection');
    }

    public function up(): void
    {
        Schema::connection($this->getConnection())->create('bw_blocks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name')->unique();

            // markdown / image / video / audio / button / input / select /
            // textarea / embed — and label, which exists only inside forms.
            $table->string('type', 20);

            $table->json('data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists('bw_blocks');
    }
};
