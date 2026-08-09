<?php

namespace App\Support;

class UserRole
{
    public const Admin = 'admin';

    public const Waiter = 'waiter';

    /** @return array<string, string> */
    public static function labels(): array
    {
        return [
            self::Admin => 'Administrador',
            self::Waiter => 'Garçom',
        ];
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_keys(self::labels());
    }

    public static function label(string $role): string
    {
        return self::labels()[$role] ?? $role;
    }
}
