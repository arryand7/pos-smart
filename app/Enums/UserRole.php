<?php

namespace App\Enums;

enum UserRole: string
{
    case SUPER_ADMIN = 'super_admin';
    case ADMIN = 'admin';
    case BENDAHARA = 'bendahara';
    case KASIR = 'kasir';
    case SANTRI = 'santri';
    case WALI = 'wali';

    public static function labels(): array
    {
        return [
            self::SUPER_ADMIN->value => 'Super Admin',
            self::ADMIN->value => 'Administrator',
            self::BENDAHARA->value => 'Bendahara',
            self::KASIR->value => 'Kasir',
            self::SANTRI->value => 'Santri',
            self::WALI->value => 'Wali Santri',
        ];
    }
}
