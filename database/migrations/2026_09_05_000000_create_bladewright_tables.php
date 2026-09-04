<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Everything Bladewright keeps, made at once.
 *
 * **The four layers, and what they are made of.** A layout is the frame a
 * page wears; a page is a row of components; a component is an arrangement of
 * blocks; a block is one element. Nothing points at anything by id: the
 * children hold uuids, so a part can be renamed, copied or moved without a
 * single reference going stale.
 *
 * (This replaces the twenty-nine migrations the design was found through —
 * the creates, the drops, the renames. Nobody installing a package should
 * have to watch somebody else's history replay. **A table already standing is
 * left exactly as it is**, so a site installed before this stays where it is
 * and simply notes that this ran.)
 */
return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('bladewright.database.connection');
    }

    public function up(): void
    {
        $schema = Schema::connection($this->getConnection());

        // **The site's own settings**, overriding config() at boot — which is
        // how nothing is ever written to .env.
        $this->make($schema, 'bw_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        // **The admin's own accounts**, apart from the application's users:
        // signing in here is not signing in to the site.
        $this->make($schema, 'bw_users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        // **One block is one element.** What kind it is decides what its data
        // holds; markdown is the one that is many.
        $this->make($schema, 'bw_blocks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name')->unique();
            $table->string('type', 20);
            $table->json('data')->nullable();
            $table->timestamps();
        });

        // **A component is a structure that means something** — a section, a
        // nav, a form — and holds blocks, and other components.
        $this->make($schema, 'bw_structures', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name')->unique();
            $table->string('type', 20);
            $table->json('data')->nullable();
            $table->timestamps();
        });

        // What stands inside a component, in order. **The kind says whether
        // the uuid names a block or another component.**
        $this->make($schema, 'bw_structure_children', function (Blueprint $table) {
            $table->id();
            $table->foreignId('structure_id')->constrained('bw_structures')->cascadeOnDelete();
            $table->string('child_kind', 12);
            $table->uuid('child_uuid');
            $table->unsignedInteger('position');

            $table->index(['structure_id', 'position']);
            $table->index('child_uuid');
        });

        // **The frame a page wears**: a whole HTML document of the site's own,
        // with the header and footer components it carries.
        $this->make($schema, 'bw_layouts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name')->unique();
            $table->string('type', 20);
            $table->uuid('header_uuid')->nullable();
            $table->uuid('footer_uuid')->nullable();
            $table->string('font_family')->nullable();
            $table->longText('content');
            $table->timestamps();
        });

        // A page: **where it answers, what it wears, and when it is visible.**
        $this->make($schema, 'bw_pages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name')->unique();
            $table->string('url')->unique();
            $table->uuid('layout_uuid')->nullable();
            $table->string('locale', 12)->default('en');
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_from')->nullable();
            $table->timestamp('published_until')->nullable();
            $table->json('data')->nullable();
            $table->timestamps();
        });

        // The components a page shows, in order.
        $this->make($schema, 'bw_page_children', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained('bw_pages')->cascadeOnDelete();
            $table->uuid('child_uuid');
            $table->unsignedInteger('position');

            $table->index(['page_id', 'position']);
            $table->index('child_uuid');
        });
    }

    /**
     * Make a table, **unless it is already standing**.
     *
     * A site installed before these were gathered into one has every table
     * already; this then does nothing at all rather than failing, and the
     * site carries on exactly as it was.
     */
    private function make($schema, string $table, callable $shape): void
    {
        if (! $schema->hasTable($table)) {
            $schema->create($table, $shape);
        }
    }

    public function down(): void
    {
        $schema = Schema::connection($this->getConnection());

        // **The children first**, or a key would hold its parent in place.
        foreach ([
            'bw_page_children', 'bw_structure_children',
            'bw_pages', 'bw_layouts', 'bw_structures', 'bw_blocks',
            'bw_users', 'bw_settings',
        ] as $table) {
            $schema->dropIfExists($table);
        }
    }
};
