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

    /** Valore di tc_admin.user_type nel DB legacy. */
    public function tipoLegacy(): int
    {
        return match ($this) {
            self::SuperAdmin => 1,
            self::Admin => 2,
            self::Cliente => 3,
            self::Operatore => 4,
        };
    }
}
