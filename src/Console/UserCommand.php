<?php

namespace Bladewright\Console;

use Illuminate\Console\Command;
use Bladewright\Models\User;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * The admin's people: create, update, delete.
 *
 * One of the core commands (the owner's table, 2026-09-02). They live in
 * **`bw_users`, our own table** — an email address and a password, no name —
 * so a developer let into the admin never becomes a row among the host
 * application's own members, and deleting one endangers nothing of theirs.
 */
class UserCommand extends Command
{
    protected $signature = 'bladewright:user
        {--create : Somebody new to sign in with}
        {--update= : The email address of the account to change}
        {--delete : Take an account out (asks first)}
        {--email= : Their email address (--create), or the new one (--update, --delete)}
        {--password= : Their password (you are asked when omitted)}';

    protected $description = "The admin's accounts: create, update, delete";

    public function handle(): int
    {
        if ($this->option('create')) {
            return $this->create();
        }

        if (($current = $this->option('update')) !== null) {
            return $this->update((string) $current);
        }

        if ($this->option('delete')) {
            return $this->delete();
        }

        $this->components->error('Say what to do: --create, --update="who@example.com", or --delete --email="who@example.com".');

        return self::FAILURE;
    }

    private function create(): int
    {
        $email = (string) ($this->option('email') ?: text('Email address', required: true));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->components->error("[{$email}] is not an email address.");

            return self::FAILURE;
        }

        if (User::query()->where('email', $email)->exists()) {
            $this->components->error("{$email} already exists. They can sign in already.");

            return self::FAILURE;
        }

        $plain = (string) ($this->option('password') ?: password('Password', required: true));

        if (strlen($plain) < 8) {
            $this->components->error('The password needs at least 8 characters.');

            return self::FAILURE;
        }

        User::create(['email' => $email, 'password' => $plain]);

        $this->components->info("Created {$email}.");
        $this->components->bulletList([
            route('bladewright.admin.login').' is where they sign in',
            // **Everybody created here can do everything** until roles land.
            'Everyone here can open the admin and write code, for now',
        ]);

        return self::SUCCESS;
    }

    /** Change an address, a password, or both. **The account is named by the address it has now.** */
    private function update(string $current): int
    {
        $user = User::query()->where('email', $current)->first();

        if ($user === null) {
            $this->components->error("[{$current}] is not an account.");

            return self::FAILURE;
        }

        $email = $this->option('email');
        $plain = $this->option('password');

        if ($email === null && $plain === null) {
            $this->components->error('Say what changes: --email="new@example.com" and/or --password="…".');

            return self::FAILURE;
        }

        if ($email !== null) {
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->components->error("[{$email}] is not an email address.");

                return self::FAILURE;
            }

            if (User::query()->where('email', $email)->where('id', '!=', $user->id)->exists()) {
                $this->components->error("{$email} already belongs to somebody.");

                return self::FAILURE;
            }

            $user->email = $email;
        }

        if ($plain !== null) {
            if (strlen((string) $plain) < 8) {
                $this->components->error('The password needs at least 8 characters.');

                return self::FAILURE;
            }

            $user->password = $plain;
        }

        $user->save();

        $this->components->info("Updated. They sign in as {$user->email}.");

        return self::SUCCESS;
    }

    /**
     * Take one out. **Warned, and asked** — and never the last one.
     *
     * With no accounts left, nobody can open the admin at all, and only the
     * terminal can put that right. Better to refuse than to lock the door
     * from the inside.
     */
    private function delete(): int
    {
        $email = (string) ($this->option('email') ?: '');

        if ($email === '') {
            $this->components->error('Say who with --email="who@example.com".');

            return self::FAILURE;
        }

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->components->error("[{$email}] is not an account.");

            return self::FAILURE;
        }

        if (User::query()->count() === 1) {
            $this->components->error('That is the last account. Deleting it would lock the admin for everybody; create another first.');

            return self::FAILURE;
        }

        $this->components->warn('They can no longer sign in to the admin. It cannot be undone.');

        if (! $this->components->confirm("Delete [{$email}]?")) {
            $this->components->info('Nothing was deleted.');

            return self::SUCCESS;
        }

        $user->delete();

        $this->components->info("Deleted [{$email}].");

        return self::SUCCESS;
    }
}
