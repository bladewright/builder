<?php

namespace Bladewright\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Somebody who can open the admin.
 *
 * **Ours, not the host's.** The admin used to sign in against the host
 * application's users table, which put developers among the customer's own
 * members; these rows live in `bw_users` with the rest of the site, and the
 * host's table is entirely the host's again.
 *
 * **An email address and a password. No name** — the owner's rule; the
 * screens show the address.
 */
class User extends Authenticatable
{
    protected $table = 'bw_users';

    protected $fillable = ['email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    public function getConnectionName(): ?string
    {
        return config('bladewright.database.connection') ?? parent::getConnectionName();
    }

    protected function casts(): array
    {
        return ['password' => 'hashed'];
    }
}
