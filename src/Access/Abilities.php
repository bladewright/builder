<?php

namespace Bladewright\Access;

/**
 * Everything that can be done.
 *
 * Decisions are made on abilities, not on roles; a role is only a bundle of
 * abilities. v3 compared `role_id == 2` literally, so every new permission
 * scattered another check across the code.
 */
final class Abilities
{
    /** Can open the admin. */
    public const ACCESS_ADMIN = 'access-admin';

    /** Can edit block fields and body text. */
    public const EDIT_CONTENT = 'edit-content';

    /** Can publish a draft. */
    public const PUBLISH = 'publish';

    /**
     * Can read and write raw Blade.
     *
     * **This is the RCE boundary in practice.** Blade takes PHP, so whoever
     * holds this can run any code on the server. Never hand it to a Member.
     */
    public const WRITE_CODE = 'write-code';

    /** Can change Laravel configuration. */
    public const MANAGE_SETTINGS = 'manage-settings';

    /** Can run named tasks (npm run build and the like). */
    public const RUN_TASKS = 'run-tasks';

    /** Can change who is allowed to do what. */
    public const MANAGE_USERS = 'manage-users';

    public const ALL = [
        self::ACCESS_ADMIN,
        self::EDIT_CONTENT,
        self::PUBLISH,
        self::WRITE_CODE,
        self::MANAGE_SETTINGS,
        self::RUN_TASKS,
        self::MANAGE_USERS,
    ];

    /** The name it is registered under with the Gate. */
    public static function gate(string $ability): string
    {
        return 'bladewright.'.$ability;
    }
}
