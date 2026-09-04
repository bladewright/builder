<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tidying a page away is gone. **There were two ways to take a page off the
 * site, and they differed by one thing.**
 *
 * Unpublishing takes it off and keeps the URL reserved. Tidying away took it
 * off and freed the URL — and for that single difference it brought a second
 * idea of "not on the site", a shelf of its own on the list, a little box on
 * every shelved row to choose a URL to come back at, and a state column that
 * had to explain both.
 *
 * What it could do is still there in two moves that were already on the
 * screen: change the URL to `about-old`, then take it off the site. Nothing
 * about a page is lost by dropping this, because tidying away never held
 * anything — the revisions were always the way back.
 */
return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('bladewright.database.connection');
    }

    public function up(): void
    {
        Schema::connection($this->getConnection())->table('bw_components', function (Blueprint $table) {
            $table->dropColumn('archived_at');
        });
    }

    /**
     * The column comes back empty.
     *
     * **What was shelved cannot be**: nothing here knows which pages had been
     * tidied away, and a page that came back would come back with no URL.
     */
    public function down(): void
    {
        Schema::connection($this->getConnection())->table('bw_components', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('origin_hash');
        });
    }
};
