<?php

namespace Bladewright\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Bladewright\Models\Layout;
use Bladewright\Models\Page;
use Bladewright\Tests\TestCase;

/**
 * The install, and taking the site back to the state right after it.
 *
 * **`migrate:fresh` is never what this does.** By default the site shares the
 * customer's database, so that would take the customer's own tables with it.
 * Only what begins with `bw_` goes, and the uploaded files always stay.
 */
class InstallFreshTest extends TestCase
{
    use RefreshDatabase;

    /** One command: the welcome page is at /, out of the model's own pieces. */
    public function test_one_command_gets_you_a_working_site(): void
    {
        $this->installSite()->assertSuccessful();

        $this->get('/')
            ->assertOk()
            ->assertSee('Edit a live site from the browser.', false)
            // The four layers, named on the page they are made of.
            ->assertSee('<h3>Component</h3>', false)
            // The way in, dressed the way the rest of the frame is.
            ->assertSee('class="btn btn-light btn-lg fw-semibold"', false)
            ->assertSee("location.href='/bladewright'", false)
            // The hero wears the palette's glow, resolved at render time.
            ->assertSee('linear-gradient(135deg', false);

        // Built from the four layers, not from anything special-cased: the
        // page's three sections, and the header and footer the frame wears.
        $this->assertSame(1, Page::query()->count());
        $this->assertSame(1, Layout::query()->count());
        $this->assertSame(6, \Bladewright\Models\Structure::query()->count());
        $this->assertSame(10, \Bladewright\Models\Block::query()->count());

        // **The bands are components**, changed on their own screen — and
        // **each brings its own tag**: the seeded header is a <header>, the
        // frame writes none of its own.
        $layout = Layout::query()->firstOrFail();

        $this->assertNotNull($layout->header_uuid);
        $this->assertNotNull($layout->footer_uuid);
        $this->assertStringNotContainsString('<header', $layout->content);
        $this->assertSame('header', \Bladewright\Models\Structure::query()->where('name', 'bladewright-header')->firstOrFail()->type);
        $this->assertSame('footer', \Bladewright\Models\Structure::query()->where('name', 'bladewright-footer')->firstOrFail()->type);

        // The menu stands in a <nav> of its own, inside the <header>.
        $this->get('/')->assertSee('<header', false)->assertSee('<nav', false)->assertSee('<footer', false);
    }

    /** A site that already has content is left alone. */
    public function test_a_site_with_content_gets_no_welcome_page(): void
    {
        $this->installSite()->assertSuccessful();

        $this->installSite()
            ->expectsOutputToContain('already has content')
            ->assertSuccessful();

        $this->assertSame(1, Page::query()->count());
    }

    /** --fresh wipes and reinstalls, in one command. */
    public function test_it_wipes_the_site_and_installs_it_again(): void
    {
        $this->installSite()->assertSuccessful();

        $this->app->make(\Bladewright\Blocks\SitePages::class)->create('Old page', 'old');

        $this->installSite('--fresh')->assertSuccessful();

        $this->assertSame(0, Page::query()->where('name', 'Old page')->count());
        $this->get('/')->assertOk();
    }

    /** **The customer's own tables are not touched.** They do not begin with `bw_`. */
    public function test_it_leaves_the_customers_tables_alone(): void
    {
        Schema::create('shop_orders', function ($table) {
            $table->id();
            $table->string('code');
        });

        $this->app['db']->table('shop_orders')->insert(['code' => 'A-1']);

        $this->installSite()->assertSuccessful();
        $this->installSite('--fresh')->assertSuccessful();

        $this->assertTrue(Schema::hasTable('shop_orders'));
        $this->assertSame(1, $this->app['db']->table('shop_orders')->count());
    }

    /**
     * **The customer's own migrations are not run either.** The install runs
     * ours by path; half-written work of theirs must not go out because
     * somebody installed a package.
     */
    public function test_it_leaves_the_customers_pending_migrations_alone(): void
    {
        $dir = $this->app->databasePath('migrations');
        @mkdir($dir, 0777, true);
        file_put_contents($dir.'/2099_01_01_000000_create_customer_things_table.php', <<<'MIGRATION'
        <?php
        use Illuminate\Database\Migrations\Migration;
        use Illuminate\Database\Schema\Blueprint;
        use Illuminate\Support\Facades\Schema;

        return new class extends Migration {
            public function up(): void
            {
                Schema::create('customer_things', fn (Blueprint $t) => $t->id());
            }
        };
        MIGRATION);

        try {
            $this->installSite()->assertSuccessful();

            $this->assertFalse(Schema::hasTable('customer_things'));
        } finally {
            @unlink($dir.'/2099_01_01_000000_create_customer_things_table.php');
        }
    }

    /** **Media always stays.** It is the one thing nothing can restore. */
    public function test_it_keeps_the_uploaded_files(): void
    {
        $this->installSite()->assertSuccessful();

        $media = $this->app->make(\Bladewright\Media\MediaLibrary::class);
        $file = $media->store(\Illuminate\Http\UploadedFile::fake()->image('logo.png'));

        $this->installSite('--fresh')->assertSuccessful();

        $this->assertTrue($media->disk()->exists($file->path));
    }

    /** A wrong name wipes nothing. */
    public function test_a_wrong_name_wipes_nothing(): void
    {
        $this->installSite()->assertSuccessful();

        $this->artisan('bladewright:install --fresh')
            ->expectsQuestion("Type the site's name (Laravel) to wipe it", 'not-the-name')
            ->assertFailed();

        $this->assertSame(1, Page::query()->count());
    }

    /** It says what will be lost before it asks. */
    public function test_it_says_what_will_be_lost(): void
    {
        $this->installSite()->assertSuccessful();

        $this->installSite('--fresh')
            ->expectsOutputToContain('tables to drop')
            ->assertSuccessful();
    }

    /** The language answered here is the language new pages are born in. */
    public function test_the_language_question_reaches_new_pages(): void
    {
        $this->artisan('bladewright:install')
            ->expectsQuestion('What language does this site write in? (en, ja, …)', 'ja')
            ->expectsQuestion("What is the site's CSS written in?", 'Bootstrap')
            ->expectsConfirmation('Create somebody to sign in with?', 'no')
            ->assertSuccessful();

        // The welcome page itself was born in it.
        $this->assertSame('ja', Page::query()->firstOrFail()->locale);
    }

    /**
     * **Every account can do everything, and the install says so.**
     */
    public function test_it_says_the_admin_is_open_to_every_account(): void
    {
        \Bladewright\Models\User::create(['email' => 'someone@example.com', 'password' => 'secret-password']);

        $this->artisan('bladewright:install')
            ->expectsQuestion('What language does this site write in? (en, ja, …)', 'en')
            ->expectsQuestion("What is the site's CSS written in?", 'Bootstrap')
            ->expectsOutputToContain('can open the admin and write code')
            ->assertSuccessful();
    }

    /**
     * **The install cannot leave somebody at a 500 without a word.**
     *
     * We run our own migrations and no more. On a database the application
     * has never migrated, the site answers 500 the moment it is opened —
     * right after an install that said everything went well. So the install
     * says what is still missing.
     */
    public function test_it_says_when_the_application_has_not_migrated_itself(): void
    {
        config()->set('session.driver', 'database');
        config()->set('session.table', 'sessions');

        \Illuminate\Support\Facades\Schema::dropIfExists('sessions');

        $this->artisan('bladewright:install')
            ->expectsQuestion('What language does this site write in? (en, ja, …)', 'en')
            ->expectsChoice("What is the site's CSS written in?", 'Plain CSS', ['Bootstrap', 'Pico', 'Plain CSS'])
            ->expectsConfirmation('Create somebody to sign in with?', 'no')
            ->expectsOutputToContain('has not run its own migrations')
            ->expectsOutputToContain('php artisan migrate')
            ->assertSuccessful();
    }

    /** With the table there, nothing is said — a warning nobody needs is noise. */
    public function test_it_says_nothing_when_the_application_is_ready(): void
    {
        config()->set('session.driver', 'database');

        \Illuminate\Support\Facades\Schema::dropIfExists('sessions');
        \Illuminate\Support\Facades\Schema::create('sessions', function ($table) {
            $table->string('id')->primary();
        });

        $this->artisan('bladewright:install')
            ->expectsQuestion('What language does this site write in? (en, ja, …)', 'en')
            ->expectsChoice("What is the site's CSS written in?", 'Plain CSS', ['Bootstrap', 'Pico', 'Plain CSS'])
            ->expectsConfirmation('Create somebody to sign in with?', 'no')
            ->doesntExpectOutputToContain('has not run its own migrations')
            ->assertSuccessful();
    }

    /** A driver that keeps nothing in the database is never warned about. */
    public function test_a_file_session_is_not_a_missing_table(): void
    {
        config()->set('session.driver', 'file');
        config()->set('cache.default', 'file');

        \Illuminate\Support\Facades\Schema::dropIfExists('sessions');

        $this->artisan('bladewright:install')
            ->expectsQuestion('What language does this site write in? (en, ja, …)', 'en')
            ->expectsChoice("What is the site's CSS written in?", 'Plain CSS', ['Bootstrap', 'Pico', 'Plain CSS'])
            ->expectsConfirmation('Create somebody to sign in with?', 'no')
            ->doesntExpectOutputToContain('has not run its own migrations')
            ->assertSuccessful();
    }

    /* ------------------------------------------------------------------ */
    /* The front page, when something else already answers it              */
    /* ------------------------------------------------------------------ */

    private function routesSaying(string $body): string
    {
        $file = base_path('routes/web.php');

        @mkdir(dirname($file), 0o777, true);
        file_put_contents($file, $body);

        return $file;
    }

    private const LARAVELS_OWN = <<<'PHP'
    <?php

    use Illuminate\Support\Facades\Route;

    Route::get('/', function () {
        return view('welcome');
    });

    Route::get('/about', fn () => 'mine');

    PHP;

    private function install(string $answer)
    {
        return $this->artisan('bladewright:install')
            ->expectsQuestion('What language does this site write in? (en, ja, …)', 'en')
            ->expectsChoice("What is the site's CSS written in?", 'Plain CSS', ['Bootstrap', 'Pico', 'Plain CSS'])
            ->expectsConfirmation('Create somebody to sign in with?', 'no')
            ->expectsConfirmation('Comment it out? (nothing else in the file is touched)', $answer);
    }

    /**
     * **Consent, not intrusion.** We never write into the application's tree
     * uninvited — so it is asked, and a yes is what lets us in.
     */
    public function test_it_asks_before_freeing_the_front_page(): void
    {
        $file = $this->routesSaying(self::LARAVELS_OWN);

        $this->install('yes')
            ->expectsOutputToContain('Something else already answers /')
            ->assertSuccessful();

        $now = file_get_contents($file);

        // The route is commented, and said who did it.
        $this->assertStringContainsString("// Route::get('/', function () {", $now);
        $this->assertStringContainsString('//     return view(\'welcome\');', $now);
        $this->assertStringContainsString('// });', $now);
        $this->assertStringContainsString('bladewright:install, with permission', $now);

        // **Nothing else in the file is touched.**
        $this->assertStringContainsString("Route::get('/about', fn () => 'mine');", $now);
        $this->assertStringNotContainsString("// Route::get('/about'", $now);
    }

    /** **A no leaves the file exactly as it was**, to the character. */
    public function test_a_no_leaves_the_routes_untouched(): void
    {
        $file = $this->routesSaying(self::LARAVELS_OWN);

        $this->install('no')
            ->expectsOutputToContain('Left as it is')
            ->assertSuccessful();

        $this->assertSame(self::LARAVELS_OWN, file_get_contents($file));
    }

    /** With nothing in the way, nothing is asked — a question nobody needs is noise. */
    public function test_it_says_nothing_when_the_front_page_is_free(): void
    {
        $this->routesSaying("<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n");

        $this->artisan('bladewright:install')
            ->expectsQuestion('What language does this site write in? (en, ja, …)', 'en')
            ->expectsChoice("What is the site's CSS written in?", 'Plain CSS', ['Bootstrap', 'Pico', 'Plain CSS'])
            ->expectsConfirmation('Create somebody to sign in with?', 'no')
            ->doesntExpectOutputToContain('Something else already answers')
            ->assertSuccessful();
    }

    /**
     * **What cannot be read plainly is left alone.** Somebody's own
     * arrangement — a controller, a group, a variable — is not ours to guess
     * at, and a wrong guess would comment out the wrong thing.
     */
    public function test_a_route_it_cannot_read_plainly_is_left_alone(): void
    {
        $clever = "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n\nRoute::get(\$home, [HomeController::class, 'index']);\n";
        $file = $this->routesSaying($clever);

        $this->artisan('bladewright:install')
            ->expectsQuestion('What language does this site write in? (en, ja, …)', 'en')
            ->expectsChoice("What is the site's CSS written in?", 'Plain CSS', ['Bootstrap', 'Pico', 'Plain CSS'])
            ->expectsConfirmation('Create somebody to sign in with?', 'no')
            ->doesntExpectOutputToContain('Something else already answers')
            ->assertSuccessful();

        $this->assertSame($clever, file_get_contents($file));
    }
}
