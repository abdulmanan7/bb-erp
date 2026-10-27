<?php

namespace App\Libraries;

/**
 * Holds the user resolved from a mobile bearer token for the current request.
 * Cookie-session users are read from the session as before.
 */
final class ApiUser
{
    private static ?array $user = null;

    public static function set(array $user): void
    {
        self::$user = $user;
    }

    public static function get(): ?array
    {
        return self::$user;
    }
}
