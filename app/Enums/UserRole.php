<?php

namespace App\Enums;

enum UserRole: string
{
    case Master = 'master';
    case User = 'user';

    public function label(): string
    {
        return match ($this) {
            self::Master => 'Administrador Master',
            self::User => 'Usuário',
        };
    }
}
