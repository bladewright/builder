<?php

namespace Bladewright\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Bladewright\Models\User;
use Bladewright\Tests\TestCase;

/**
 * `bladewright:user`, in the core shape: **create, update, delete.**
 *
 * The accounts are ours — `bw_users`, an email address and a password, no
 * name — so nothing here ever touches the host application's members.
 */
class UserCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_account_can_be_created_and_signs_in(): void
    {
        $this->artisan('bladewright:user', ['--create' => true, '--email' => 'kanri@example.com', '--password' => 'secret-password'])
            ->assertSuccessful();

        $this->post('/bladewright/login', [
            'email' => 'kanri@example.com',
            'password' => 'secret-password',
        ])->assertRedirect('/bladewright');
    }

    /**
     * **The host's users table is not needed at all any more.** It does not
     * even exist in this test app, and creating an account still works — the
     * old command required it, because it wrote there.
     */
    public function test_the_hosts_users_table_is_not_needed(): void
    {
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasTable('users'));

        $this->artisan('bladewright:user', ['--create' => true, '--email' => 'kanri@example.com', '--password' => 'secret-password'])
            ->assertSuccessful();

        $this->assertSame(1, User::query()->count());
    }

    /** An existing account is never rewritten by create. */
    public function test_create_refuses_an_existing_email(): void
    {
        User::create(['email' => 'kanri@example.com', 'password' => 'secret-password']);

        $this->artisan('bladewright:user', ['--create' => true, '--email' => 'kanri@example.com', '--password' => 'another-password'])
            ->assertFailed();
    }

    public function test_a_short_password_is_refused(): void
    {
        $this->artisan('bladewright:user', ['--create' => true, '--email' => 'kanri@example.com', '--password' => 'short'])
            ->assertFailed();

        $this->assertSame(0, User::query()->count());
    }

    /** The account is named by the address it has now; both parts can change. */
    public function test_update_changes_the_address_and_the_password(): void
    {
        User::create(['email' => 'old@example.com', 'password' => 'secret-password']);

        $this->artisan('bladewright:user', [
            '--update' => 'old@example.com',
            '--email' => 'new@example.com',
            '--password' => 'brand-new-password',
        ])->assertSuccessful();

        $user = User::query()->firstOrFail();

        $this->assertSame('new@example.com', $user->email);
        $this->assertTrue(Hash::check('brand-new-password', $user->password));
    }

    /** An address that already belongs to somebody cannot be taken by update. */
    public function test_update_refuses_a_taken_address(): void
    {
        User::create(['email' => 'one@example.com', 'password' => 'secret-password']);
        User::create(['email' => 'two@example.com', 'password' => 'secret-password']);

        $this->artisan('bladewright:user', ['--update' => 'one@example.com', '--email' => 'two@example.com'])
            ->assertFailed();
    }

    /** Deleting warns, asks, and only then removes. */
    public function test_delete_asks_first(): void
    {
        User::create(['email' => 'one@example.com', 'password' => 'secret-password']);
        User::create(['email' => 'two@example.com', 'password' => 'secret-password']);

        $this->artisan('bladewright:user', ['--delete' => true, '--email' => 'two@example.com'])
            ->expectsConfirmation('Delete [two@example.com]?', 'yes')
            ->assertSuccessful();

        $this->assertSame(0, User::query()->where('email', 'two@example.com')->count());
    }

    /** Answering no removes nobody. */
    public function test_declining_deletes_nobody(): void
    {
        User::create(['email' => 'one@example.com', 'password' => 'secret-password']);
        User::create(['email' => 'two@example.com', 'password' => 'secret-password']);

        $this->artisan('bladewright:user', ['--delete' => true, '--email' => 'two@example.com'])
            ->expectsConfirmation('Delete [two@example.com]?', 'no')
            ->assertSuccessful();

        $this->assertSame(2, User::query()->count());
    }

    /**
     * **The last account cannot be deleted.** With nobody left, nobody can
     * open the admin, and only the terminal can put that right.
     */
    public function test_the_last_account_is_refused(): void
    {
        User::create(['email' => 'only@example.com', 'password' => 'secret-password']);

        $this->artisan('bladewright:user', ['--delete' => true, '--email' => 'only@example.com'])
            ->assertFailed();

        $this->assertSame(1, User::query()->count());
    }
}
