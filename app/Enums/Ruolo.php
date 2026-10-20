<?php

namespace App\Enums;

enum Ruolo: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Cliente = 'cliente';
    case Operatore = 'operatore';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super admin',
            self::Admin => 'Amministratore',
            self::Cliente => 'Cliente',
            self::Operatore => 'Operatore',
        };
    }
}
